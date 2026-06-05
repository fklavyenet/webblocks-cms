<?php

namespace WebBlocks\Cms\Support\Mail;

use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;
use WebBlocks\Cms\Support\System\SystemSettings;

class CmsMailSettingsResolver
{
  public const MAILER_NAME = 'webblocks_cms';

  public const SUPPORTED_MAILERS = [
    'smtp',
    'sendmail',
    'log',
    'array',
  ];

  public function __construct(
    private readonly SystemSettings $systemSettings,
    private readonly MailFactory $mailFactory,
  ) {}

  public function applyToMailMessage(MailMessage $message): MailMessage
  {
    $message->mailer($this->resolvedMailerName());

    $settings = $this->systemSettings->cmsMailSettings();

    if ($settings['mode'] === SystemSettings::CMS_MAIL_MODE_CUSTOM && $settings['from_address']) {
      $message->from($settings['from_address'], $settings['from_name']);
    }

    if ($settings['mode'] === SystemSettings::CMS_MAIL_MODE_CUSTOM && $settings['reply_to_address']) {
      $message->replyTo($settings['reply_to_address']);
    }

    return $message;
  }

  public function resolvedMailerName(): string
  {
    $settings = $this->systemSettings->cmsMailSettings();

    if ($settings['mode'] !== SystemSettings::CMS_MAIL_MODE_CUSTOM) {
      return (string) config('mail.default', 'log');
    }

    $this->assertCustomSettingsAreComplete($settings);
    $this->configureCustomMailer($settings);

    return self::MAILER_NAME;
  }

  public function diagnostics(): array
  {
    $settings = $this->systemSettings->cmsMailSettings();
    $custom = $settings['mode'] === SystemSettings::CMS_MAIL_MODE_CUSTOM;

    return [
      'active_mode' => $custom ? 'CMS custom settings' : 'Environment config',
      'mailer' => $custom ? $settings['mailer'] : (string) config('mail.default', 'log'),
      'host' => $custom ? ($settings['host'] ?? '') : (string) config('mail.mailers.'.config('mail.default').'.host', ''),
      'port' => $custom ? ($settings['port'] ?? '') : (string) config('mail.mailers.'.config('mail.default').'.port', ''),
      'encryption' => $custom ? ($settings['encryption'] ?? '') : (string) (config('mail.mailers.'.config('mail.default').'.scheme') ?? config('mail.mailers.'.config('mail.default').'.encryption', '')),
      'username_configured' => $custom ? $settings['username'] !== null : filled(config('mail.mailers.'.config('mail.default').'.username')),
      'password_configured' => $custom ? $this->systemSettings->cmsMailPasswordConfigured() : filled(config('mail.mailers.'.config('mail.default').'.password')),
      'from_address' => $custom ? ($settings['from_address'] ?? '') : (string) config('mail.from.address', ''),
      'from_name' => $custom ? ($settings['from_name'] ?? '') : (string) config('mail.from.name', ''),
      'config_cached' => app()->configurationIsCached(),
      'environment' => app()->environment(),
      'ready' => $custom ? $this->customSettingsAreComplete($settings) : true,
    ];
  }

  public function customSettingsAreComplete(?array $settings = null): bool
  {
    try {
      $this->assertCustomSettingsAreComplete($settings ?? $this->systemSettings->cmsMailSettings());

      return true;
    } catch (CmsMailConfigurationException) {
      return false;
    }
  }

  private function assertCustomSettingsAreComplete(array $settings): void
  {
    $mailer = (string) ($settings['mailer'] ?? '');

    if (! in_array($mailer, self::SUPPORTED_MAILERS, true)) {
      throw new CmsMailConfigurationException('CMS mail settings use an unsupported mailer.');
    }

    if (! filled($settings['from_address'] ?? null)) {
      throw new CmsMailConfigurationException('CMS mail settings require a from address before CMS mail can be sent.');
    }

    if ($mailer === 'smtp') {
      foreach (['host', 'port'] as $requiredKey) {
        if (! filled($settings[$requiredKey] ?? null)) {
          throw new CmsMailConfigurationException('CMS SMTP mail settings require host and port before CMS mail can be sent.');
        }
      }
    }
  }

  private function configureCustomMailer(array $settings): void
  {
    $mailer = (string) $settings['mailer'];
    $config = match ($mailer) {
      'smtp' => [
        'transport' => 'smtp',
        'scheme' => $settings['encryption'] ?: null,
        'encryption' => $settings['encryption'] ?: null,
        'host' => $settings['host'],
        'port' => (int) $settings['port'],
        'username' => $settings['username'],
        'password' => $settings['password'] !== '' ? $settings['password'] : null,
        'timeout' => filled($settings['timeout']) ? (int) $settings['timeout'] : null,
        'local_domain' => parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST),
      ],
      'sendmail' => [
        'transport' => 'sendmail',
        'path' => (string) config('mail.mailers.sendmail.path', '/usr/sbin/sendmail -bs -i'),
      ],
      'log' => [
        'transport' => 'log',
        'channel' => config('mail.mailers.log.channel'),
      ],
      'array' => [
        'transport' => 'array',
      ],
    };

    Config::set('mail.mailers.'.self::MAILER_NAME, array_filter($config, fn (mixed $value): bool => $value !== null));

    if (method_exists($this->mailFactory, 'purge')) {
      $this->mailFactory->purge(self::MAILER_NAME);
    }
  }
}
