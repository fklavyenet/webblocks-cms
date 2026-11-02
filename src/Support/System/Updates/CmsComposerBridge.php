<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\Updates\Client\Support\Version\VersionResolver;
use WebBlocks\Cms\Support\Updates\Client\Updates\RunHeartbeat;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdatePackageExtractor;
use WebBlocks\Cms\Support\WebBlocks;

/** One-time in-flight upgrade from a released archive-based CMS updater. */
class CmsComposerBridge
{
  public const VERSION = '1.112.2';

  public function beforeMigration(CommandStarting $event): void
  {
    if (WebBlocks::version() !== self::VERSION || $event->command !== 'migrate') {
      return;
    }
    $expected = realpath(base_path('vendor/fklavyenet/webblocks-cms/database/migrations/updates'));
    $paths = array_map(static fn ($path) => realpath((string) $path), (array) $event->input->getOption('path'));
    if ($expected === false || ! in_array($expected, $paths, true)
      || ! $event->input->getOption('realpath') || ! $event->input->getOption('force')
      || ! Schema::hasTable('wbcms_system_update_runs')) {
      return;
    }
    $run = SystemUpdateRun::query()->where('status', 'running')->whereNull('finished_at')
      ->where('to_version', self::VERSION)
      ->where('started_at', '>=', now()->subSeconds((int) config('webblocks-updates.installer.lock_ttl_seconds', 7200)))
      ->latest('id')->first();
    if (! $run || version_compare($run->from_version, self::VERSION, '>=')) {
      return;
    }

    app(CmsPublisherClientConfigurator::class)->configure();
    $probe = Cache::lock((string) config('publisher-client.lock.name'), 1);
    $unlocked = $probe->get();
    if ($unlocked) {
      $probe->release();
    }
    if ($unlocked || ! app()->isDownForMaintenance() || app(RunHeartbeat::class)->isStale()) {
      throw new UpdateException(__('webblocks-cms::admin.update_cli.bridge_failed'));
    }

    $this->pruneRestoredCopies($run);
    $checksum = null;
    if (Schema::hasTable('wbcms_admin_update_operations')) {
      $checksum = AdminUpdateOperation::where('system_update_run_id', $run->id)->where('status', 'running')->value('checksum_sha256');
    }
    if ($checksum === null && Schema::hasTable('wbcms_system_update_requests')) {
      $checksum = SystemUpdateRequest::where('system_update_run_id', $run->id)->where('status', 'running')->value('checksum_sha256');
    }
    $staged = $this->stagedRelease($run, is_string($checksum) ? $checksum : null);
    $progress = app(CmsUpdateProgress::class);
    $progress->start($run);
    $progress->stage('dependencies');

    $keys = ['composer_update_dependencies', 'composer_lock_only'];
    $previous = [];
    foreach ($keys as $key) {
      $previous[$key] = config('publisher-client.apply.'.$key);
    }
    config()->set('publisher-client.apply.composer_update_dependencies', false);
    config()->set('publisher-client.apply.composer_lock_only', true);
    $strategy = app(CmsComposerBridgeStrategy::class);
    $strategy->forRun((int) $run->id);
    $output = [];
    try {
      $strategy->prepare($staged, $output);
      $strategy->apply($staged);
      // No unrelated package was installed or changed. The legacy parent's
      // existing package and Composer backups still cover every changed file.
      $strategy->commit();
      $progress->stage('migrations');
    } catch (\Throwable $exception) {
      $strategy->rollback($output);
      throw new UpdateException(__('webblocks-cms::admin.update_cli.bridge_failed'), $exception->getMessage(), previous: $exception);
    } finally {
      foreach ($previous as $key => $value) {
        config()->set('publisher-client.apply.'.$key, $value);
      }
    }
  }

  private function stagedRelease(SystemUpdateRun $run, ?string $checksum): string
  {
    $root = storage_path(trim((string) config('publisher-client.apply.workspace_root'), '/'));
    $matches = [];
    foreach (glob($root.'/*/download/package.zip') ?: [] as $archive) {
      $directory = dirname($archive, 2);
      if (is_link($directory) || is_link($archive)
        || preg_match('/^\d{8}-\d{6}-[a-f0-9-]{36}$/D', basename($directory)) !== 1
        || File::lastModified($archive) < $run->started_at->timestamp - 2) {
        continue;
      }
      if ($checksum !== null && ! hash_equals(strtolower($checksum), hash_file('sha256', $archive))) {
        continue;
      }
      // The active parent has already checked this archive's mandatory
      // signature before applying it. Panel/API SQL also pins its checksum.
      $destination = $directory.'/composer-bridge-'.$run->id;
      if (File::exists($destination) || is_link($destination)) {
        throw new UpdateException(__('webblocks-cms::admin.update_cli.bridge_failed'));
      }
      $stage = app(UpdatePackageExtractor::class)->extract($archive, $destination);
      if (app(VersionResolver::class)->appliedVersion($stage) === self::VERSION) {
        $matches[] = $stage;
      }
    }
    if (count($matches) !== 1) {
      throw new UpdateException(__('webblocks-cms::admin.update_cli.bridge_failed'));
    }

    return $matches[0];
  }

  private function pruneRestoredCopies(SystemUpdateRun $active): void
  {
    foreach (glob(base_path('.publisher-recovery-bridge-*')) ?: [] as $path) {
      if (is_link($path) || preg_match('/^\.publisher-recovery-bridge-(\d+)-[a-f0-9]{24}$/D', basename($path), $match) !== 1) {
        continue;
      }
      $previous = SystemUpdateRun::find((int) $match[1]);
      if ($previous && $previous->status === 'restored'
        && $previous->from_version === $active->from_version && $previous->to_version === $active->to_version) {
        File::deleteDirectory($path);
      }
    }
  }
}
