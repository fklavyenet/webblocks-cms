<?php

namespace WebBlocks\Cms\Support\Contact;

use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Services\SiteNotifications\SiteNotificationDispatcher;
use WebBlocks\Cms\Support\SiteNotifications\ContactNotificationChannel;

class ContactMessageNotifier
{
  public function send(ContactMessage $contactMessage): ContactMessageNotificationResult
  {
    if (! $contactMessage->notification_enabled) {
      return ContactMessageNotificationResult::skipped(__('webblocks-cms::notifications.disabled'));
    }
    $site = $contactMessage->page?->site;
    if (! $site) {
      return ContactMessageNotificationResult::notConfigured(__('webblocks-cms::notifications.site_missing'));
    }
    app(SiteNotificationDispatcher::class)->submit($site, 'contact', $contactMessage->id);
    $contactMessage->refresh();
    $recipient = app(ContactNotificationChannel::class)->recipient($contactMessage);
    $status = $contactMessage->notification_status ?: 'pending';

    return new ContactMessageNotificationResult(
      enabled: true, recipient: $recipient, error: $contactMessage->notification_error,
      sent: $status === 'sent', status: $status, reason: $contactMessage->notification_reason,
      recipientSource: $contactMessage->notification_recipient_source,
    );
  }
}
