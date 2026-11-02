<?php

declare(strict_types=1);

namespace WebBlocks\Cms\Support\System\Updates;

use WebBlocks\Cms\Support\Updates\Client\Contracts\TelemetryProvider;
use WebBlocks\Cms\Support\Updates\Client\Telemetry\FileInstallationTelemetry;

/** Use the same production identity for CMS panel and embedded Client checks. */
final class InstallationTelemetry implements TelemetryProvider
{
  // Historical UUID setting is preserved; it is not a production identity.
  public const SETTING_KEY = 'telemetry.installation_id';

  public function enabled(): bool
  {
    return filter_var(config('webblocks-updates.telemetry.enabled', true), FILTER_VALIDATE_BOOL)
      && app(FileInstallationTelemetry::class)->enabled();
  }

  public function installationId(): ?string
  {
    return $this->enabled()
      ? app(FileInstallationTelemetry::class)->installationId('webblocks-cms')
      : null;
  }

  public function updateCheckPayload(string $productKey, string $installedVersion, string $channel): array
  {
    return $this->enabled()
      ? app(FileInstallationTelemetry::class)->updateCheckPayload($productKey, $installedVersion, $channel)
      : [];
  }
}
