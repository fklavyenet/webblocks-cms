<?php

namespace WebBlocks\Cms\Support\Jobs;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use WebBlocks\Cms\Models\JobRun;
use WebBlocks\Cms\Support\System\SchedulerHost;
use WebBlocks\Cms\Support\System\SchedulerSetup;

class JobAutomation
{
  public function jobStatus(string $key): string
  {
    $job = app(JobRegistry::class)->get($key);
    if (! $job || ! JobSchema::ready()) {
      return 'unverified';
    }
    if (! app(JobRegistry::class)->automatic($job)) {
      return 'not_enabled';
    }
    $last = JobRun::query()->where('job_key', $key)->latest('id')->first();
    if ($last?->state === 'failed') {
      return 'failed';
    }
    $sources = $this->status()['sources'];

    return ($sources['cli']['verified'] || ($job->web && $sources['http']['verified'])) ? 'verified' : 'unverified';
  }

  public function status(): array
  {
    $ready = JobSchema::ready();
    $enabled = $ready && (bool) DB::table('wbcms_job_automation')->where('id', 1)->value('http_enabled');
    $sources = [];
    foreach (['cli', 'http'] as $source) {
      $ticks = $ready ? DB::table('wbcms_job_ticks')->where('source', $source)->where('probe_completed', true)->whereNotNull('completed_at')->where('minute', '>=', now()->subMinutes(5))->orderByDesc('minute')->limit(2)->get() : collect();
      $sources[$source] = ['verified' => $ticks->count() === 2 && ($source !== 'http' || $enabled), 'last' => $ticks->first()?->completed_at];
    }

    return ['sources' => $sources, 'http_enabled' => $enabled,
      'instructions' => app(SchedulerSetup::class)->instructions(), 'can_install' => $this->canInstall()];
  }

  public function canInstall(): bool
  {
    $c = app(SchedulerHost::class)->context();

    return $c['supported'] && $c['uid'] !== 0 && $c['uid'] === $c['owner'] && $c['readable'] && $c['writable'] && $c['php'] && function_exists('proc_open');
  }

  public function install(): string
  {
    if (! $this->canInstall()) {
      return 'unsupported';
    }
    $c = app(SchedulerHost::class)->context();
    $attempted = false;
    try {
      $process = new Process([$c['php'], $c['base'].'/artisan', 'webblocks:scheduler:setup', '--install', '--yes', '--json'], $c['base']);
      $process->setTimeout(25);
      $attempted = true;
      $process->run();
      $output = json_decode($process->getOutput(), true);
      $status = $output['status'] ?? null;

      return in_array($status, ['running', 'existing', 'external', 'installed_unverified', 'write_unverified', 'write_failed', 'changed', 'conflict', 'busy', 'unavailable', 'unsupported', 'account', 'root'], true) ? $status : 'write_unverified';
    } catch (\Throwable) {
      return $attempted ? 'write_unverified' : 'unavailable';
    }
  }

  public function rotate(): string
  {
    $token = bin2hex(random_bytes(32));
    DB::transaction(function () use ($token): void {
      DB::table('wbcms_job_automation')->where('id', 1)->lockForUpdate()->first();
      DB::table('wbcms_job_automation')->where('id', 1)->update(['http_enabled' => true, 'token_hash' => hash('sha256', $token)]);
      DB::table('wbcms_job_ticks')->where('source', 'http')->delete();
    });

    return $token;
  }

  public function disable(): void
  {
    DB::table('wbcms_job_automation')->where('id', 1)->update(['http_enabled' => false, 'token_hash' => null]);
  }
}
