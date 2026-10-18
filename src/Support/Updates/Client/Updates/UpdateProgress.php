<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Updates;

use Illuminate\Support\Str;
use WebBlocks\Cms\Support\Updates\Client\Contracts\ProgressReporter;

/** Private, cache-independent journal for hosts without a relational adapter. */
final class UpdateProgress implements ProgressReporter
{
    public const PHASES = ['check', 'filesystem_backup', 'download', 'verify', 'extract', 'maintenance_down', 'apply', 'dependencies', 'migrations', 'cache', 'post_apply', 'verify_version', 'maintenance_up', 'rollback'];
    private ?string $id = null;
    private float $lastBeat = 0;

    public function __construct(private readonly ?string $directory = null) {}

    public function begin(?string $id = null): string
    {
        $id ??= (string) Str::uuid();
        if (!Str::isUuid($id)) { throw new UpdateException('Invalid update operation identifier.'); }
        $this->locked(function (string $root) use ($id): void {
            $active = is_file($root.'/active') ? trim((string) file_get_contents($root.'/active')) : null;
            if ($active || is_file($root.'/'.$id.'.json')) { throw new UpdateException('An update operation already exists. Review its status before starting another.'); }
            $this->write($root.'/'.$id.'.json', ['operation_id' => $id, 'state' => 'running', 'started_ms' => $this->now(), 'heartbeat_ms' => $this->now(), 'finished_ms' => null, 'steps' => []]);
            $this->write($root.'/active', $id);
        });
        $this->id = $id;
        return $id;
    }

    public function beginRun(): void { if ($this->id === null) { $this->begin(); } }
    public function phase(string $phase): void
    {
        if (!in_array($phase, self::PHASES, true)) { throw new \InvalidArgumentException('Unknown update phase.'); }
        $this->mutate(function (array &$data) use ($phase): void {
            $last = array_key_last($data['steps']);
            if ($last !== null && $data['steps'][$last]['phase'] === $phase && $data['steps'][$last]['state'] === 'running') { return; }
            $this->close($data, 'complete');
            $data['steps'][] = ['phase' => $phase, 'state' => 'running', 'started_ms' => $this->now(), 'finished_ms' => null];
        });
    }
    public function beat(): void
    {
        if (microtime(true) - $this->lastBeat < 5) { return; }
        $this->lastBeat = microtime(true);
        $this->mutate(static function (array &$data): void {});
    }
    public function failStep(): void { $this->mutate(function (array &$data): void { $this->close($data, 'failed'); }); }
    public function endRun(string $status): void
    {
        if (!in_array($status, ['success', 'success_with_warnings', 'failed', 'restored'], true)) { throw new \InvalidArgumentException('Invalid update result.'); }
        $this->mutate(function (array &$data) use ($status): void {
            $this->close($data, in_array($status, ['success', 'success_with_warnings', 'restored'], true) ? 'complete' : 'failed');
            $data['state'] = $status;
            $data['finished_ms'] = $this->now();
        });
        $this->id = null;
    }
    public function payload(?string $id = null): array
    {
        if ($id !== null && !Str::isUuid($id)) { return ['state' => 'unknown', 'terminal' => false, 'running' => false]; }
        return $this->locked(function (string $root) use ($id): array {
            $id ??= is_file($root.'/active') ? trim((string) file_get_contents($root.'/active')) : null;
            $data = $id && Str::isUuid($id) && is_file($root.'/'.$id.'.json') ? json_decode((string) file_get_contents($root.'/'.$id.'.json'), true, 512, JSON_THROW_ON_ERROR) : null;
            if (!$data) { return ['state' => $id ? 'unknown' : 'idle', 'terminal' => false, 'running' => false]; }
            $timeout = max(180, (int) config('publisher-client.commands.timeout_seconds', 600) + 60, (int) config('publisher-client.apply.download_timeout_seconds', 120) + 60) * 1000;
            $state = $data['state'] === 'running' && $this->now() - $data['heartbeat_ms'] > $timeout ? 'interrupted' : $data['state'];
            $end = $data['finished_ms'] ?? $this->now();
            return ['operation_id' => $id, 'state' => $state, 'running' => $state === 'running', 'terminal' => in_array($state, ['success', 'success_with_warnings', 'failed', 'restored'], true), 'elapsed_ms' => max(0, $end - $data['started_ms']), 'message' => __('publisher-client::updates.progress.'.($state === 'failed' ? 'failure_message' : $state)), 'steps' => array_map(static fn ($step) => ['phase' => $step['phase'], 'label' => __('publisher-client::updates.phases.'.$step['phase']), 'state' => $state === 'interrupted' && $step['state'] === 'running' ? 'interrupted' : $step['state'], 'elapsed_ms' => max(0, ($step['finished_ms'] ?? $end) - $step['started_ms'])], $data['steps'])];
        });
    }
    private function mutate(callable $change): void
    {
        if ($this->id === null) { return; }
        $this->locked(function (string $root) use ($change): void {
            $path = $root.'/'.$this->id.'.json';
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if ($data['state'] !== 'running') { return; }
            $change($data); $data['heartbeat_ms'] = $this->now(); $this->write($path, $data);
            if ($data['state'] !== 'running' && is_file($root.'/active') && trim((string) file_get_contents($root.'/active')) === $this->id) { unlink($root.'/active'); }
        });
    }
    private function close(array &$data, string $state): void
    {
        $key = array_key_last($data['steps']);
        if ($key !== null && $data['steps'][$key]['state'] === 'running') { $data['steps'][$key]['state'] = $state; $data['steps'][$key]['finished_ms'] = $this->now(); }
    }
    private function locked(callable $callback): mixed
    {
        $storage = realpath(storage_path()) ?: storage_path();
        $root = $this->directory ?? $storage.'/app/publisher-client/progress/'.hash('sha256', (string) config('publisher-client.product').'|'.(string) config('publisher-client.lock.name'));
        for ($path = $root; $path !== dirname($path); $path = dirname($path)) { if (is_link($path)) { throw new UpdateException('Unsafe update progress storage.'); } }
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) { throw new UpdateException('Update progress storage is unavailable.'); }
        foreach (glob($root.'/*') ?: [] as $path) { if (is_link($path)) { throw new UpdateException('Unsafe update progress storage.'); } }
        if (is_link($root.'/.lock')) { throw new UpdateException('Unsafe update progress storage.'); }
        $lock = fopen($root.'/.lock', 'c');
        if ($lock === false) { throw new UpdateException('Update progress storage is unavailable.'); }
        chmod($root.'/.lock', 0600);
        if (!flock($lock, LOCK_EX)) { fclose($lock); throw new UpdateException('Update progress storage is busy.'); }
        try { return $callback($root); } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
    private function write(string $path, array|string $data): void
    {
        $tmp = tempnam(dirname($path), '.progress-');
        if ($tmp === false) { throw new UpdateException('Update progress storage is unavailable.'); }
        try {
            chmod($tmp, 0600);
            if (file_put_contents($tmp, is_array($data) ? json_encode($data, JSON_THROW_ON_ERROR) : $data) === false || !rename($tmp, $path)) { throw new UpdateException('Update progress could not be recorded.'); }
        } finally { if (is_file($tmp)) { unlink($tmp); } }
    }
    private function now(): int { return now()->getTimestampMs(); }
}
