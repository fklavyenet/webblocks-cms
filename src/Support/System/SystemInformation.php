<?php

namespace WebBlocks\Cms\Support\System;

use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\WebBlocks;

class SystemInformation
{
  public function __construct(
    private readonly AdminLocaleResolver $adminLocaleResolver,
  ) {}

  public function rows(): array
  {
    return [
      'cms_version' => WebBlocks::VERSION,
      'php_version' => PHP_VERSION,
      'laravel_version' => app()->version(),
      'environment' => app()->environment(),
      'debug_mode' => (bool) config('app.debug', false),
      'database_driver' => (string) config('database.default', '—'),
      'default_locale' => (string) config('app.locale', 'en'),
      'admin_locale' => $this->adminLocaleResolver->locale(),
      'timezone' => (string) config('app.timezone', 'UTC'),
    ];
  }
}
