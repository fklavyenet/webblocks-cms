<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Runs;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use WebBlocks\Cms\Support\Updates\Client\Contracts\RunRecorder;

/**
 * Default run recorder: a bounded JSON file under storage. Self-contained (no
 * migration required). Products rebind to a DB-backed recorder.
 */
class FileRunRecorder implements RunRecorder
{
  public function start(string $fromVersion, string $toVersion, ?int $userId): mixed
  {
    return [
      'id' => (string) Str::uuid(),
      'from_version' => $fromVersion,
      'to_version' => $toVersion,
      'user_id' => $userId,
      'started_at' => CarbonImmutable::now()->toIso8601String(),
    ];
  }

  public function finish(mixed $ref, string $status, string $summary, string $output, int $warningCount, int $durationMs): void
  {
    $ref = is_array($ref) ? $ref : [];

    $record = array_merge($ref, [
      'status' => $status,
      'summary' => $summary,
      'output' => $output,
      'warning_count' => $warningCount,
      'duration_ms' => $durationMs,
      'finished_at' => CarbonImmutable::now()->toIso8601String(),
    ]);

    $runs = $this->all();
    array_unshift($runs, $record);
    $this->write($this->trim($runs));
  }

  public function prune(): void
  {
    $this->write($this->trim($this->all()));
  }

  public function all(): array
  {
    $path = $this->path();

    if (! File::isFile($path)) {
      return [];
    }

    $decoded = json_decode((string) File::get($path), true);

    return is_array($decoded) ? $decoded : [];
  }

  private function trim(array $runs): array
  {
    $keep = max(1, (int) config('publisher-client.runs.keep', 5));

    return array_slice(array_values($runs), 0, $keep);
  }

  private function write(array $runs): void
  {
    $path = $this->path();
    File::ensureDirectoryExists(dirname($path));
    File::put($path, json_encode($runs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
  }

  private function path(): string
  {
    return storage_path(trim((string) config('publisher-client.apply.workspace_root', 'app/publisher-client'), '/').'/runs.json');
  }
}
