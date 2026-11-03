<?php

namespace WebBlocks\Cms\Support\Jobs;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\JobRun;
use WebBlocks\Cms\Models\SeoAudit;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Seo\SiteSeoAuditWorker;
use WebBlocks\Cms\Support\System\MaintenanceCleanup;

class JobRegistry
{
  public function automatic(JobDefinition $job): bool
  {
    $value = DB::table('wbcms_job_settings')->where('job_key', $job->key)->value('automatic');

    return $value === null ? $job->automatic : (bool) $value;
  }

  public function all(): array
  {
    $jobs = [
      new JobDefinition('probe', 'webblocks-cms::jobs.probe', fn () => ['state' => 'complete', 'processed' => 1, 'total' => 1], '* * * * *', web: true),
      new JobDefinition('seo', 'webblocks-cms::jobs.seo', fn (JobRun $run, int $seconds): array => $this->seoStep($run, $seconds), '* * * * *', web: true, scopes: fn () => SeoAudit::query()->whereIn('state', ['queued', 'running'])->orderBy('updated_at')->limit(20)->get()->map(fn ($a) => [$a->site_id, $a->id])->all()),
      $this->command('notifications', 'webblocks-cms::jobs.notifications', 'webblocks:notifications:dispatch', '* * * * *', effects: true),
      $this->command('reports', 'webblocks-cms::jobs.reports', 'system:reports:refresh', '*/5 * * * *'),
      $this->command('backups-cleanup', 'webblocks-cms::jobs.backups', 'system:backups:cleanup', '30 3 * * *', ['--scheduled' => true], true),
      $this->command('visitors-cleanup', 'webblocks-cms::jobs.visitors', 'visitors:cleanup', '45 3 * * *', effects: true),
      $this->command('editor-recovery', 'webblocks-cms::jobs.recovery', 'webblocks:editor-recovery:prune', '15 3 * * *', effects: true),
    ];
    foreach (MaintenanceCleanup::RUNNABLE as $category) {
      $cron = match ($category) {
        'page-revisions' => '30 4 * * 0', 'shared-slot-revisions' => '45 4 * * 0',
        'asset-revisions' => '0 4 * * *', 'media-variants' => '10 4 * * *', default => '20 4 * * *',
      };
      $jobs[] = $this->command('cleanup-'.$category, 'webblocks-cms::admin.cleanup.'.str_replace('-', '_', $category), 'system:cleanup', $cron, ['category' => $category, '--scheduled' => true], true);
    }
    $indexed = collect($jobs)->keyBy('key')->all();
    foreach (app(PluginRegistry::class)->enabled() as $plugin) {
      foreach ($plugin->jobsList() as $job) {
        if (! $job instanceof JobDefinition || $job->owner !== $plugin->handle() || ! str_starts_with($job->key, $plugin->handle().':') || isset($indexed[$job->key])) {
          throw new \LogicException('Invalid plugin job registration.');
        }
        $indexed[$job->key] = $job;
      }
    }

    return $indexed;
  }

  public function get(string $key): ?JobDefinition
  {
    return $this->all()[$key] ?? null;
  }

  public function seoStep(JobRun $run, int $seconds, int $limit = 10): array
  {
        $audit = SeoAudit::query()->where('site_id', $run->site_id)->findOrFail($run->subject_id);
        app(SiteSeoAuditWorker::class)->advance($audit->id, $limit, $seconds);
        $audit->refresh();

        return ['state' => match ($audit->state) {
          'queued', 'running' => 'running', 'complete', 'partial' => 'complete', 'paused' => 'paused', default => 'failed',
        }, 'reason' => in_array($audit->state, ['partial', 'stale', 'failed'], true) ? $audit->state : null,
          'processed' => $audit->processed, 'total' => $audit->phase === 'inventory' ? null : $audit->total];

  }

  private function command(string $key, string $label, string $command, string $cron, array $arguments = [], bool $effects = false): JobDefinition
  {
    // Existing unbounded commands stay CLI-only until their domain service supports checkpoints.
    return new JobDefinition($key, $label, function () use ($command, $arguments): array {
      return ['state' => Artisan::call($command, $arguments) === 0 ? 'complete' : 'failed'];
    }, $cron, effects: $effects, destination: match ($key) {
      'notifications' => 'admin.sites.index', 'reports' => 'admin.system.health.index', 'backups-cleanup' => 'admin.system.backups.index',
      'visitors-cleanup' => 'admin.reports.visitors.index', default => 'admin.system.cleanup.index',
    });
  }
}
