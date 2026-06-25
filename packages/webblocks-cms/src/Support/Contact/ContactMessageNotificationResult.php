<?php

namespace WebBlocks\Cms\Support\Contact;

final class ContactMessageNotificationResult
{
  public function __construct(
    public readonly bool $enabled,
    public readonly ?string $recipient,
    public readonly ?string $error,
    public readonly bool $sent,
    public readonly string $status,
    public readonly ?string $reason = null,
    public readonly ?string $recipientSource = null,
  ) {}

  public static function skipped(string $reason, ?string $recipient = null, ?string $recipientSource = null): self
  {
    return new self(
      enabled: false,
      recipient: $recipient,
      error: null,
      sent: false,
      status: 'skipped',
      reason: $reason,
      recipientSource: $recipientSource,
    );
  }

  public static function notConfigured(string $reason, ?string $recipient = null, ?string $recipientSource = null): self
  {
    return new self(
      enabled: true,
      recipient: $recipient,
      error: null,
      sent: false,
      status: 'not_configured',
      reason: $reason,
      recipientSource: $recipientSource,
    );
  }

  public static function sent(string $recipient, ?string $recipientSource = null): self
  {
    return new self(
      enabled: true,
      recipient: $recipient,
      error: null,
      sent: true,
      status: 'sent',
      recipientSource: $recipientSource,
    );
  }

  public static function failed(?string $recipient, string $error, ?string $recipientSource = null): self
  {
    return new self(
      enabled: true,
      recipient: $recipient,
      error: $error,
      sent: false,
      status: 'failed',
      reason: $error,
      recipientSource: $recipientSource,
    );
  }
}
