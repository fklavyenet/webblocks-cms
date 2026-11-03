<?php

namespace WebBlocks\Cms\Support\Jobs;

use Cron\CronExpression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use WebBlocks\Cms\Actions\Seo\ManageSeoAudit;
use WebBlocks\Cms\Models\JobRun;
use WebBlocks\Cms\Models\SeoAudit;

class JobRunner
{
  private static array $executing = [];

  public static function executing(string $key): bool
  {
    return isset(self::$executing[$key]);
  }

  public function __construct(private readonly JobRegistry $registry) {}

  public function request(string $key, ?int $site = null, ?int $subject = null, ?int $actor = null, string $source = 'panel', ?string $slot = null): JobRun
  {
    if (! JobSchema::ready() || ! $this->registry->get($key)) {
      throw new \RuntimeException('Job unavailable.');
    }
    $active = hash('sha256', $key.'|'.$site.'|'.$subject);

    // Serialize admissions without relying on a cache driver's lock support.
    return DB::transaction(function () use ($key, $site, $subject, $actor, $source, $slot, $active): JobRun {
      DB::table('wbcms_job_automation')->where('id', 1)->lockForUpdate()->first();
      $existing = JobRun::query()->where('active_key', $active)->first();
      if ($existing) {
        return $existing;
      }
      if ($slot && ($existing = JobRun::query()->where('slot_key', $slot)->first())) {
        return $existing;
      }

      return JobRun::create(['job_key' => $key, 'site_id' => $site, 'subject_id' => $subject, 'requested_by' => $actor,
        'source' => $source, 'active_key' => $active, 'slot_key' => $slot]);
    });
  }

  public function advance(JobRun $run, string $source = 'panel', int $seconds = 5, ?\Closure $step = null): JobRun
  {
    $definition = $this->registry->get($run->job_key);
    if (! $definition || ($source !== 'cli' && ! $definition->web)) {
      return $run;
    }
    $token = (string) Str::uuid();
    $claimed = DB::transaction(function () use ($run, $token, $source): bool {
      $current = JobRun::query()->lockForUpdate()->find($run->id);
      if (! $current || ! in_array($current->state, ['queued', 'running'], true) || $current->lease_until?->isFuture()) {
        return false;
      }
      $current->update(['state' => 'running', 'lease_token' => $token, 'lease_until' => now()->addMinutes(15), 'started_at' => $current->started_at ?? now()]);
      DB::table('wbcms_job_attempts')->where('run_id', $run->id)->whereNull('completed_at')->update(['state' => 'interrupted', 'reason' => 'lease_expired', 'completed_at' => now()]);
      DB::table('wbcms_job_attempts')->insert(['run_id' => $run->id, 'token' => $token, 'source' => $source, 'state' => 'running', 'started_at' => now()]);

      return true;
    });
    if (! $claimed) {
      return $run->refresh();
    }
    try {
      self::$executing[$run->job_key] = true;
      $result = ($step ?? $definition->step)($run->refresh(), max(1, min($source === 'cli' ? 40 : 5, $seconds)));
      if (! in_array($result['state'] ?? null, ['running', 'complete', 'failed', 'paused'], true)) {
        throw new \LogicException('Invalid job outcome.');
      }
    } catch (\Throwable) {
      $result = ['state' => 'failed', 'reason' => 'execution_failed'];
    } finally {
      unset(self::$executing[$run->job_key]);
    }
    DB::transaction(function () use ($run, $token, $result): void {
      $current = JobRun::query()->lockForUpdate()->find($run->id);
      if (! $current || $current->lease_token !== $token) {
        return;
      }
      if (in_array($current->requested_state, ['paused', 'cancelled'], true) && ! in_array($result['state'], ['complete', 'failed'], true)) {
        $result['state'] = $current->requested_state;
        if ($run->job_key === 'seo') {
          $audit = SeoAudit::query()->where('site_id', $run->site_id)->find($run->subject_id);
          if ($audit && in_array($audit->state, ['queued', 'running'], true)) {
            app(ManageSeoAudit::class)->transition($audit, 'pause');
          }
        }
      }
      $terminal = in_array($result['state'], ['complete', 'failed', 'cancelled'], true);
      $values = array_intersect_key($result, array_flip(['state', 'reason', 'processed', 'total', 'cursor']));
      $current->update([...$values, 'requested_state' => null, 'lease_token' => null, 'lease_until' => null, 'progress_at' => now(),
        'active_key' => $terminal ? null : $current->active_key, 'completed_at' => $terminal ? now() : null]);
      DB::table('wbcms_job_attempts')->where('run_id', $run->id)->where('token', $token)->update(['state' => $result['state'], 'reason' => $result['reason'] ?? null, 'completed_at' => now()]);
    });

    return $run->refresh();
  }

  public function tick(string $source, bool $automatic = true): void
  {
    if (! JobSchema::ready() || ! in_array($source, ['cli', 'http'], true)) {
      return;
    }
    $minute = now()->startOfMinute();
    if ($automatic && ! DB::table('wbcms_job_ticks')->insertOrIgnore(['source' => $source, 'minute' => $minute])) {
      return;
    }
    foreach ($this->registry->all() as $job) {
      if (! $this->registry->automatic($job) || ($source === 'http' && ! $job->web) || ! $job->cron || ! (new CronExpression($job->cron))->isDue(now()->toDateTimeImmutable(), config('app.timezone'))) {
        continue;
      }
      foreach ($job->scopes ? ($job->scopes)() : [[null, null]] as [$site, $subject]) {
        $slot = hash('sha256', $job->key.'|'.$site.'|'.$subject.'|'.$minute->timestamp);
        $this->request($job->key, $site, $subject, source: $source, slot: $slot);
      }
    }
    $deadline = microtime(true) + ($source === 'cli' ? 40 : 5);
    $probe = false;
    $keys = array_keys(array_filter($this->registry->all(), fn ($job) => $source === 'cli' || $job->web));
    $automaticKeys = array_keys(array_filter($this->registry->all(), fn ($job) => $this->registry->automatic($job)));
    foreach (JobRun::query()->whereIn('job_key', $keys)->whereIn('state', ['queued', 'running'])
      ->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<=', now()))
      ->where(fn ($q) => $q->where('source', 'panel')->orWhereIn('job_key', $automaticKeys))
      ->orderBy('updated_at')->orderBy('id')->limit(20)->get() as $run) {
      if (microtime(true) >= $deadline) {
        break;
      }
      if ($run->source !== 'panel' && ! $this->registry->automatic($this->registry->get($run->job_key))) {
        continue;
      }
      $result = $this->advance($run, $source, max(1, (int) ($deadline - microtime(true))));
      $probe = $probe || ($result->job_key === 'probe' && $result->state === 'complete');
    }
    if ($automatic) {
      DB::table('wbcms_job_ticks')->where('source', $source)->where('minute', $minute)->update(['completed_at' => now(), 'probe_completed' => $probe]);
    }
    $this->prune();
  }

  private function prune(): void
  {
    JobRun::query()->whereIn('state', ['complete', 'failed', 'cancelled'])->where('completed_at', '<', now()->subDays(30))->limit(100)->get()->each->delete();
    foreach (JobRun::query()->select('job_key', 'site_id')->distinct()->limit(200)->get() as $scope) {
      $terminal = JobRun::query()->where('job_key', $scope->job_key)->where('site_id', $scope->site_id)->whereIn('state', ['complete', 'failed', 'cancelled']);
      $boundary = (clone $terminal)->latest('id')->offset(99)->value('id');
      if ($boundary) {
        $terminal->where('id', '<', $boundary)->limit(100)->get()->each->delete();
      }
    }
    DB::table('wbcms_job_ticks')->where('minute', '<', now()->subDays(2))->delete();
  }
}
