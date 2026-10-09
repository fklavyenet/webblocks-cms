<?php

namespace WebBlocks\Cms\Services\SiteNotifications;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Mail\SiteMessageAlert;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\SiteNotifications\SiteNotificationChannels;
use WebBlocks\Cms\Support\SiteNotifications\SiteNotificationPolicy;

class SiteNotificationDispatcher
{
  public function __construct(private readonly SiteNotificationChannels $channels, private readonly SiteNotificationMailer $mailer) {}

  public function submit(Site $site, string $channel, int $sourceId): void
  {
    if (! Schema::hasTable('wbcms_site_notification_events')) {
      // Existing installations awaiting schema updates retain immediate mail.
      $adapter = $this->channels->all()[$channel] ?? null;
      $source = $adapter?->source($site, $sourceId);
      if ($source && $adapter->enabled($source)) {
        $alert = SiteNotificationPolicy::forSite($site)['notification_mode'] === 'alert_only';
        $mail = $alert ? $this->alert($site, $adapter->inbox($site)) : $adapter->fullMail([$source]);
        $adapter->record($source, $this->mailer->send($mail, $adapter->recipient($source), $alert));
      }

      return;
    }
    DB::table('wbcms_site_notification_events')->insertOrIgnore([
      'site_id' => $site->id, 'channel' => $channel, 'source_id' => $sourceId,
      'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->dispatch($site, $channel);
  }

  public function run(): void
  {
    if (! Schema::hasTable('wbcms_site_notification_events') || ! Schema::hasColumn('wbcms_sites', 'notification_settings')) {
      return;
    }
    $this->recoverInterruptedAttempts();
    Site::query()->each(function (Site $site): void {
      foreach (array_keys($this->channels->all()) as $channel) {
        $this->dispatch($site, $channel);
      }
      $this->summary($site);
    });
    DB::table('wbcms_site_notification_events')->where('status', '!=', 'pending')->where('updated_at', '<', now()->subDays(30))->delete();
    DB::table('wbcms_site_notification_states')->where('last_attempt_at', '<', now()->subDays(30))->delete();
  }

  private function dispatch(Site $site, string $channel): void
  {
    $lock = Cache::lock('wbcms-site-notifications:'.$site->id.':'.$channel, 900);
    if (! $lock->get()) {
      return;
    }
    try {
      $site = $site->fresh() ?? $site;
      $adapter = $this->channels->all()[$channel] ?? null;
      if (! $adapter) {
        return; // Disabled plugins do not consume their pending events.
      }
      $policy = SiteNotificationPolicy::forSite($site);
      $groups = [];
      // Bound work per pass, so bursts cannot exhaust a scheduler process.
      $events = DB::table('wbcms_site_notification_events')->where('site_id', $site->id)->where('channel', $channel)->where('status', 'pending')->orderBy('id')->limit(500)->get();
      foreach ($events as $event) {
        $source = $adapter->source($site, $event->source_id);
        if (! $source || ! $adapter->enabled($source)) {
          $this->finish([$event->id], 'skipped');
          if ($source) {
            $adapter->record($source, 'skipped');
          }

          continue;
        }
        $recipient = $adapter->recipient($source);
        if (! $recipient) {
          $this->finish([$event->id], 'not_configured');
          $adapter->record($source, 'not_configured');

          continue;
        }
        $group = $recipient.'|'.$adapter->group($source);
        $groups[$group]['recipient'] = $recipient;
        $groups[$group]['items'][] = ['event' => $event, 'source' => $source];
      }
      if ($policy['notification_frequency'] === 'daily') {
        return; // One combined daily summary handles all participating channels.
      }
      foreach ($groups as $group => $batch) {
        $recipient = $batch['recipient'];
        $items = $batch['items'];
        $key = hash('sha256', $site->id.'|'.$channel.'|'.$group);
        $state = $this->state($site, $key, $channel);
        $frequency = $policy['notification_frequency'];
        if ($frequency === 'batched' && $state->last_attempt_at && now()->lt(Carbon::parse($state->last_attempt_at)->addMinutes($policy['batch_minutes']))) {
          continue;
        }
        // Claim before calling the transport. Ambiguous transport failures are
        // never automatically retried, including after a process crash.
        $ids = array_map(fn ($item) => $item['event']->id, $items);
        $this->finish($ids, 'attempting');
        DB::table('wbcms_site_notification_states')->where('key', $key)->update([
          'last_attempt_at' => now(),
        ]);
        $sources = array_map(fn ($item) => $item['source'], $items);
        $current = $site->fresh() ?? $site;
        $alert = SiteNotificationPolicy::forSite($current)['notification_mode'] === 'alert_only';
        $mail = $alert ? $this->alert($site, $adapter->inbox($site), count($sources)) : $adapter->fullMail($sources);
        $status = $this->mailer->send($mail, $recipient, $alert);
        $this->finish($ids, $status);
        DB::table('wbcms_site_notification_states')->where('key', $key)->update(['status' => $status]);
        foreach ($sources as $source) {
          $adapter->record($source, $status);
        }
      }
    } finally {
      $lock->release();
    }
  }

  private function summary(Site $site): void
  {
    $site = $site->fresh() ?? $site;
    $policy = SiteNotificationPolicy::forSite($site);
    $dailyDelivery = $policy['notification_frequency'] === 'daily';
    if (! $policy['daily_summary'] && ! $dailyDelivery) {
      return;
    }
    $local = now()->setTimezone($site->resolvedTimezone());
    if ($local->hour < $policy['summary_hour']) {
      return;
    }
    $lock = Cache::lock('wbcms-site-notifications:'.$site->id.':summary', 120);
    if (! $lock->get()) {
      return;
    }
    try {
      // Check the daily window even when there is no work. A message arriving
      // after today's scheduled summary belongs to tomorrow's summary.
      $windowKey = hash('sha256', $site->id.'|summary-window');
      $window = $this->state($site, $windowKey, 'summary_window');
      if ($window->last_daily_date === $local->toDateString()) {
        return;
      }
      DB::table('wbcms_site_notification_states')->where('key', $windowKey)->update(['last_daily_date' => $local->toDateString(), 'last_attempt_at' => now()]);
      $groups = [];
      $channels = $this->channels->all();
      foreach ($channels as $channel => $adapter) {
        foreach ($adapter->summaries($site) as $recipient => $counts) {
          if ($counts['unread'] + $counts['awaiting'] === 0) {
            continue;
          }
          $groups[$recipient] ??= ['unread' => 0, 'awaiting' => 0, 'links' => [], 'events' => []];
          $groups[$recipient]['unread'] += $counts['unread'];
          $groups[$recipient]['awaiting'] += $counts['awaiting'];
          $groups[$recipient]['links'][] = ['label' => $adapter->label(), 'url' => $adapter->inbox($site), 'unread' => $counts['unread'], 'awaiting' => $counts['awaiting']];
        }
        if ($dailyDelivery) {
          $events = DB::table('wbcms_site_notification_events')->where('site_id', $site->id)->where('channel', $channel)->where('status', 'pending')->orderBy('id')->limit(500)->get();
          foreach ($events as $event) {
            $source = $adapter->source($site, $event->source_id);
            $recipient = $source && $adapter->enabled($source) ? $adapter->recipient($source) : null;
            if ($recipient && isset($groups[$recipient])) {
              $groups[$recipient]['events'][] = ['id' => $event->id, 'source' => $source, 'channel' => $channel];
            } elseif ($source) {
              // A resolved/read-and-replied message no longer needs a new alert.
              $this->finish([$event->id], 'skipped');
              $adapter->record($source, 'skipped');
            }
          }
        }
      }
      foreach ($groups as $recipient => $counts) {
        if (! $policy['daily_summary'] && $counts['events'] === []) {
          continue;
        }
        $key = hash('sha256', $site->id.'|summary|'.$recipient);
        $state = $this->state($site, $key, 'summary');
        if ($state->last_daily_date === $local->toDateString()) {
          continue;
        }
        $ids = array_column($counts['events'], 'id');
        $this->finish($ids, 'attempting');
        DB::table('wbcms_site_notification_states')->where('key', $key)->update(['last_daily_date' => $local->toDateString(), 'last_attempt_at' => now()]);
        $safeCounts = array_intersect_key($counts, array_flip(['unread', 'awaiting', 'links']));
        $status = $this->mailer->send($this->alert($site, $counts['links'][0]['url'], summary: $safeCounts), $recipient, true);
        DB::table('wbcms_site_notification_states')->where('key', $key)->update(['status' => $status]);
        $this->finish($ids, $status);
        foreach ($counts['events'] as $event) {
          $channels[$event['channel']]->record($event['source'], $status);
        }
      }
    } finally {
      $lock->release();
    }
  }

  private function alert(Site $site, string $url, int $count = 1, ?array $summary = null): SiteMessageAlert
  {
    return (new SiteMessageAlert($site->publicDisplayName() ?: $site->canonicalDomain() ?: $site->name, $url, $count, $summary))
      ->locale(app(LocaleResolver::class)->default()->code);
  }

  private function recoverInterruptedAttempts(): void
  {
    $channels = $this->channels->all();
    DB::table('wbcms_site_notification_events')->where('status', 'attempting')->where('updated_at', '<', now()->subMinutes(15))
      ->orderBy('id')->each(function ($event) use ($channels): void {
        $site = Site::query()->find($event->site_id);
        $channel = $channels[$event->channel] ?? null;
        $source = $site && $channel ? $channel->source($site, $event->source_id) : null;
        if ($source) {
          $channel->record($source, 'failed');
        }
        $this->finish([$event->id], 'failed');
      });
  }

  private function state(Site $site, string $key, string $channel): object
  {
    DB::table('wbcms_site_notification_states')->insertOrIgnore(['key' => $key, 'site_id' => $site->id, 'channel' => $channel]);

    return DB::table('wbcms_site_notification_states')->where('key', $key)->first();
  }

  private function finish(array $ids, string $status): void
  {
    DB::table('wbcms_site_notification_events')->whereIn('id', $ids)->update(['status' => $status, 'updated_at' => now()]);
  }
}
