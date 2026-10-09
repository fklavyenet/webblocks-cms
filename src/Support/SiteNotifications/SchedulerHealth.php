<?php

namespace WebBlocks\Cms\Support\SiteNotifications;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemSetting;

class SchedulerHealth
{
  public const STATE_KEY = 'runtime.scheduler_health';

  public const STALE_AFTER_SECONDS = 300;

  // This method is registered as a scheduled callback only. A manual dispatch
  // or HTTP read must never manufacture evidence that the scheduler is running.
  public function scheduledHeartbeat(): void
  {
    $this->record(['scheduler_last_seen_at' => now()->utc()->toIso8601String()]);
  }

  public function dispatchStarted(): void
  {
    $this->record(['notifications_started_at' => now()->utc()->toIso8601String()]);
  }

  public function dispatchCompleted(): void
  {
    $this->record(['notifications_completed_at' => now()->utc()->toIso8601String(), 'notifications_failed_at' => null]);
  }

  public function dispatchFailed(): void
  {
    $this->record(['notifications_failed_at' => now()->utc()->toIso8601String()]);
  }

  public function snapshot(): array
  {
    $state = $this->read();
    $seen = $this->timestamp($state['scheduler_last_seen_at'] ?? null);
    $completed = $this->timestamp($state['notifications_completed_at'] ?? null);
    $started = $this->timestamp($state['notifications_started_at'] ?? null);
    $failed = $this->timestamp($state['notifications_failed_at'] ?? null);
    $ready = Schema::hasTable((new SystemSetting)->getTable())
      && Schema::hasTable('wbcms_site_notification_events') && Schema::hasTable('wbcms_site_notification_states');
    $scheduler = ! $seen ? 'unverified' : ($this->stale($seen) ? 'delayed' : 'healthy');
    $dispatch = ! $completed ? 'unverified' : ($this->stale($completed) ? 'delayed' : 'healthy');
    if ($failed && (! $completed || $failed->gte($completed))) {
      $dispatch = 'failed';
    } elseif ($started && (! $completed || $started->gt($completed))) {
      $dispatch = $this->stale($started) ? 'delayed' : 'running';
    }
    $status = ! $ready ? 'unavailable' : ($scheduler !== 'healthy' ? $scheduler : $dispatch);

    return [
      'status' => $status,
      'scheduler_status' => $scheduler,
      'notification_status' => $dispatch,
      'last_seen_at' => $seen?->toIso8601String(),
      'last_completed_at' => $completed?->toIso8601String(),
      'last_failed_at' => $failed?->toIso8601String(),
      'stale_after_seconds' => self::STALE_AFTER_SECONDS,
    ];
  }

  public function forSite(Site $site): array
  {
    return $this->snapshot() + ['required' => $this->requiredForSite($site)];
  }

  public function requiredForSite(Site $site): bool
  {
    $policy = SiteNotificationPolicy::forSite($site);

    return $policy['notification_frequency'] !== 'immediate' || $policy['daily_summary'];
  }

  private function stale(Carbon $time): bool
  {
    return $time->lt(now()->subSeconds(self::STALE_AFTER_SECONDS));
  }

  private function timestamp(mixed $value): ?Carbon
  {
    if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
      return null;
    }
    try {
      $time = Carbon::parse($value)->utc();

      return $time->gt(now()->addMinute()) ? null : $time;
    } catch (Throwable) {
      return null;
    }
  }

  private function read(): array
  {
    if (! Schema::hasTable((new SystemSetting)->getTable())) {
      return [];
    }
    $raw = SystemSetting::query()->where('key', self::STATE_KEY)->value('value');
    $state = is_string($raw) ? json_decode($raw, true) : null;

    return is_array($state) ? $state : [];
  }

  private function record(array $updates): void
  {
    if (! Schema::hasTable((new SystemSetting)->getTable())) {
      return;
    }
    DB::transaction(function () use ($updates): void {
      SystemSetting::query()->firstOrCreate(['key' => self::STATE_KEY], ['value' => '{}']);
      $row = SystemSetting::query()->where('key', self::STATE_KEY)->lockForUpdate()->firstOrFail();
      $state = json_decode((string) $row->value, true);
      $row->update(['value' => json_encode(array_replace(is_array($state) ? $state : [], $updates), JSON_THROW_ON_ERROR)]);
    });
  }
}
