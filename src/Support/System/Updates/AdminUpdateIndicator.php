<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Facades\Cache;
use Throwable;
use WebBlocks\Cms\Support\System\InstalledVersionStore;

class AdminUpdateIndicator
{
  public const CACHE_KEY = 'webblocks-cms:admin-update-indicator';

  public function __construct(
    private readonly UpdateServerClient $updateServerClient,
    private readonly InstalledVersionStore $installedVersionStore,
  ) {}

  public function payload(bool $refresh = false): array
  {
    if ($refresh) {
      $this->clear();
    }

    $status = $refresh ? null : Cache::get(self::CACHE_KEY);

    // Never advertise a version that is already installed. The badge is cached
    // for an hour, and while the update controller does clear it on a successful
    // run, a request served between the apply and the worker recycling still
    // holds the pre-update code — it re-checks, still sees itself as the old
    // version, and re-caches the finished update for another hour. Recompute
    // instead of showing a badge for the release you are running.
    if (is_array($status) && $this->advertisesInstalledVersion($status)) {
      $this->clear();
      $status = null;
    }

    if (! is_array($status)) {
      $status = $this->safeStatus();
      $this->storeVersionStatus($status);
    }

    return $this->payloadFromStatus(is_array($status) ? $status : []);
  }

  public function storeVersionStatus(array $status): void
  {
    Cache::put(self::CACHE_KEY, $status, now()->addSeconds($this->ttlSecondsForStatus($status)));
  }

  public function clear(): void
  {
    Cache::forget(self::CACHE_KEY);
  }

  /**
   * True when a cached status announces an update to a version that is not
   * actually newer than what is installed right now. Uses the same lenient
   * normalization as the update check ("v1.2.3" == "1.2.3").
   */
  private function advertisesInstalledVersion(array $status): bool
  {
    if (($status['state'] ?? null) !== 'update_available') {
      return false;
    }

    $latestVersion = $status['latest_version'] ?? null;

    if (! is_string($latestVersion) || $latestVersion === '') {
      return false;
    }

    try {
      $installedVersion = $this->installedVersionStore->currentVersion();
    } catch (Throwable) {
      return false;
    }

    if ($installedVersion === '') {
      return false;
    }

    return version_compare(
      $this->normalizeVersion($latestVersion),
      $this->normalizeVersion($installedVersion),
      '<=',
    );
  }

  private function normalizeVersion(string $version): string
  {
    $version = trim($version);

    if ($version !== '' && ($version[0] === 'v' || $version[0] === 'V')) {
      $version = substr($version, 1);
    }

    return $version;
  }

  private function safeStatus(): array
  {
    try {
      return $this->updateServerClient->check()->toArray();
    } catch (Throwable) {
      return [
        'state' => 'indicator_unavailable',
        'label' => 'Update status unavailable',
        'latest_version' => null,
        'update_available' => false,
      ];
    }
  }

  private function payloadFromStatus(array $status): array
  {
    $latestVersion = $status['latest_version'] ?? null;
    $updateAvailable = ($status['state'] ?? null) === 'update_available'
      && ($status['update_available'] ?? false) === true
      && is_string($latestVersion)
      && $latestVersion !== '';

    return [
      'visible' => $updateAvailable,
      'state' => (string) ($status['state'] ?? 'unknown'),
      'label' => $updateAvailable ? 'Update '.$latestVersion.' available' : (string) ($status['label'] ?? 'No update available'),
      'latest_version' => is_string($latestVersion) ? $latestVersion : null,
      'checked_at' => $status['checked_at'] ?? null,
    ];
  }

  private function ttlSeconds(): int
  {
    return max(60, (int) config('webblocks-updates.indicator_cache_ttl_seconds', 3600));
  }

  private function inactiveTtlSeconds(): int
  {
    return max(30, (int) config('webblocks-updates.indicator_inactive_cache_ttl_seconds', 60));
  }

  private function ttlSecondsForStatus(array $status): int
  {
    $latestVersion = $status['latest_version'] ?? null;

    if (($status['state'] ?? null) === 'update_available'
      && ($status['update_available'] ?? false) === true
      && is_string($latestVersion)
      && $latestVersion !== '') {
      return $this->ttlSeconds();
    }

    return $this->inactiveTtlSeconds();
  }
}
