<?php

namespace WebBlocks\Cms\Queries;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Models\SystemBackupRestore;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Plugins\PluginHealthMonitor;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth;
use WebBlocks\Cms\Support\System\MaintenanceCleanup;
use WebBlocks\Cms\Support\System\MaintenanceCleanupResult;
use WebBlocks\Cms\Support\System\SystemBackupCleanup;
use WebBlocks\Cms\Support\System\SystemBackupManager;
use WebBlocks\Cms\Support\System\SystemHealthStatus as Health;
use WebBlocks\Cms\Support\System\SystemInformation;
use WebBlocks\Cms\Support\System\SystemUpdateInspector;
use WebBlocks\Cms\Support\System\Updates\AdminUpdateIndicator;
use WebBlocks\Cms\Support\WebBlocks;

class SystemHealthQuery
{
  public const TTL_SECONDS = 300;

  public function __construct(
    private readonly SystemHealthSiteQuery $sites,
    private readonly SystemInformation $information,
    private readonly SystemBackupManager $backups,
    private readonly MaintenanceCleanup $cleanup,
    private readonly SystemBackupCleanup $backupCleanup,
    private readonly SchedulerHealth $scheduler,
    private readonly PluginRegistry $plugins,
    private readonly PluginHealthMonitor $pluginHealth,
    private readonly InstalledPluginRepository $installedPlugins,
    private readonly SystemUpdateInspector $updates,
  ) {}

  public function cacheKey(): string
  {
    return 'webblocks-cms:system-health:'.WebBlocks::VERSION.':'.hash('sha256', base_path().config('database.default').config('database.connections.'.config('database.default').'.database'));
  }

  public function refresh(): void
  {
    Cache::forget($this->cacheKey());
  }

  public function report(?int $siteId = null): array
  {
    try {
      $snapshot = Cache::remember($this->cacheKey(), self::TTL_SECONDS, fn () => $this->snapshot());
    } catch (Throwable) {
      $snapshot = $this->snapshot();
    }
    $siteOptions = array_map(fn (array $site) => ['id' => $site['id'], 'name' => $site['name']], $snapshot['sites']);
    $sites = array_values(array_filter($snapshot['sites'], fn (array $site) => $siteId === null || $site['id'] === $siteId));
    $scheduler = $this->schedulerSnapshot();
    foreach ($sites as &$site) {
      $site['scheduling'] = $this->schedulingCheck($scheduler, $site['scheduler_required']);
      $site['scheduling']['route'] = 'admin.sites.edit';
      $site['scheduling']['route_parameters'] = ['site' => $site['id'], 'tab' => 'contact'];
      $site['status'] = Health::aggregate([...$site['checks'], $site['search'], $site['scheduling']]);
      $site['lead'] = Health::lead([...$site['checks'], $site['search'], $site['scheduling']]);
    }
    unset($site);

    $categories = $snapshot['categories'];
    $siteChecks = [];
    $searchChecks = [];
    foreach ($sites as $site) {
      foreach ($site['checks'] as $check) {
        $siteChecks[] = $check + ['site_id' => $site['id'], 'site_name' => $site['name']];
      }
      $searchChecks[] = $site['search'] + ['site_id' => $site['id'], 'site_name' => $site['name']];
    }
    $fallback = $snapshot['sites_available'] ? 'not_applicable' : 'unknown';
    $fallbackMessage = $snapshot['sites_available'] ? 'no_sites' : 'check_unavailable';
    $categories['sites'] = $siteChecks ?: [Health::check('sites', $fallback, $fallbackMessage, 'admin.sites.index')];
    $categories['search'] = $searchChecks ?: [Health::check('search', $fallback, $fallbackMessage, 'admin.system.search.index')];
    $categories['scheduling'] = [$this->schedulingCheck($scheduler, collect($sites)->contains('scheduler_required', true))];

    $categories = array_replace(array_fill_keys(['system', 'sites', 'backups', 'scheduling', 'storage', 'search', 'plugins', 'updates'], []), $categories);
    $issues = [];
    $counts = array_fill_keys(array_keys(Health::ORDER), 0);
    foreach ($categories as $category => &$checks) {
      foreach ($checks as $check) {
        $counts[$check['status']]++;
        if (in_array($check['status'], ['critical', 'warning', 'unknown'], true)) {
          $issues[] = $check + ['category' => $category];
        }
      }
      $checks = ['status' => Health::aggregate($checks), 'lead' => Health::lead($checks), 'checks' => $checks];
    }
    unset($checks);
    usort($issues, fn (array $a, array $b) => Health::ORDER[$a['status']] <=> Health::ORDER[$b['status']]);

    return ['checked_at' => $snapshot['checked_at'], 'scheduler_checked_at' => now()->toIso8601String(), 'cache_seconds' => self::TTL_SECONDS, 'selected_site_id' => $siteId, 'site_options' => $siteOptions, 'sites' => $sites, 'categories' => $categories, 'counts' => $counts, 'issues' => $issues, 'information' => $this->information->rows(), 'operations' => $snapshot['operations']];
  }

  private function snapshot(): array
  {
    try {
      $sites = $this->sites->rows();
      $available = true;
    } catch (Throwable) {
      $sites = [];
      $available = false;
    }

    $categories = [];
    foreach (['system' => 'system', 'backups' => 'backups', 'storage' => 'storage', 'plugins' => 'plugins', 'updates' => 'updates'] as $category => $method) {
      try {
        $categories[$category] = $this->{$method}();
      } catch (Throwable) {
        $categories[$category] = [Health::check($category, 'unknown', 'check_unavailable', $this->categoryRoute($category))];
      }
    }
    try {
      $operations = $this->operations();
    } catch (Throwable) {
      $operations = [];
    }

    return ['checked_at' => now()->toIso8601String(), 'sites' => $sites, 'sites_available' => $available, 'categories' => $categories, 'operations' => $operations];
  }

  private function system(): array
  {
    DB::connection()->getPdo();
    $primary = Site::query()->where('is_primary', true)->count();
    $debug = app()->environment('production') && config('app.debug');

    return [
      Health::check('database', 'healthy', 'database_ready', 'admin.system.information'),
      Health::check('debug', $debug ? 'warning' : 'healthy', $debug ? 'debug_review' : 'debug_ready', 'admin.system.settings.edit'),
      Health::check('primary_site', $primary === 1 ? 'healthy' : 'warning', $primary === 1 ? 'primary_ready' : 'primary_review', 'admin.sites.index'),
    ];
  }

  private function backups(): array
  {
    $route = 'admin.system.backups.index';
    if (! Schema::hasTable('wbcms_system_backups')) {
      return [Health::check('backups', 'unknown', 'check_unavailable', $route)];
    }
    $freshness = $this->backups->freshnessSummary();
    $successful = $freshness['latest_successful'];
    $checks = [Health::check('backup_age', $freshness['has_recent_successful_backup'] ? 'healthy' : 'warning', $successful ? ($freshness['has_recent_successful_backup'] ? 'backup_recent' : 'backup_old') : 'backup_none', $route, ['hours' => $freshness['hours']])];
    if ($successful) {
      $available = $this->backups->archiveResolution($successful)->isAvailable();
      $checks[] = Health::check('backup_archive', $available ? 'healthy' : 'critical', $available ? 'backup_available' : 'backup_unavailable', 'admin.system.backups.show', [], ['backup' => $successful->id]);
    }
    $stale = SystemBackup::query()->where('status', SystemBackup::STATUS_RUNNING)->get()->contains(fn (SystemBackup $backup) => $backup->isStaleRunning());
    if ($stale) {
      $checks[] = Health::check('backup_running', 'warning', 'backup_stalled', $route);
    }
    if ($freshness['latest']?->isFailed()) {
      $checks[] = Health::check('backup_last', 'warning', 'backup_failed', $route);
    }

    return $checks;
  }

  private function storage(): array
  {
    $overview = $this->cleanup->overview();
    $preview = $this->backupCleanup->preview();
    $bytes = $preview->candidateBytes;
    $count = $preview->candidateCount();
    foreach ($overview as $item) {
      if ($item instanceof MaintenanceCleanupResult) {
        $bytes += $item->candidateBytes;
        $count += $item->candidateCount;
      }
    }
    $free = @disk_free_space(storage_path());
    $checks = [
      Health::check('disk', $free === false ? 'unknown' : ($free < 500 * 1048576 ? 'critical' : 'healthy'), $free === false ? 'disk_unknown' : ($free < 500 * 1048576 ? 'disk_low' : 'disk_ready'), 'admin.system.cleanup.index'),
      Health::check('cleanup', $count > 0 ? 'warning' : 'healthy', $count > 0 ? 'cleanup_available' : 'cleanup_ready', 'admin.system.cleanup.index', ['count' => $count, 'size' => $this->formatBytes($bytes)]),
    ];
    foreach ($overview['capacity_warnings'] as $warning) {
      $checks[] = Health::check($warning, 'warning', 'capacity_'.$warning, 'admin.system.cleanup.index');
    }

    return $checks;
  }

  private function plugins(): array
  {
    $checks = [];
    foreach ($this->plugins->all() as $plugin) {
      if (! $this->plugins->isConfiguredEnabled($plugin->handle())) {
        continue;
      }
      $result = $this->pluginHealth->healthFor($plugin);
      $status = match ($result->status) {
      'healthy' => 'healthy', 'warning' => 'warning', 'incompatible' => 'critical', default => 'unknown'
      };
      // Reporters may return private exception text. Show controlled copy only.
      $checks[] = Health::check('plugin', $status, 'plugin_'.$status, 'admin.system.plugins.show', ['name' => $plugin->labelText()], ['plugin' => $plugin->handle()]);
    }
    foreach ($this->installedPlugins->installed() as $installed) {
      $handle = (string) $installed['manifest']['handle'];
      $version = (string) $installed['manifest']['version'];
      if ($this->installedPlugins->runtimeFailed($handle, $version)) {
        $checks[] = Health::check('plugin_recovery', 'critical', 'plugin_recovery', 'admin.plugins.recovery.index', ['name' => $handle]);
      }
    }

    return $checks ?: [Health::check('plugins', 'not_applicable', 'plugins_empty', 'admin.system.plugins.index')];
  }

  private function updates(): array
  {
    $cached = Cache::get(AdminUpdateIndicator::CACHE_KEY, []);
    $cached = is_array($cached) ? $cached : [];
    $state = $cached['state'] ?? 'unknown';
    $checkedAt = $cached['checked_at'] ?? null;
    try {
      $at = $checkedAt ? now()->parse($checkedAt) : null;
      $fresh = $at && $at->gte(now()->subHour()) && $at->lte(now()->addMinute());
    } catch (Throwable) {
      $fresh = false;
    }
    $status = ! $fresh ? 'unknown' : match ($state) {
    'up_to_date' => 'healthy', 'update_available', 'incompatible' => 'warning', default => 'unknown'
    };
    $checks = [Health::check('update_version', $status, 'update_'.$status, 'admin.system.updates.index')];
    $ready = collect($this->updates->readOnlyChecks())->every(fn (array $check) => $check['status'] === 'pass');
    $checks[] = Health::check('update_readiness', $ready ? 'healthy' : 'warning', $ready ? 'update_ready' : 'update_review', 'admin.system.updates.index');

    return $checks;
  }

  private function schedulerSnapshot(): array
  {
    try {
      return $this->scheduler->snapshot();
    } catch (Throwable) {
      return ['status' => 'unavailable'];
    }
  }

  private function schedulingCheck(array $snapshot, bool $required): array
  {
    $state = $snapshot['status'];
    $status = match ($state) {
    'healthy' => 'healthy', 'failed' => $required ? 'critical' : 'warning', 'delayed' => $required ? 'warning' : 'not_applicable', default => $required ? 'unknown' : 'not_applicable'
    };

    return Health::check('scheduling', $status, $status === 'not_applicable' ? 'scheduler_optional' : 'scheduler_'.$status, 'admin.dashboard');
  }

  private function operations(): array
  {
    $operations = [];
    foreach ([SystemBackup::class => ['backup', 'admin.system.backups.index'], SystemBackupRestore::class => ['restore', 'admin.system.backups.index'], SystemUpdateRun::class => ['update', 'admin.system.updates.index']] as $model => [$type, $route]) {
      if (! Schema::hasTable((new $model)->getTable())) {
        continue;
      }
      foreach ($model::query()->latest()->limit(5)->get(['id', 'status', 'created_at']) as $row) {
        $status = match ($row->status) {
        'completed', 'success' => 'healthy', 'failed', 'restored' => 'critical', default => 'warning'
        };
        $operations[] = ['type' => $type, 'id' => (int) $row->id, 'status' => $status, 'outcome' => in_array($row->status, ['running', 'pending'], true) ? 'running' : (in_array($row->status, ['failed', 'restored'], true) ? 'failed' : (in_array($row->status, ['completed', 'success'], true) ? 'completed' : 'review')), 'at' => $row->created_at->toIso8601String(), 'route' => $route];
      }
    }
    usort($operations, fn (array $a, array $b) => strcmp($b['at'], $a['at']));

    return array_slice($operations, 0, 8);
  }

  private function categoryRoute(string $category): string
  {
    return match ($category) {
    'system' => 'admin.system.information', 'storage' => 'admin.system.cleanup.index', default => 'admin.system.'.$category.'.index'
    };
  }

  private function formatBytes(int $bytes): string
  {
    return $bytes < 1048576 ? number_format($bytes / 1024, 1).' KB' : number_format($bytes / 1048576, 1).' MB';
  }
}
