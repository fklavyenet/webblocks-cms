<?php

namespace WebBlocks\Cms\Support\Translations;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;
use WebBlocks\Cms\Support\System\SystemSettings;

class AdminLocaleResolver
{
  // Baseline used by shipped mail, plugin and provider contracts.
  public const SUPPORTED_LOCALES = ['en', 'de', 'tr', 'es', 'it', 'fr'];

  public const PACKAGED_OPTIONS = [
    'en' => 'EN - English',
    'de' => 'DE - Deutsch',
    'tr' => 'TR - Türkçe',
    'es' => 'ES - Español',
    'it' => 'IT - Italiano',
    'fr' => 'FR - Français',
  ];

  public function __construct(
    private readonly SystemSettings $systemSettings,
  ) {}

  public function locale(?Authenticatable $user = null): string
  {
    $options = $this->options();

    return $this->userLocale($user ?? request()?->user(), $options) ?? $this->configuredLocale($options);
  }

  public function defaultLocale(): string
  {
    return $this->configuredLocale($this->options());
  }

  public function packagedLocale(?Authenticatable $user = null): string
  {
    return $this->userLocale($user, self::PACKAGED_OPTIONS) ?? $this->configuredLocale(self::PACKAGED_OPTIONS);
  }

  public function options(): array
  {
    return app(PanelLanguages::class)->options();
  }

  private function configuredLocale(array $options): string
  {
    $configured = Locale::normalizeCode((string) $this->systemSettings->get(SystemSettings::ADMIN_LOCALE, ''));
    $appLocale = Locale::normalizeCode((string) config('app.locale', 'en'));

    return isset($options[$configured]) ? $configured : (isset($options[$appLocale]) ? $appLocale : 'en');
  }

  public function userPreferencesAvailable(): bool
  {
    try {
      $connection = DB::connection();

      return app(RequestMemo::class)->remember('admin.locale-schema', [
        $connection->getName(), $connection->getDatabaseName(), $connection->getTablePrefix(),
      ], fn () => $connection->getSchemaBuilder()->hasTable('users') && $connection->getSchemaBuilder()->hasColumn('users', 'admin_locale'));
    } catch (\Throwable) {
      return false;
    }
  }

  private function userLocale(?Authenticatable $user, array $options): ?string
  {
    if (! $user || ! $this->userPreferencesAvailable()) {
      return null;
    }

    $locale = Locale::normalizeCode((string) ($user->getAuthIdentifier() ? $user->getAttribute('admin_locale') : ''));

    return isset($options[$locale]) ? $locale : null;
  }
}
