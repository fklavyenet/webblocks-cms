<?php

namespace WebBlocks\Cms\Support\Mail;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;
use WebBlocks\Cms\Notifications\System\CmsTestEmail;
use WebBlocks\Cms\Support\System\SystemSettings;

class CmsTestEmailSender
{
  public function __construct(
    private readonly CmsMailSettingsResolver $mailSettingsResolver,
    private readonly SystemSettings $systemSettings,
  ) {}

  public function send(string $recipient): void
  {
    try {
      Notification::route('mail', $recipient)
        ->notify(new CmsTestEmail($this->messageContext()));
    } catch (Throwable $exception) {
      Log::warning('CMS test email could not be sent.', $this->mailSettingsResolver->logContext() + [
        'recipient_domain' => $this->recipientDomain($recipient),
        'exception_class' => $exception::class,
        'sanitized_message' => $this->mailSettingsResolver->sanitizedExceptionMessage($exception),
      ]);

      throw $exception;
    }
  }

  /**
   * @return array<string, string>
   */
  private function messageContext(): array
  {
    $diagnostics = $this->mailSettingsResolver->diagnostics();

    return [
      'CMS project' => $this->systemSettings->projectName() ?: config('app.name', 'WebBlocks CMS'),
      'Active mail mode' => (string) $diagnostics['active_mode'],
      'App URL' => (string) config('app.url', 'not configured'),
      'Timestamp' => now()->toIso8601String(),
    ];
  }

  private function recipientDomain(string $recipient): ?string
  {
    $parts = explode('@', $recipient, 2);

    return count($parts) === 2 && $parts[1] !== '' ? $parts[1] : null;
  }
}
