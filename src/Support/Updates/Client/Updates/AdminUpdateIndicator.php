<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Updates;

use Illuminate\Support\Facades\Cache;
use Throwable;
use WebBlocks\Cms\Support\Updates\Client\Support\Version\VersionResolver;

/**
 * Cached "update available" badge for the admin navbar (§7.7). Ported from the
 * The cache key is product-scoped and the TTLs
 * come from `publisher-client.admin.*`. The default admin scaffolding renders
 * `payload()['visible']` as the nav badge; a per-product view themes it.
 */
class AdminUpdateIndicator
{
  public function __construct(
    private readonly UpdateServerClient $client,
    private readonly VersionResolver $versions,
  ) {
  }

  public function payload(bool $refresh = false): array
  {
    if ($refresh) {
      $this->clear();
    }

    $status = $refresh ? null : Cache::get($this->cacheKey());

    // Never advertise a version we already run. A cached "update available"
    // outlives the update that applied it whenever the product's post-apply
    // commands do not clear the cache, or when a worker still holding the
    // pre-update code re-caches the old answer right after the apply — and the
    // entry lives for an hour. Recompute rather than show a stale badge.
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
    Cache::put($this->cacheKey(), $status, now()->addSeconds($this->ttlSecondsForStatus($status)));
  }

  public function clear(): void
  {
    Cache::forget($this->cacheKey());
  }

  public function cacheKey(): string
  {
    return 'publisher-client:update-indicator:'.(string) config('publisher-client.product', 'default');
  }

  /**
   * True when a cached status announces an update to a version that is not
   * actually newer than what is installed right now. Uses the same lenient
   * normalization as the check ("v1.2.3" == "1.2.3").
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
      $installedVersion = $this->versions->current();
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
      return $this->client->check()->toArray();
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

  private function ttlSecondsForStatus(array $status): int
  {
    $latestVersion = $status['latest_version'] ?? null;

    if (($status['state'] ?? null) === 'update_available'
      && ($status['update_available'] ?? false) === true
      && is_string($latestVersion)
      && $latestVersion !== '') {
      return max(60, (int) config('publisher-client.admin.indicator_cache_ttl_seconds', 3600));
    }

    return max(30, (int) config('publisher-client.admin.indicator_inactive_cache_ttl_seconds', 60));
  }
}
