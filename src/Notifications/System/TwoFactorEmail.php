<?php

namespace WebBlocks\Cms\Notifications\System;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use WebBlocks\Cms\Support\Auth\EmailTwoFactorCodes;
use WebBlocks\Cms\Support\Mail\CmsMailSettingsResolver;

final class TwoFactorEmail extends Notification
{
  // Sent synchronously; never queued or persisted with its plaintext code.
  public function __construct(#[\SensitiveParameter] public readonly string $code) {}

  public function via(object $notifiable): array
  {
    return ['mail'];
  }

  public function toMail(object $notifiable): MailMessage
  {
    $message = app(CmsMailSettingsResolver::class)->applyToMailMessage((new MailMessage)
      ->subject(__('webblocks-cms::two-factor.mail_subject'))
      ->line(__('webblocks-cms::two-factor.mail_code', ['code' => $this->code]))
      ->line(__('webblocks-cms::two-factor.mail_help')));
    app(EmailTwoFactorCodes::class)->assertDeliverable($message->mailer);

    return $message;
  }
}
