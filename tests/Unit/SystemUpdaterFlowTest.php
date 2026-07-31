<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as AuthenticatableUser;

if (! class_exists(User::class, false)) {
  class User extends AuthenticatableUser {}
}

namespace WebBlocks\Cms\Tests\Unit;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\System\BackupRestoreResult;
use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\System\SystemBackupManager;
use WebBlocks\Cms\Support\System\SystemBackupRestoreManager;
use WebBlocks\Cms\Support\System\Updates\SystemUpdater;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateRunRetention;
use WebBlocks\Cms\Support\System\Updates\UpdateCheckResult;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\System\Updates\UpdateInstaller;
use WebBlocks\Cms\Support\System\Updates\UpdatePackageDownloader;
use WebBlocks\Cms\Support\System\Updates\UpdatePackageExtractor;
use WebBlocks\Cms\Support\System\Updates\UpdateServerClient;
use WebBlocks\Cms\Support\System\Updates\UpdateSignatureVerifier;
use WebBlocks\Cms\Support\System\Updates\UpdateWorkspaceManager;
use WebBlocks\Cms\Tests\TestCase;

class SystemUpdaterFlowTest extends TestCase
{
  private string $workspaceRoot;

  protected function setUp(): void
  {
    parent::setUp();

    Schema::create('wbcms_system_update_runs', function (Blueprint $table) {
      $table->id();
      $table->string('from_version');
      $table->string('to_version');
      $table->string('status', 32);
      $table->string('summary')->nullable();
      $table->longText('output')->nullable();
      $table->unsignedInteger('warning_count')->default(0);
      $table->timestamp('started_at')->nullable();
      $table->timestamp('finished_at')->nullable();
      $table->unsignedBigInteger('duration_ms')->nullable();
      $table->unsignedBigInteger('triggered_by_user_id')->nullable();
      $table->timestamps();
    });

    $this->workspaceRoot = storage_path('framework/testing/system-updater-flow');
    File::ensureDirectoryExists($this->workspaceRoot);
  }

  protected function tearDown(): void
  {
    File::deleteDirectory($this->workspaceRoot);

    parent::tearDown();
  }

  #[Test]
  public function successful_run_records_a_success_run_and_releases_the_lock(): void
  {
    $updater = $this->makeUpdater();

    $result = $updater->run($this->user());

    $this->assertSame(SystemUpdateRun::STATUS_SUCCESS, $result->status);
    $this->assertSame('1.0.0', $result->fromVersion);
    $this->assertSame('1.1.0', $result->toVersion);
    $this->assertStringContainsString('Pre-update backup created', $result->output);
    $this->assertStringContainsString('Installed version persisted as 1.1.0', $result->output);

    $run = SystemUpdateRun::query()->latest('id')->first();
    $this->assertNotNull($run);
    $this->assertSame(SystemUpdateRun::STATUS_SUCCESS, $run->status);
    $this->assertSame('1.1.0', $run->to_version);
    $this->assertNotNull($run->finished_at);

    $this->assertFalse($updater->isLocked(), 'The update lock must be released after a successful run.');
  }

  #[Test]
  public function failure_before_apply_does_not_attempt_a_restore(): void
  {
    $restoreManager = Mockery::mock(SystemBackupRestoreManager::class);
    $restoreManager->shouldNotReceive('restoreFromBackup');

    $downloader = Mockery::mock(UpdatePackageDownloader::class);
    $downloader->shouldReceive('download')
      ->once()
      ->andThrow(new UpdateException('The update package could not be downloaded.'));

    $updater = $this->makeUpdater([
      'downloader' => $downloader,
      'restoreManager' => $restoreManager,
    ]);

    try {
      $updater->run($this->user());
      $this->fail('An UpdateException was expected.');
    } catch (UpdateException $exception) {
      $this->assertSame('The update package could not be downloaded.', $exception->userMessage());
    }

    $run = SystemUpdateRun::query()->latest('id')->first();
    $this->assertNotNull($run);
    $this->assertSame(SystemUpdateRun::STATUS_FAILED, $run->status);
    $this->assertStringNotContainsString('restored', (string) $run->output);
    $this->assertFalse($updater->isLocked());
  }

  #[Test]
  public function failure_after_apply_restores_the_backup_and_records_restored_status(): void
  {
    $backup = $this->backup();

    $installer = $this->workingInstaller();
    $installer->shouldReceive('installDependencies')
      ->once()
      ->andThrow(new UpdateException('The update could not install dependencies.', 'composer dump-autoload failed'));
    $installer->shouldReceive('leaveMaintenance')->once(); // maintenance recovery after failure

    $restoreManager = Mockery::mock(SystemBackupRestoreManager::class);
    $restoreManager->shouldReceive('restoreFromBackup')
      ->once()
      ->withArgs(fn (SystemBackup $restored, ?int $userId): bool => $restored === $backup && $userId === 7)
      ->andReturn(Mockery::mock(BackupRestoreResult::class));

    $updater = $this->makeUpdater([
      'installer' => $installer,
      'restoreManager' => $restoreManager,
      'backup' => $backup,
    ]);

    try {
      $updater->run($this->user());
      $this->fail('An UpdateException was expected.');
    } catch (UpdateException $exception) {
      $this->assertSame('The update could not install dependencies.', $exception->userMessage());
    }

    $run = SystemUpdateRun::query()->latest('id')->first();
    $this->assertNotNull($run);
    $this->assertSame(SystemUpdateRun::STATUS_RESTORED, $run->status);
    $this->assertStringContainsString('backup was restored automatically', (string) $run->summary);
    $this->assertStringContainsString('restored after failure', (string) $run->output);
    $this->assertStringContainsString('Update failed:', (string) $run->output);
    $this->assertFalse($updater->isLocked());
  }

  #[Test]
  public function restore_failure_keeps_failed_status_with_both_error_trails(): void
  {
    $installer = $this->workingInstaller();
    $installer->shouldReceive('installDependencies')
      ->once()
      ->andThrow(new UpdateException('The update could not install dependencies.', 'composer dump-autoload failed'));
    $installer->shouldReceive('leaveMaintenance')->once();

    $restoreManager = Mockery::mock(SystemBackupRestoreManager::class);
    $restoreManager->shouldReceive('restoreFromBackup')
      ->once()
      ->andThrow(new RuntimeException('Database restore command failed with password=hunter2.'));

    $updater = $this->makeUpdater([
      'installer' => $installer,
      'restoreManager' => $restoreManager,
    ]);

    try {
      $updater->run($this->user());
      $this->fail('An UpdateException was expected.');
    } catch (UpdateException $exception) {
      $this->assertSame('The update could not install dependencies.', $exception->userMessage());
    }

    $run = SystemUpdateRun::query()->latest('id')->first();
    $this->assertNotNull($run);
    $this->assertSame(SystemUpdateRun::STATUS_FAILED, $run->status);

    $output = (string) $run->output;
    $this->assertStringContainsString('Update failed: composer dump-autoload failed', $output);
    $this->assertStringContainsString('Automatic restore of pre-update backup', $output);
    $this->assertStringContainsString('Database restore command failed', $output);
    $this->assertStringContainsString('password=[redacted]', $output, 'Restore failure detail must be sanitized.');
    $this->assertStringNotContainsString('hunter2', $output);
    $this->assertStringContainsString('manually from the Backups screen', $output);
    $this->assertFalse($updater->isLocked());
  }

  #[Test]
  public function a_second_run_is_rejected_while_the_lock_is_held(): void
  {
    $lock = Cache::lock((string) config('webblocks-updates.installer.lock_name', 'system-updates:run'), 900);
    $this->assertTrue($lock->get());

    $updater = $this->makeUpdater();

    try {
      $this->expectException(UpdateException::class);
      $this->expectExceptionMessage('Another update is already running.');
      $updater->run($this->user());
    } finally {
      $lock->release();
    }
  }

  private function user(): User
  {
    $user = new User;
    $user->forceFill(['id' => 7]);

    return $user;
  }

  private function backup(): SystemBackup
  {
    $backup = new SystemBackup;
    $backup->forceFill([
      'id' => 42,
      'archive_filename' => 'pre-update-backup.zip',
    ]);

    return $backup;
  }

  private function workingInstaller(): Mockery\MockInterface
  {
    $installer = Mockery::mock(UpdateInstaller::class);
    $installer->shouldReceive('enterMaintenance')->once();
    $installer->shouldReceive('applyPackage')->once();
    $installer->shouldReceive('rollbackAppliedPackage')->once();

    return $installer;
  }

  private function makeUpdater(array $overrides = []): SystemUpdater
  {
    $packageContents = 'signed-release-package';
    $checksum = hash('sha256', $packageContents);
    $release = [
      'version' => '1.1.0',
      'download_url' => 'https://updates.example.test/webblocks-cms-1.1.0.zip',
      'checksum_sha256' => $checksum,
    ];

    $serverClient = Mockery::mock(UpdateServerClient::class);
    $serverClient->shouldReceive('check')->andReturn(new UpdateCheckResult(
      state: 'update_available',
      label: 'Update available',
      message: 'Update 1.1.0 is available.',
      badgeClass: 'wb-status-pending',
      serverReachable: true,
      apiVersion: '1',
      serverUrl: 'https://updates.example.test',
      product: 'webblocks-cms',
      channel: 'stable',
      installedVersion: '1.0.0',
      latestVersion: '1.1.0',
      updateAvailable: true,
      compatibility: ['status' => 'compatible', 'reasons' => []],
      release: $release,
      errorCode: null,
      errorMessage: null,
      checkedAt: CarbonImmutable::now(),
    ));

    $versionStore = Mockery::mock(InstalledVersionStore::class);
    $versionStore->shouldReceive('currentVersion')->andReturn('1.0.0');
    $versionStore->shouldReceive('persist');

    $workspaceManager = Mockery::mock(UpdateWorkspaceManager::class);
    $workspaceManager->shouldReceive('create')->andReturn([
      'root' => $this->workspaceRoot,
      'archive' => $this->workspaceRoot.'/package.zip',
      'extract' => $this->workspaceRoot.'/extract',
    ]);
    $workspaceManager->shouldReceive('cleanup');

    $downloader = $overrides['downloader'] ?? null;

    if (! $downloader) {
      $downloader = Mockery::mock(UpdatePackageDownloader::class);
      $downloader->shouldReceive('download')->andReturnUsing(function (string $url, string $archivePath) use ($packageContents): void {
        File::put($archivePath, $packageContents);
      });
    }

    $extractor = Mockery::mock(UpdatePackageExtractor::class);
    $extractor->shouldReceive('extract')->andReturn($this->workspaceRoot.'/extract/package');

    $signatureVerifier = Mockery::mock(UpdateSignatureVerifier::class);
    $signatureVerifier->shouldReceive('verify');

    $installer = $overrides['installer'] ?? null;

    if (! $installer) {
      $installer = Mockery::mock(UpdateInstaller::class);
      $installer->shouldReceive('enterMaintenance');
      $installer->shouldReceive('applyPackage');
      $installer->shouldReceive('installDependencies');
      $installer->shouldReceive('runPostInstallCommands');
      $installer->shouldReceive('verifyAppliedVersion');
      $installer->shouldReceive('finalizeAppliedPackage');
      $installer->shouldReceive('leaveMaintenance');
    }

    $backupManager = Mockery::mock(SystemBackupManager::class);
    $backupManager->shouldReceive('createPreUpdateBackup')->andReturn($overrides['backup'] ?? $this->backup());

    $restoreManager = $overrides['restoreManager'] ?? Mockery::mock(SystemBackupRestoreManager::class);

    $retention = Mockery::mock(SystemUpdateRunRetention::class);
    $retention->shouldReceive('prune');

    return new SystemUpdater(
      $serverClient,
      $versionStore,
      $workspaceManager,
      $downloader,
      $extractor,
      $signatureVerifier,
      $installer,
      $backupManager,
      $restoreManager,
      $retention,
    );
  }
}
