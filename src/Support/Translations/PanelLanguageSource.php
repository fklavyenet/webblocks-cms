<?php

namespace WebBlocks\Cms\Support\Translations;

use Illuminate\Support\Arr;
use WebBlocks\Cms\Support\WebBlocks;

class PanelLanguageSource
{
  private const EMAIL_KEYS = [
    'notifications.new_body', 'notifications.summary_body', 'notifications.open_inbox', 'notifications.login_required',
  ];

  private const GROUPS = [
    'admin', 'acl', 'automatic-pages', 'backup-retention', 'block-settings', 'diagnostics',
    'dynamic-authoring', 'jobs', 'listing_views', 'maintenance_reports', 'modal-submit', 'notes',
    'notifications', 'page-locale-restore', 'recovery', 'retention-table', 'scheduler', 'seo_workspace',
    'storage', 'system_health', 'table_sort', 'two-factor', 'update_recovery', 'visitor_insights', 'panel-languages',
  ];

  public function isPanelGroup(string $key): bool
  {
    return in_array(strstr($key, '.', true), self::GROUPS, true);
  }

  public function messages(): array
  {
    $messages = [];

    foreach (self::GROUPS as $group) {
      $lines = Arr::dot(require $this->directory().'/en/'.$group.'.php', $group.'.');

      foreach ($lines as $key => $value) {
        if (is_string($value) && strlen($key) <= 190 && $this->plainText($value)
          && ! str_starts_with($key, 'admin.auth.reset_email_')
          && ! str_starts_with($key, 'two-factor.mail_') && ! in_array($key, self::EMAIL_KEYS, true)) {
          $messages[$key] = $value;
        }
      }
    }

    return $messages;
  }

  public function template(): array
  {
    $messages = $this->messages();

    return [
      'format_version' => 1,
      'source_version' => WebBlocks::VERSION,
      'code' => '',
      'name' => '',
      'translations' => array_map(fn ($key, $text) => ['key' => $key, 'text' => $text], array_keys($messages), $messages),
    ];
  }

  public function packagedOptions(): array
  {
    $options = AdminLocaleResolver::PACKAGED_OPTIONS;

    foreach (glob($this->directory().'/*/admin.php') ?: [] as $file) {
      $code = basename(dirname($file));
      if (! isset($options[$code]) && $this->validCode($code)) {
        $options[$code] = strtoupper($code);
      }
    }

    return $options;
  }

  public function validCode(string $code): bool
  {
    return strlen($code) <= 12 && preg_match('/\A[a-z]{2,3}(?:-[a-z0-9]{2,8}){0,2}\z/D', $code) === 1;
  }

  public function plainText(string $value): bool
  {
    return preg_match('/[<>\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u', $value) === 0;
  }

  public function placeholders(string $value): array
  {
    preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $value, $matches);
    $placeholders = array_unique($matches[0]);
    sort($placeholders);

    return $placeholders;
  }

  private function directory(): string
  {
    return dirname(__DIR__, 3).'/resources/lang';
  }
}
