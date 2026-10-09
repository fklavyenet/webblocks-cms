<?php

namespace WebBlocks\Cms\Services\SiteNotifications;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Throwable;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Mail\CmsMailConfigurationException;
use WebBlocks\Cms\Support\Mail\CmsMailSettingsResolver;
use WebBlocks\Cms\Support\System\SystemSettings;

class SiteNotificationMailer
{
  public function send(Mailable $mail, ?string $recipient, bool $alertOnly): string
  {
    if (! $recipient || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
      return 'not_configured';
    }
    try {
      $mailer = app(CmsMailSettingsResolver::class)->resolvedMailerName();
      $transport = config('mail.mailers.'.$mailer.'.transport', $mailer);
      if (! $mailer || in_array($transport, ['array', 'log', 'null', 'failover', 'roundrobin'], true)
        || ($transport === 'smtp' && (! config('mail.mailers.'.$mailer.'.host') || ! config('mail.mailers.'.$mailer.'.port')))) {
        return 'not_configured';
      }
      $mail->locale(app(LocaleResolver::class)->default()->code);
      $settings = app(SystemSettings::class)->cmsMailSettings();
      if ($settings['mode'] === SystemSettings::CMS_MAIL_MODE_CUSTOM) {
        $mail->from($settings['from_address'], $settings['from_name']);
        if (! $alertOnly && $settings['reply_to_address'] && $mail->replyTo === []) {
          $mail->replyTo($settings['reply_to_address']);
        }
      }
      if ($alertOnly) {
        $mail->withSymfonyMessage(static function (Email $message): void {
          if ($message->getAttachments() !== []) {
            throw new \RuntimeException('Alert attachments are prohibited.');
          }
          // Remove configured Reply-To and any custom metadata as well as
          // visitor-derived headers. Mailer defaults run before this callback.
          $allowed = ['from', 'to', 'subject', 'date', 'message-id', 'mime-version', 'content-type', 'content-transfer-encoding'];
          foreach ($message->getHeaders()->getNames() as $name) {
            if (! in_array(strtolower($name), $allowed, true)) {
              $message->getHeaders()->remove($name);
            }
          }
        });
      }
      $sent = Mail::mailer($mailer)->to($recipient)->send($mail);

      return $sent === null ? 'skipped' : 'sent';
    } catch (CmsMailConfigurationException) {
      return 'not_configured';
    } catch (Throwable) {
      // SMTP exceptions can contain credentials and message payloads.
      return 'failed';
    }
  }
}
