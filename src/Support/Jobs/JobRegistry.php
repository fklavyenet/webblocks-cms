<?php

namespace WebBlocks\Cms\Support\Jobs;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\JobRun;
use WebBlocks\Cms\Models\SeoAudit;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Seo\SiteSeoAuditWorker;
use WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth;
use WebBlocks\Cms\Support\System\MaintenanceCleanup;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\Visitors\VisitorReportRetention;

class JobRegistry
{
  public function automatic(JobDefinition $job): bool
  {
    $value = JobSchema::ready() ? DB::table('wbcms_job_settings')->where('job_key', $job->key)->value('automatic') : null;

    return $value === null ? $job->automatic : (bool) $value;
  }

  public function all(): array
  {
    $jobs = [
      new JobDefinition('probe', 'webblocks-cms::jobs.probe', fn () => ['state' => 'complete', 'processed' => 1, 'total' => 1], '* * * * *', web: true, automatic: true),
      new JobDefinition('seo', 'webblocks-cms::jobs.seo', fn (JobRun $run, int $seconds): array => $this->seoStep($run, $seconds), '* * * * *', web: true, scopes: fn () => SeoAudit::query()->whereIn('state', ['queued', 'running'])->orderBy('updated_at')->limit(20)->get()->map(fn ($a) => [$a->site_id, $a->id])->all(), automatic: false, help: 'webblocks-cms::jobs.seo_help'),
      $this->command('notifications', 'webblocks-cms::jobs.notifications', 'webblocks:notifications:dispatch', '* * * * *', effects: true, automatic: app(SchedulerHealth::class)->requiredForInstallation()),
      $this->command('reports', 'webblocks-cms::jobs.reports', 'system:reports:refresh', '*/5 * * * *'),
      $this->command('backups-cleanup', 'webblocks-cms::jobs.backups', 'system:backups:cleanup', '30 3 * * *', ['--scheduled' => true], true),
      new JobDefinition('visitors-cleanup', 'webblocks-cms::jobs.visitors', fn (JobRun $run): array => app(ManualMaintenance::class)->visitors($run), '45 3 * * *', web: true, effects: true, automatic: false, destination: 'admin.reports.visitors.index', help: 'webblocks-cms::jobs.visitors_help', guard: fn () => hash('sha256', serialize(app(VisitorReportRetention::class)->policy()))),
      new JobDefinition('editor-recovery', 'webblocks-cms::jobs.recovery', fn (JobRun $run): array => app(ManualMaintenance::class)->recovery($run), '15 3 * * *', web: true, effects: true, automatic: false, help: 'webblocks-cms::jobs.recovery_help'),
    ];
    foreach (MaintenanceCleanup::RUNNABLE as $category) {
      $cron = match ($category) {
        'page-revisions' => '30 4 * * 0', 'shared-slot-revisions' => '45 4 * * 0',
        'asset-revisions' => '0 4 * * *', 'media-variants' => '10 4 * * *', default => '20 4 * * *',
      };
      $jobs[] = $this->command('cleanup-'.$category, 'webblocks-cms::admin.cleanup.'.str_replace('-', '_', $category), 'system:cleanup', $cron, ['category' => $category, '--scheduled' => true], true, app(SystemSettings::class)->automaticMaintenanceSettings()[$category] ?? false);
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

  private function command(string $key, string $label, string $command, string $cron, array $arguments = [], bool $effects = false, bool $automatic = false): JobDefinition
  {
    // Existing unbounded commands stay CLI-only until their domain service supports checkpoints.
    return new JobDefinition($key, $label, function () use ($command, $arguments): array {
      return ['state' => Artisan::call($command, $arguments) === 0 ? 'complete' : 'failed'];
    }, $cron, effects: $effects, automatic: $automatic, destination: match ($key) {
      'notifications' => 'admin.sites.index', 'reports' => 'admin.system.health.index', 'backups-cleanup' => 'admin.system.backups.index',
      'visitors-cleanup' => 'admin.reports.visitors.index', default => 'admin.system.cleanup.index',
    }, help: $key === 'notifications' ? 'webblocks-cms::jobs.notifications_help' : 'webblocks-cms::jobs.feature_manual_help');
  }
}
