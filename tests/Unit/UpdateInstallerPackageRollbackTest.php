<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Support\Facades\File;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Support\Install\InstallationGitRemoteGuard;
use WebBlocks\Cms\Support\System\Updates\UpdateCommandRunner;
use WebBlocks\Cms\Support\System\Updates\UpdateInstaller;
use WebBlocks\Cms\Support\System\Updates\UpdateMigrationRunner;
use WebBlocks\Cms\Tests\TestCase;

class UpdateInstallerPackageRollbackTest extends TestCase
{
  private string $targetPath;

  private string $packageRuntimePath;

  protected function setUp(): void
  {
    parent::setUp();

    $this->targetPath = storage_path('framework/testing/update-installer-rollback');
    $this->packageRuntimePath = $this->targetPath.'/vendor/fklavyenet/webblocks-cms';

    File::deleteDirectory($this->targetPath);
    File::ensureDirectoryExists($this->packageRuntimePath);
    File::put($this->packageRuntimePath.'/marker.txt', 'OLD');

    config(['webblocks-updates.installer.target_path' => $this->targetPath]);
  }

  protected function tearDown(): void
  {
    File::deleteDirectory($this->targetPath);

    parent::tearDown();
  }

  #[Test]
  public function rollback_restores_pre_update_contents_after_a_swap(): void
  {
    $installer = $this->installer();
    $packageRoot = $this->newPackageRoot('NEW');

    $output = [];
    $installer->applyPackage($packageRoot, $output);

    $this->assertSame('NEW', File::get($this->packageRuntimePath.'/marker.txt'));
    $this->assertTrue(File::isDirectory($this->packageRuntimePath.'.wb-update-old'), 'Expected the pre-update backup to be kept after a successful swap.');

    $output = [];
    $installer->rollbackAppliedPackage($output);

    $this->assertSame('OLD', File::get($this->packageRuntimePath.'/marker.txt'), 'A later failure must roll the package back to its pre-update contents.');
    $this->assertFalse(File::isDirectory($this->packageRuntimePath.'.wb-update-old'), 'The backup directory should be consumed by the rollback.');
    $this->assertTrue(
      collect($output)->contains(fn (string $line): bool => str_contains($line, 'Rolled back')),
      'Expected the rollback to be logged.',
    );
  }

  #[Test]
  public function finalize_clears_the_backup_once_the_update_flow_succeeds(): void
  {
    $installer = $this->installer();
    $packageRoot = $this->newPackageRoot('NEW');

    $output = [];
    $installer->applyPackage($packageRoot, $output);
    $this->assertTrue(File::isDirectory($this->packageRuntimePath.'.wb-update-old'));

    $output = [];
    $installer->finalizeAppliedPackage($output);

    $this->assertSame('NEW', File::get($this->packageRuntimePath.'/marker.txt'), 'Finalizing must not touch the applied package contents.');
    $this->assertFalse(File::isDirectory($this->packageRuntimePath.'.wb-update-old'), 'Expected the backup to be cleared once the update flow succeeded.');
  }

  #[Test]
  public function rollback_is_a_no_op_when_there_is_nothing_to_roll_back(): void
  {
    $installer = $this->installer();

    $output = [];
    $installer->rollbackAppliedPackage($output);

    $this->assertSame('OLD', File::get($this->packageRuntimePath.'/marker.txt'));
    $this->assertSame([], $output);
  }

  private function newPackageRoot(string $markerContents): string
  {
    $packageRoot = $this->targetPath.'/incoming-package';
    File::ensureDirectoryExists($packageRoot);
    File::put($packageRoot.'/marker.txt', $markerContents);

    return $packageRoot;
  }

  private function installer(): UpdateInstaller
  {
    return new UpdateInstaller(
      Mockery::mock(UpdateCommandRunner::class),
      Mockery::mock(UpdateMigrationRunner::class),
      Mockery::mock(InstallationGitRemoteGuard::class),
    );
  }
}
