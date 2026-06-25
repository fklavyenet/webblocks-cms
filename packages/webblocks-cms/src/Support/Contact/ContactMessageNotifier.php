<?php

namespace WebBlocks\Cms\Support\Contact;

use Illuminate\Support\Facades\Mail;
use Throwable;
use WebBlocks\Cms\Mail\ContactMessageNotification;
use WebBlocks\Cms\Models\ContactMessage;

class ContactMessageNotifier
{
  public function send(ContactMessage $contactMessage): ContactMessageNotificationResult
  {
    if (! $contactMessage->notification_enabled) {
      return ContactMessageNotificationResult::skipped('Email notification is disabled for this Contact Form.');
    }

    [$recipient, $recipientSource] = $this->resolveRecipient(
      $contactMessage->notification_recipient,
      $contactMessage->notification_recipient_source,
    );

    if ($recipient === null) {
      return ContactMessageNotificationResult::notConfigured('No contact recipient email is configured.');
    }

    $mailerReason = $this->notConfiguredReasonForMailer();

    if ($mailerReason !== null) {
      return ContactMessageNotificationResult::notConfigured($mailerReason, $recipient, $recipientSource);
    }

    try {
      Mail::to($recipient)->send(new ContactMessageNotification($contactMessage));

      return ContactMessageNotificationResult::sent($recipient, $recipientSource);
    } catch (Throwable $throwable) {
      return ContactMessageNotificationResult::failed($recipient, $this->normalizeFailureMessage($throwable), $recipientSource);
    }
  }

  private function resolveRecipient(?string $storedRecipient, ?string $storedSource): array
  {
    $candidate = trim((string) $storedRecipient);

    if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
      return [$candidate, $storedSource ?: 'contact_form'];
    }

    $fallback = trim((string) config('contact.recipient_email'));

    if ($fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
      return [$fallback, 'CONTACT_RECIPIENT_EMAIL'];
    }

    $fromAddress = trim((string) config('mail.from.address'));

    if ($fromAddress !== '' && filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
      return [$fromAddress, 'MAIL_FROM_ADDRESS'];
    }

    return [null, null];
  }

  private function notConfiguredReasonForMailer(): ?string
  {
    $mailer = strtolower(trim((string) config('mail.default')));

    if ($mailer === '' || in_array($mailer, ['array', 'log', 'null'], true)) {
      return 'Mail delivery is not configured for a real outbound transport.';
    }

    if ($mailer !== 'smtp') {
      return null;
    }

    $host = trim((string) config('mail.mailers.smtp.host'));
    $port = trim((string) config('mail.mailers.smtp.port'));

    if ($host === '' || $port === '') {
      return 'SMTP mail configuration is incomplete.';
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
