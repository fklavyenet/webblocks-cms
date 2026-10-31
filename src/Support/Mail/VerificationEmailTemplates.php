<?php

namespace WebBlocks\Cms\Support\Mail;

use Illuminate\Database\QueryException;
use Illuminate\Notifications\Messages\MailMessage;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\Auth\EmailTwoFactorCodes;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;

final class VerificationEmailTemplates
{
  public const FIELDS = ['subject', 'greeting', 'intro', 'help', 'salutation'];

  public const TOKENS = ['{code}', '{minutes}', '{app_name}'];

  public static function key(string $locale, string $field): string
  {
    return 'system.verification_email.'.$locale.'.'.$field;
  }

  public function defaults(string $locale): array
  {
    $locale = $this->locale($locale);

    return array_combine(self::FIELDS, array_map(
      fn (string $field): string => trans('webblocks-cms::two-factor.mail_'.$field, [], $locale),
      self::FIELDS,
    ));
  }

  public function templates(string $locale): array
  {
    $locale = $this->locale($locale);
    $values = $this->defaults($locale);

    try {
      $stored = SystemSetting::query()->whereIn('key', array_map(
        fn (string $field): string => self::key($locale, $field), self::FIELDS,
      ))->pluck('value', 'key');
    } catch (QueryException) {
      // Packaged defaults also work before the settings schema is installed.
      return $values;
    }

    foreach (self::FIELDS as $field) {
      $value = $stored[self::key($locale, $field)] ?? null;
      if (is_string($value) && trim($value) !== '') {
        $values[$field] = $value;
      }
    }

    return $values;
  }

  public function message(#[\SensitiveParameter] string $code, string $locale, ?array $templates = null): MailMessage
  {
    $templates ??= $this->templates($locale);
    $copy = [];
    foreach (self::FIELDS as $field) {
      $copy[$field] = strtr($templates[$field], [
        '{code}' => $code,
        '{minutes}' => (string) EmailTwoFactorCodes::LIFETIME_MINUTES,
        '{app_name}' => (string) config('app.name'),
      ]);
    }

    return (new MailMessage)
      ->subject(preg_replace('/[\r\n]+/', ' ', $copy['subject']))
      ->markdown('webblocks-cms::emails.two-factor', [
        'copy' => $copy,
        'code' => $code,
        'verificationEmailCss' => file_get_contents(dirname(__DIR__, 3).'/public/cms/css/email.css'),
      ])
      ->theme('webblocks-cms::emails.themes.verification');
  }

  private function locale(string $locale): string
  {
    return in_array($locale, AdminLocaleResolver::SUPPORTED_LOCALES, true) ? $locale : 'en';
  }
}
