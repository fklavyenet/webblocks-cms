<?php

namespace WebBlocks\Cms\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use WebBlocks\Cms\Support\Mail\CmsMailSettingsResolver;

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
    return app(CmsMailSettingsResolver::class)
      ->applyToMailMessage($this->buildMailMessage($this->resetUrl($notifiable)));
  }
}
