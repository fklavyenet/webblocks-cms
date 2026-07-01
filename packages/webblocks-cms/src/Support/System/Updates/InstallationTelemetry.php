<?php

declare(strict_types=1);

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;
use WebBlocks\Cms\Models\SystemSetting;

final class InstallationTelemetry
{
  public const SETTING_KEY = 'telemetry.installation_id';

  public function enabled(): bool
  {
    return filter_var(config('webblocks-updates.telemetry.enabled', true), FILTER_VALIDATE_BOOL);
  }

  public function installationId(): ?string
  {
    if (! $this->enabled() || ! $this->settingsTableExists()) {
      return null;
    }

    try {
      $setting = SystemSetting::query()->firstOrCreate(
        ['key' => self::SETTING_KEY],
        ['value' => (string) Str::uuid()],
      );
    } catch (Throwable) {
      return null;
    }

    $installationId = trim((string) $setting->value);

    if ($installationId !== '') {
      return $installationId;
    }

    $installationId = (string) Str::uuid();

    try {
      $setting->forceFill(['value' => $installationId])->save();
    } catch (Throwable) {
      return null;
    }

    return $installationId;
  }

  public function updateCheckPayload(string $productKey, string $installedVersion, string $channel): array
  {
    $installationId = $this->installationId();

    if ($installationId === null) {
      return [];
    }

    return [
      'product_key' => $productKey,
      'installed_version' => $installedVersion,
      'channel' => $channel,
      'installation_id' => $installationId,
      'telemetry_schema_version' => (string) config('webblocks-updates.telemetry.schema_version', '1'),
    ];
  }

  private function settingsTableExists(): bool
  {
    try {
      return Schema::hasTable('system_settings');
    } catch (Throwable) {
      return false;
    }
  }
}
