<?php

namespace WebBlocks\Cms\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use WebBlocks\Cms\Notifications\System\TwoFactorEmail;
use WebBlocks\Cms\Support\Auth\Module\EmailCodes;
use WebBlocks\Cms\Support\Mail\CmsMailSettingsResolver;

final class EmailTwoFactorCodes
{
  public const LIFETIME_MINUTES = EmailCodes::LIFETIME_MINUTES;

  public const SESSION = 'wbcms.two_factor.email_codes';

  public function __construct(private readonly EmailCodes $codes) {}

  public function send(Request $request, Authenticatable $user, string $purpose, string $email, string $binding): string
  {
    return $this->codes->send($request, $user, $purpose, $email, $binding, $this->deliver(...));
  }

  private function deliver(string $email, #[\SensitiveParameter] string $code): bool
  {
    try {
      $mailer = app(CmsMailSettingsResolver::class)->resolvedMailerName();
      $this->assertDeliverable($mailer);
      Notification::route('mail', $email)->notify((new TwoFactorEmail($code))->locale(app()->getLocale()));
    } catch (\Throwable $exception) {
      // SMTP exceptions can contain the entire message, including its verification code.
      Log::warning('CMS verification email could not be sent.', ['exception_class' => $exception::class]);

      return false;
    }

    return true;
  }

  public function assertDeliverable(string $mailer, array $visited = []): void
  {
    $transport = config('mail.mailers.'.$mailer.'.transport');
    if (in_array($mailer, $visited, true) || ! is_string($transport) || in_array($transport, ['log', 'array'], true)) {
      throw new \RuntimeException('Verification email requires a delivery transport.');
    }
    if (in_array($transport, ['failover', 'roundrobin'], true)) {
      foreach ((array) config('mail.mailers.'.$mailer.'.mailers', []) as $nested) {
        $this->assertDeliverable($nested, [...$visited, $mailer]);
      }
    }
  }
}
