<?php

namespace WebBlocks\Cms\Support\Contact;

use WebBlocks\Cms\Mail\ContactMessageNotification;
use Illuminate\Support\Facades\Mail;
use Throwable;
use WebBlocks\Cms\Models\ContactMessage;

class ContactMessageNotifier
{
    public function send(ContactMessage $contactMessage): ContactMessageNotificationResult
    {
        if (! $contactMessage->notification_enabled) {
            return ContactMessageNotificationResult::skipped();
        }

        $recipient = $this->resolveRecipient($contactMessage->notification_recipient);

        if ($recipient === null) {
            return ContactMessageNotificationResult::failed(null, 'No contact recipient email is configured.');
        }

        try {
            Mail::to($recipient)->send(new ContactMessageNotification($contactMessage));

            return ContactMessageNotificationResult::sent($recipient);
        } catch (Throwable $throwable) {
            return ContactMessageNotificationResult::failed($recipient, $this->normalizeFailureMessage($throwable));
        }
    }

    private function resolveRecipient(?string $storedRecipient): ?string
    {
        $candidate = trim((string) $storedRecipient);

        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            return $candidate;
        }

        $fallback = trim((string) config('contact.recipient_email'));

        if ($fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
            return $fallback;
        }

        $fromAddress = trim((string) config('mail.from.address'));

        if ($fromAddress !== '' && filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            return $fromAddress;
        }

        return null;
    }

    private function normalizeFailureMessage(Throwable $throwable): string
    {
        $message = trim($throwable->getMessage());

        if ($message === '') {
            return 'Notification delivery failed.';
        }

        $message = preg_replace('/([?&](?:password|passwd|pwd|token|api[_-]?key|secret)=)[^&\s]+/i', '$1[redacted]', $message) ?? $message;
        $message = preg_replace('/\b(password|passwd|pwd|token|api[_-]?key|secret)=\S+/i', '$1=[redacted]', $message) ?? $message;
        $message = preg_replace('/\b[A-Za-z0-9+\/=_-]{24,}\b/', '[redacted]', $message) ?? $message;

        return mb_strimwidth($message, 0, 240, '...');
    }
}
