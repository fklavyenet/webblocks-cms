<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Updates;

use Illuminate\Support\Facades\Cache;

/**
 * Liveness signal for the run lock.
 *
 * The run lock alone cannot distinguish "an update is genuinely running" from
 * "a previous run died fatally (OOM, kill) and left the lock behind until the
 * TTL". The running process writes a heartbeat timestamp alongside the lock —
 * between pipeline steps, per subprocess output chunk and while the backup
 * copy streams — so a later attempt can tell a live run (fresh heartbeat →
 * reject) from a dead one (stale/absent heartbeat → take the lock over).
 *
 * Beats are throttled in-process so streaming callbacks don't hammer the
 * cache; the stored value is a plain unix timestamp with the lock's TTL.
 */
class RunHeartbeat
{
  private float $lastBeatAt = 0.0;

  public function beat(): void
  {
    $now = microtime(true);

    if ($now - $this->lastBeatAt < $this->throttleSeconds()) {
      return;
    }

    $this->lastBeatAt = $now;

    Cache::put($this->key(), time(), $this->ttlSeconds());
  }

  public function clear(): void
  {
    Cache::forget($this->key());
  }

  /** Seconds since the last recorded beat, or null when none is recorded. */
  public function ageSeconds(): ?int
  {
    $value = Cache::get($this->key());

    if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
      return null;
    }

    return max(0, time() - (int) $value);
  }

  /**
   * Whether a held lock should be considered abandoned: no heartbeat at all
   * (every run beats immediately after acquiring the lock, so an absent beat
   * means the owner died or predates the heartbeat) or a beat older than the
   * configured stale window.
   */
  public function isStale(): bool
  {
    $age = $this->ageSeconds();

    return $age === null || $age >= $this->staleAfterSeconds();
  }

  public function staleAfterSeconds(): int
  {
    return max(60, (int) config('publisher-client.lock.stale_after_seconds', 600));
  }

  private function ttlSeconds(): int
  {
    return max(60, (int) config('publisher-client.lock.ttl_seconds', 900));
  }

  private function throttleSeconds(): int
  {
    return 5;
  }

  private function key(): string
  {
    return (string) config('publisher-client.lock.name', 'publisher-client:run').':heartbeat';
  }
}
