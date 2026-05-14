<?php

namespace App\Support\Contact;

final class ContactMessageNotificationResult
{
    public function __construct(
        public readonly bool $enabled,
        public readonly ?string $recipient,
        public readonly ?string $error,
        public readonly bool $sent,
    ) {}

    public static function skipped(): self
    {
        return new self(enabled: false, recipient: null, error: null, sent: false);
    }

    public static function sent(string $recipient): self
    {
        return new self(enabled: true, recipient: $recipient, error: null, sent: true);
    }

    public static function failed(?string $recipient, string $error): self
    {
        return new self(enabled: true, recipient: $recipient, error: $error, sent: false);
    }
}
