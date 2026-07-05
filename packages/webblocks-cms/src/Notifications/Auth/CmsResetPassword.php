<?php

namespace WebBlocks\Cms\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use WebBlocks\Cms\Support\Mail\CmsMailSettingsResolver;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

class CmsResetPassword extends ResetPassword
{
  public const RESET_ROUTE_NAME = 'webblocks.auth.password.reset';

  public function __construct(string $token, private readonly string $email)
  {
    parent::__construct($token);
  }

  protected function resetUrl($notifiable): string
  {
    return route(self::RESET_ROUTE_NAME, [
      'token' => $this->token,
      'email' => $this->email,
    ]);
  }

  public function toMail($notifiable): MailMessage
  {
    $locale = app(AdminLocaleResolver::class)->locale($notifiable);
    $translator = app(CmsTranslator::class);
    $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
    $message = (new MailMessage)
      ->subject($translator->admin('auth.reset_email_subject', $locale))
      ->line($translator->admin('auth.reset_email_line', $locale))
      ->action($translator->admin('auth.reset_email_action', $locale), $this->resetUrl($notifiable))
      ->line($translator->admin('auth.reset_email_expire', $locale, ['count' => $minutes]))
      ->line($translator->admin('auth.reset_email_no_action', $locale));

    return app(CmsMailSettingsResolver::class)
      ->applyToMailMessage($message);
  }
}
