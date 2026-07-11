<?php

namespace WebBlocks\Cms\Notifications\System;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use WebBlocks\Cms\Support\Mail\CmsMailSettingsResolver;

class CmsTestEmail extends Notification
{
  /**
   * @param  array<string, string>  $context
   */
  public function __construct(
    private readonly array $context,
  ) {}

  /**
   * @return array<int, string>
   */
  public function via(object $notifiable): array
  {
    return ['mail'];
  }

  public function toMail(object $notifiable): MailMessage
  {
    $message = (new MailMessage)
      ->subject('WebBlocks CMS test email')
      ->line('This is a test email from WebBlocks CMS.');

    foreach ($this->context as $label => $value) {
      $message->line($label.': '.$value);
    }

    $message->line('Secrets are never included in this test email.');

    return app(CmsMailSettingsResolver::class)->applyToMailMessage($message);
  }
}
