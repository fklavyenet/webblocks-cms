<?php

namespace WebBlocks\Cms\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;

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
}
