<?php

namespace WebBlocks\Cms\Support\SiteNotifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Mail\ContactMessageBatch;
use WebBlocks\Cms\Mail\ContactMessageNotification;
use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Models\Site;

class ContactNotificationChannel implements SiteNotificationChannel
{
  public function source(Site $site, int $id): ?Model
  {
    return ContactMessage::query()->whereKey($id)->whereHas('page', fn ($query) => $query->where('site_id', $site->id))->first();
  }

  public function enabled(Model $source): bool
  {
    return $source->notification_enabled && ! in_array($source->status, ['spam', 'quarantined', 'archived'], true);
  }

  public function group(Model $source): string
  {
    return 'contact';
  }

  public function recipient(Model $source): ?string
  {
    return $this->address($source);
  }

  private function address(ContactMessage $source): ?string
  {
    // Preserve the submission's deliberately resolved routing. Never use the
    // visitor's email as a destination or sender.
    foreach ([$source->notification_recipient, config('contact.recipient_email'), config('mail.from.address')] as $candidate) {
      $candidate = trim((string) $candidate);
      if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
        return $candidate;
      }
    }

    return null;
  }

  public function fullMail(array $sources): Mailable
  {
    return count($sources) === 1 ? new ContactMessageNotification($sources[0]) : new ContactMessageBatch($sources);
  }

  public function record(Model $source, string $status): void
  {
    $source->forceFill([
      'notification_status' => $status,
      'notification_sent_at' => $status === 'sent' ? now() : null,
      'notification_error' => $status === 'failed' ? __('webblocks-cms::notifications.failed') : null,
      'notification_reason' => $status === 'not_configured' ? __('webblocks-cms::notifications.not_configured') : null,
    ])->save();
  }

  public function summaries(Site $site): array
  {
    if (! Schema::hasTable((new ContactMessage)->getTable())) {
      return [];
    }
    $counts = [];
    ContactMessage::query()->whereHas('page', fn ($query) => $query->where('site_id', $site->id))
      ->whereIn('status', ['new', 'read'])->where('notification_enabled', true)
      ->each(function (ContactMessage $message) use (&$counts): void {
        $recipient = $this->enabled($message) ? $this->recipient($message) : null;
        if ($recipient) {
          $counts[$recipient] ??= ['unread' => 0, 'awaiting' => 0];
          $counts[$recipient]['unread'] += $message->status === 'new' ? 1 : 0;
          $counts[$recipient]['awaiting']++;
        }
      });

    return $counts;
  }

  public function label(): string
  {
    return 'webblocks-cms::notifications.channel_contact';
  }

  public function inbox(Site $site): string
  {
    return SiteNotificationPolicy::inbox('contact-messages');
  }
}
