<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;
use WebBlocks\Cms\Models\Block;

class ContactMailDiagnoseCommand extends Command
{
  protected $signature = 'contact:mail-diagnose
    {--block= : Optional Contact Form block ID for recipient fallback inspection}
    {--send-test= : Optional recipient email for a controlled SMTP send test}';

  protected $description = 'Inspect Contact Form mail configuration without printing secrets.';

  public function handle(): int
  {
    $this->components->info('Contact mail configuration');

    foreach ($this->mailConfigRows() as $label => $value) {
      $this->line($label.': '.$this->displayValue($value));
    }

    $this->line('MAIL_TRANSPORT_STATUS: '.$this->mailTransportStatus());
    $this->line('Config cached: '.($this->laravel->configurationIsCached() ? 'yes' : 'no'));

    $blockId = $this->option('block');

    if ($blockId !== null && trim((string) $blockId) !== '') {
      $this->inspectBlock((string) $blockId);
    }

    $sendTest = trim((string) $this->option('send-test'));

    if ($sendTest === '') {
      $this->line('SMTP send test: skipped');

      return self::SUCCESS;
    }

    if (! filter_var($sendTest, FILTER_VALIDATE_EMAIL)) {
      $this->components->error('SMTP send test recipient is not a valid email address.');

      return self::FAILURE;
    }

    try {
      Mail::raw('WebBlocks CMS contact mail diagnostic test.', function ($message) use ($sendTest): void {
        $message->to($sendTest)->subject('WebBlocks CMS contact mail diagnostic');
      });
    } catch (Throwable $throwable) {
      $this->components->error('SMTP send test failed.');
      $this->line('Failure detail: '.$this->sanitizeFailureMessage($throwable->getMessage()));

      return self::FAILURE;
    }

    $this->components->info('SMTP send test succeeded.');

    return self::SUCCESS;
  }

  private function mailConfigRows(): array
  {
    return [
      'MAIL_MAILER' => config('mail.default'),
      'MAIL_HOST' => config('mail.mailers.smtp.host'),
      'MAIL_PORT' => config('mail.mailers.smtp.port'),
      'MAIL_SCHEME' => config('mail.mailers.smtp.scheme'),
      'MAIL_ENCRYPTION' => env('MAIL_ENCRYPTION'),
      'MAIL_USERNAME' => config('mail.mailers.smtp.username'),
      'MAIL_FROM_ADDRESS' => config('mail.from.address'),
      'CONTACT_RECIPIENT_EMAIL' => config('contact.recipient_email'),
    ];
  }

  private function inspectBlock(string $blockId): void
  {
    $block = Block::query()->with('page.site')->find($blockId);

    if (! $block) {
      $this->line('Contact Form block: not found');

      return;
    }

    $this->line('Contact Form block ID: #'.$block->id);
    $this->line('Block recipient_email: '.$this->displayValue($block->setting('recipient_email')));
    $this->line('Block send_email_notification: '.($block->setting('send_email_notification', true) ? 'true' : 'false'));
    $this->line('Site contact_recipient_email: '.$this->displayValue($block->page?->site?->contact_recipient_email));
  }

  private function mailTransportStatus(): string
  {
    $mailer = strtolower(trim((string) config('mail.default')));

    if ($mailer === '' || in_array($mailer, ['array', 'log', 'null'], true)) {
      return 'not configured for real outbound delivery';
    }

    if ($mailer === 'smtp') {
      $host = trim((string) config('mail.mailers.smtp.host'));
      $port = trim((string) config('mail.mailers.smtp.port'));

      if ($host === '' || $port === '') {
        return 'smtp incomplete';
      }
    }

    return 'real outbound transport configured';
  }

  private function displayValue(mixed $value): string
  {
    $value = trim((string) $value);

    return $value === '' ? '-' : $value;
  }

  private function sanitizeFailureMessage(string $message): string
  {
    $message = trim($message);

    if ($message === '') {
      return 'SMTP send failed.';
    }

    $message = preg_replace('/([?&](?:password|passwd|pwd|token|api[_-]?key|secret)=)[^&\s]+/i', '$1[redacted]', $message) ?? $message;
    $message = preg_replace('/\b(password|passwd|pwd|token|api[_-]?key|secret)=\S+/i', '$1=[redacted]', $message) ?? $message;
    $message = preg_replace('/\b[A-Za-z0-9+\/=_-]{24,}\b/', '[redacted]', $message) ?? $message;

    return mb_strimwidth($message, 0, 240, '...');
  }
}
