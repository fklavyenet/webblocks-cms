<?php

namespace WebBlocks\Cms\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use WebBlocks\Cms\Support\Mail\CmsMailSettingsResolver;

class CmsResetPassword extends ResetPassword
{
  public function __construct(string $token, private readonly string $email)
  {
    parent::__construct($token);
  }

  protected function resetUrl($notifiable): string
  {
    return route('webblocks.auth.password.reset', [
      'token' => $this->token,
      'email' => $this->email,
    ]);
  }

  public function toMail($notifiable): MailMessage
  {
    return app(CmsMailSettingsResolver::class)->applyToMailMessage(parent::toMail($notifiable));
  }
}
