<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Telemetry;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;
use WebBlocks\Cms\Support\Updates\Client\Contracts\TelemetryProvider;

/**
 * Real telemetry: an anonymous, stable installation id plus php/laravel versions,
 * sent with the update check. The
 * install id is persisted to a storage file instead of a DB table, so the engine
 * needs no migration. Opt-out via `telemetry.enabled` (default true since 1.0.3);
 * returns an empty payload when disabled.
 */
class FileInstallationTelemetry implements TelemetryProvider
{
  public function enabled(): bool
  {
    return filter_var(config('publisher-client.telemetry.enabled', true), FILTER_VALIDATE_BOOL);
  }

  public function installationId(): ?string
  {
    if (! $this->enabled()) {
      return null;
    }

    $path = $this->idPath();

    if (File::isFile($path)) {
      $existing = trim((string) File::get($path));

      if ($existing !== '') {
        return $existing;
      }
    }

    $id = (string) Str::uuid();

    try {
      File::ensureDirectoryExists(dirname($path));
      File::put($path, $id);
    } catch (Throwable) {
      return null;
    }

    return $id;
  }

  public function updateCheckPayload(string $product, string $installedVersion, string $channel): array
  {
    $installationId = $this->installationId();

    if ($installationId === null) {
      return [];
    }

    return [
      'product_key' => $product,
      'installed_version' => $installedVersion,
      'channel' => $channel,
      'installation_id' => $installationId,
      'php_version' => PHP_VERSION,
      'laravel_version' => Application::VERSION,
      'telemetry_schema_version' => (string) config('publisher-client.telemetry.schema_version', '1'),
    ];
  }

  private function idPath(): string
  {
    return storage_path(trim((string) config('publisher-client.apply.workspace_root', 'app/publisher-client'), '/').'/installation-id');
  }
}
