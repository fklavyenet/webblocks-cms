<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Facades\Cache;
use Throwable;

class AdminUpdateIndicator
{
  public const CACHE_KEY = 'webblocks-cms:admin-update-indicator';

  public function __construct(
    private readonly UpdateServerClient $updateServerClient,
  ) {}

  public function payload(bool $refresh = false): array
  {
    if ($refresh) {
      $this->clear();
    }

    $status = $refresh ? null : Cache::get(self::CACHE_KEY);

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
