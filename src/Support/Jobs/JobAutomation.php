<?php

namespace WebBlocks\Cms\Support\Jobs;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\JobRun;
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

    $automatic = array_filter(app(JobRegistry::class)->all(), fn ($job) => $job->key !== 'probe' && app(JobRegistry::class)->automatic($job));

    return ['sources' => $sources, 'http_enabled' => $enabled, 'has_automatic' => $automatic !== [],
      'server_required' => collect($automatic)->contains(fn ($job) => ! $job->web),
      'instructions' => app(SchedulerSetup::class)->instructions()];
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
