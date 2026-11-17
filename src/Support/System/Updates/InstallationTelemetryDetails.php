<?php

declare(strict_types=1);

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Facades\DB;
use Throwable;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\Install\InstallState;
use WebBlocks\Cms\Support\System\SystemSettings;

final class InstallationTelemetryDetails
{
  public function payload(): array
  {
    $details = [];
    try {
      $day = SystemSetting::query()->where('key', InstallState::FIRST_INSTALLATION_DAY)->value('value');
      if (is_string($day) && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $day)) {
        $details['installed_on'] = $day;
      }
    } catch (Throwable) {
      // A missing local setting never prevents a basic update check.
    }

    try {
      $locale = strtolower(str_replace('_', '-', app(SystemSettings::class)->defaultLocaleCode()));
      if (strlen($locale) <= 24 && preg_match('/\A[a-z]{2,3}(?:-[a-z0-9]{2,8}){0,3}\z/', $locale)) {
        $details['cms_locale'] = $locale;
      }
    } catch (Throwable) {
      // The default locale is optional metadata.
    }

    $country = config('webblocks-updates.telemetry.country_code');
    $country = is_string($country) ? strtoupper(trim($country)) : '';
    $details['country_code'] = preg_match('/\A[A-Z]{2}\z/', $country) ? $country : null;

    $family = strtolower(PHP_OS_FAMILY);
    if (in_array($family, ['linux', 'windows', 'darwin', 'bsd', 'solaris'], true)) {
      $details['os_family'] = $family;
    }

    return [...$details, ...($family === 'linux' ? $this->linuxDetails() : []), ...$this->databaseDetails()];
  }

  private function linuxDetails(): array
  {
    $details = [];
    foreach (['/etc/os-release', '/usr/lib/os-release'] as $path) {
      $contents = @file_get_contents($path, false, null, 0, 8192);
      if ($contents !== false) {
        break;
      }
    }
    if ($contents === false) {
      return $details;
    }

    foreach (['ID' => 'linux_distribution', 'VERSION_ID' => 'linux_version'] as $key => $field) {
      if (preg_match('/^'.$key.'=([\'"]?)([a-zA-Z0-9][a-zA-Z0-9._+~-]{0,23})\1\r?$/m', $contents, $matches)) {
        $value = $matches[2];
        if ($key === 'ID' && preg_match('/\A[a-z0-9][a-z0-9_-]*\z/', $value)) {
          $details[$field] = $value;
        } elseif ($key === 'VERSION_ID' && strlen($value) <= 20 && ctype_digit($value[0])) {
          $details[$field] = $value;
        }
      }
    }

    return isset($details['linux_distribution']) ? $details : [];
  }

  private function databaseDetails(): array
  {
    try {
      $connection = DB::connection();
      $engine = $connection->getDriverName();
      if (! in_array($engine, ['mysql', 'mariadb', 'pgsql', 'sqlite', 'sqlsrv'], true)) {
        return [];
      }
      if ($engine === 'mysql' && $connection->isMaria()) {
        $engine = 'mariadb';
      }
      $details = ['database_engine' => $engine];
      if (preg_match('/\A([1-9][0-9]{0,3})\b/', $connection->getServerVersion(), $matches)) {
        $details['database_major_version'] = (int) $matches[1];
      }

      return $details;
    } catch (Throwable) {
      return [];
    }
  }
}
