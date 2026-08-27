<?php

namespace WebBlocks\Cms\Support\Install;

use Illuminate\Support\Str;

final class InitialSiteNameResolver
{
  public function resolve(?string $explicitName = null): string
  {
    $explicitName = trim((string) $explicitName);

    if ($explicitName !== '') {
      return $explicitName;
    }

    $configuredName = trim((string) config('webblocks-cms.defaults.site_name'));

    if ($configuredName !== '') {
      return $configuredName;
    }

    return $this->nameFromAppUrl() ?? 'Default Site';
  }

  private function nameFromAppUrl(): ?string
  {
    $host = parse_url((string) config('app.url'), PHP_URL_HOST);

    if (! is_string($host) || $host === '') {
      return null;
    }

    $host = Str::lower(rtrim($host, '.'));

    if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
      return null;
    }

    return Str::startsWith($host, 'www.') ? Str::after($host, 'www.') : $host;
  }
}
