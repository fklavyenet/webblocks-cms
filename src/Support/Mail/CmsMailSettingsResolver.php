<?php

namespace WebBlocks\Cms\Support\Mail;

use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
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

    $settings = $this->normalizedCustomSettings($settings);
    $this->assertCustomSettingsAreComplete($settings);
    $this->configureCustomMailer($settings);

    return self::MAILER_NAME;
  }

  public function diagnostics(): array
  {
    $settings = $this->systemSettings->cmsMailSettings();
    $custom = $settings['mode'] === SystemSettings::CMS_MAIL_MODE_CUSTOM;

    $details = $custom ? $this->customSummary($settings) : $this->environmentSummary();
    $ready = ! $custom || $this->customSettingsAreComplete($settings);

    return $details + [
      'active_mode' => $custom ? 'CMS custom settings' : 'Environment config',
      'mode' => $settings['mode'],
      'config_cached' => app()->configurationIsCached(),
      'environment' => app()->environment(),
      'ready' => $ready,
      'status' => $ready ? 'Ready' : 'Incomplete or invalid custom settings',
    ];
  }

  public function environmentSummary(): array
  {
    $mailer = (string) config('mail.default', 'log');
    $settings = (array) (config('mail.driver') ? config('mail') : config('mail.mailers.'.$mailer));
    if (isset($settings['url'])) {
      try {
        $settings = (new ConfigurationUrlParser)->parseConfiguration($settings);
      } catch (\InvalidArgumentException) {
        // Keep the configured fallback fields available for diagnosis.
      }
    }
    $settings['from_address'] = data_get($settings, 'from.address', config('mail.from.address'));
    $settings['from_name'] = data_get($settings, 'from.name', config('mail.from.name'));
    $settings['reply_to_address'] = data_get($settings, 'reply_to.address', config('mail.reply_to.address'));

    $transport = $settings['driver'] ?? $settings['transport'] ?? $mailer;

    return $this->summary($settings, $mailer, in_array($transport, ['smtp', 'smtps'], true));
  }

  public function customSummary(array $settings): array
  {
    $settings = $this->normalizedCustomSettings($settings);

    return $this->summary($settings, $settings['mailer'], $settings['mailer'] === 'smtp');
  }

  private function summary(array $settings, string $mailer, bool $smtp): array
  {
    return [
      'mailer' => $mailer,
      'smtp' => $smtp,
      'host' => $settings['host'] ?? '',
      'port' => $settings['port'] ?? '',
      'encryption' => $settings['scheme'] ?? $settings['encryption'] ?? '',
      'timeout' => $settings['timeout'] ?? '',
      'username_configured' => filled($settings['username'] ?? null),
      'password_configured' => filled($settings['password'] ?? null),
      'from_address' => $settings['from_address'] ?? '',
      'from_name' => $settings['from_name'] ?? '',
      'reply_to_address' => $settings['reply_to_address'] ?? '',
    ];
  }

  public function logContext(): array
  {
    $diagnostics = $this->diagnostics();
    $custom = $diagnostics['mode'] === SystemSettings::CMS_MAIL_MODE_CUSTOM;

    return [
      'cms_mail_mode' => $diagnostics['mode'],
      'mailer' => $custom ? self::MAILER_NAME : $diagnostics['mailer'],
      'transport' => $custom ? $diagnostics['mailer'] : null,
      'host' => $diagnostics['host'] ?: null,
      'port' => $diagnostics['port'] ?: null,
      'encryption' => $diagnostics['encryption'] ?: null,
      'username_configured' => $diagnostics['username_configured'],
      'password_configured' => $diagnostics['password_configured'],
      'from_address' => $diagnostics['from_address'] ?: null,
      'config_cached' => $diagnostics['config_cached'],
    ];
  }

  public function sanitizedExceptionMessage(\Throwable $exception): string
  {
    $message = $exception->getMessage();
    $settings = $this->systemSettings->cmsMailSettings();

    foreach ([
      $settings['password'] ?? null,
      $settings['username'] ?? null,
      $settings['from_address'] ?? null,
      $settings['reply_to_address'] ?? null,
    ] as $secretLikeValue) {
      if (filled($secretLikeValue)) {
        $message = str_replace((string) $secretLikeValue, '[redacted]', $message);
      }
    }

    $message = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted-email]', $message) ?? $message;
    $message = preg_replace('/(password|secret|token)=([^\\s&]+)/i', '$1=[redacted]', $message) ?? $message;

    return Str::limit($message, 500, '');
  }

  public function customSettingsAreComplete(?array $settings = null): bool
  {
    try {
      $this->assertCustomSettingsAreComplete($this->normalizedCustomSettings($settings ?? $this->systemSettings->cmsMailSettings()));

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

    if (! filter_var($settings['from_address'], FILTER_VALIDATE_EMAIL)) {
      throw new CmsMailConfigurationException('CMS mail settings require a valid from address before CMS mail can be sent.');
    }

    if ($mailer === 'smtp') {
      foreach (['host', 'port'] as $requiredKey) {
        if (! filled($settings[$requiredKey] ?? null)) {
          throw new CmsMailConfigurationException('CMS SMTP mail settings require host and port before CMS mail can be sent.');
        }
      }

      if (! is_int($settings['port']) || $settings['port'] < 1 || $settings['port'] > 65535) {
        throw new CmsMailConfigurationException('CMS SMTP mail settings require a valid port before CMS mail can be sent.');
      }

      if (! in_array($settings['encryption'], [null, 'tls', 'ssl'], true)) {
        throw new CmsMailConfigurationException('CMS SMTP mail settings use an unsupported encryption value.');
      }

      if (! filled($settings['username'] ?? null) || ! filled($settings['password'] ?? null)) {
        throw new CmsMailConfigurationException('CMS SMTP mail settings require username and password before CMS mail can be sent.');
      }
    }
  }

  private function normalizedCustomSettings(array $settings): array
  {
    $encryption = strtolower(trim((string) ($settings['encryption'] ?? '')));
    $encryption = in_array($encryption, ['', 'none', 'null'], true) ? null : $encryption;
    $port = filled($settings['port'] ?? null) && filter_var($settings['port'], FILTER_VALIDATE_INT) !== false
      ? (int) $settings['port']
      : $settings['port'];

    return [
      'mode' => SystemSettings::CMS_MAIL_MODE_CUSTOM,
      'mailer' => trim((string) ($settings['mailer'] ?? 'smtp')) ?: 'smtp',
      'host' => filled($settings['host'] ?? null) ? trim((string) $settings['host']) : null,
      'port' => $port,
      'encryption' => $encryption,
      'username' => filled($settings['username'] ?? null) ? trim((string) $settings['username']) : null,
      'password' => filled($settings['password'] ?? null) ? (string) $settings['password'] : null,
      'from_address' => filled($settings['from_address'] ?? null) ? trim((string) $settings['from_address']) : null,
      'from_name' => filled($settings['from_name'] ?? null) ? trim((string) $settings['from_name']) : null,
      'reply_to_address' => filled($settings['reply_to_address'] ?? null) ? trim((string) $settings['reply_to_address']) : null,
      'timeout' => filled($settings['timeout'] ?? null) && filter_var($settings['timeout'], FILTER_VALIDATE_INT) !== false ? (int) $settings['timeout'] : null,
    ];
  }

  private function configureCustomMailer(array $settings): void
  {
    $mailer = (string) $settings['mailer'];
    $config = match ($mailer) {
      'smtp' => [
        'transport' => 'smtp',
        'encryption' => $settings['encryption'] ?: null,
        'host' => $settings['host'],
        'port' => $settings['port'],
        'username' => $settings['username'],
        'password' => $settings['password'],
        'timeout' => $settings['timeout'],
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
