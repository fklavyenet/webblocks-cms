<?php

namespace WebBlocks\Cms\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\WebBlocks;

class SystemDiagnosticReportQuery
{
  public const CATEGORIES = ['system_summary', 'update_run', 'plugin_health', 'saved_health', 'recent_application_errors'];

  public function collect(array $categories, ?int $runId = null): array
  {
    $result = ['schema' => 'webblocks-diagnostics/1', 'collected_at' => now()->toIso8601String(), 'categories' => []];
    foreach (array_intersect(self::CATEGORIES, $categories) as $category) {
      try {
        $result['categories'][$category] = $this->{$category}($runId);
      } catch (Throwable) {
        $result['categories'][$category] = ['state' => 'unavailable'];
      }
    }

    return $result;
  }

  private function system_summary(): array
  {
    try {
      $version = DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
      preg_match('/\d+(?:\.\d+){0,3}/', (string) $version, $match);
    } catch (Throwable) {
      $match = [];
    }

    return [
      'state' => 'available', 'cms_version' => WebBlocks::VERSION, 'php_version' => PHP_VERSION,
      'laravel_version' => app()->version(), 'database_driver' => DB::connection()->getDriverName(),
      'database_version' => $match[0] ?? 'unavailable', 'debug' => (bool) config('app.debug'),
      'memory_limit' => $this->limit('memory_limit'), 'max_execution_time' => $this->limit('max_execution_time'),
      'upload_max_filesize' => $this->limit('upload_max_filesize'), 'post_max_size' => $this->limit('post_max_size'),
    ];
  }

  private function limit(string $key): string
  {
    $value = (string) ini_get($key);

    return preg_match('/^-?\d+[KMG]?$/iD', $value) ? $value : 'unavailable';
  }

  private function update_run(?int $runId): array
  {
    if (! Schema::hasTable('wbcms_system_update_runs')) {
      return ['state' => 'unavailable'];
    }
    $query = SystemUpdateRun::query();
    $run = $runId === null ? $query->orderByDesc('id')->first() : $query->find($runId);
    if ($run === null) {
      return ['state' => 'unavailable'];
    }
    // Free-form updater output can contain host content. Export controlled fields only.
    $statuses = ['success', 'success_with_warnings', 'failed', 'restored', 'pending', 'running', 'interrupted', 'cancelled'];

    return [
      'state' => 'available', 'id' => (int) $run->id,
      'from_version' => $this->version($run->from_version), 'to_version' => $this->version($run->to_version),
      'status' => in_array($run->status, $statuses, true) ? $run->status : 'unknown',
      'started_at' => $run->started_at?->toIso8601String(), 'finished_at' => $run->finished_at?->toIso8601String(),
      'duration_ms' => $run->duration_ms === null ? null : (int) $run->duration_ms,
      'warning_count' => (int) $run->warning_count,
    ];
  }

  private function plugin_health(): array
  {
    $items = [];
    // Installed manifests are data. Never boot plugins or invoke their health reporters here.
    foreach (array_slice(app(InstalledPluginRepository::class)->installed(), 0, 100) as $installed) {
      $manifest = $installed['manifest'] ?? [];
      $handle = $manifest['handle'] ?? '';
      if (! is_string($handle) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $handle) || strlen($handle) > 100) {
        continue;
      }
      $items[] = ['handle' => $handle, 'version' => $this->version($manifest['version'] ?? null), 'enabled' => (bool) ($installed['enabled'] ?? false)];
    }

    return ['state' => 'available', 'items' => $items];
  }

  private function saved_health(): array
  {
    $snapshot = Cache::get(app(SystemHealthQuery::class)->cacheKey());
    if (! is_array($snapshot) || ! is_string($snapshot['checked_at'] ?? null)) {
      return ['state' => 'unavailable'];
    }
    $at = CarbonImmutable::parse($snapshot['checked_at']);
    $items = [];
    foreach (['system', 'backups', 'storage', 'plugins', 'updates'] as $category) {
      $counts = array_fill_keys(['healthy', 'warning', 'critical', 'unknown', 'not_applicable'], 0);
      foreach (($snapshot['categories'][$category] ?? []) as $check) {
        $status = $check['status'] ?? 'unknown';
        $counts[array_key_exists($status, $counts) ? $status : 'unknown']++;
      }
      $items[] = ['category' => $category, ...$counts];
    }

    return ['state' => $at->isFuture() || $at->lt(now()->subMinutes(5)) ? 'stale' : 'available', 'checked_at' => $at->toIso8601String(), 'items' => $items];
  }

  private function recent_application_errors(): array
  {
    $path = storage_path('logs/laravel.log');
    if (! is_file($path) || ! is_readable($path)) {
      return ['state' => 'unavailable'];
    }
    $stream = fopen($path, 'rb');
    if ($stream === false) {
      return ['state' => 'unavailable'];
    }
    try {
      fseek($stream, max(0, (int) fstat($stream)['size'] - 65536));
      $tail = stream_get_contents($stream, 65536) ?: '';
    } finally {
      fclose($stream);
    }
    $items = [];
    foreach (preg_split('/\R/', $tail) ?: [] as $line) {
      if (! preg_match('/^\[(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)\]\s+[^\s]*\.(ERROR|CRITICAL|ALERT|EMERGENCY):/', $line, $match)) {
        continue;
      }
      // Never copy message/context/SQL/URLs. Fingerprints correlate repeated errors without disclosing them.
      $items[] = ['at' => $match[1], 'level' => strtolower($match[2]), 'fingerprint' => hash('sha256', $line)];
    }

    return ['state' => 'available', 'items' => array_slice($items, -20)];
  }

  private function version(mixed $value): ?string
  {
    return is_string($value) && preg_match('/^v?\d+\.\d+(?:\.\d+)?(?:[-+][a-zA-Z0-9.-]+)?$/D', $value) && strlen($value) <= 64 ? $value : null;
  }
}
