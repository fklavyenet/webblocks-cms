<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Support\Install\InstallationGitRemoteGuard;
use WebBlocks\Cms\Support\System\Updates\UpdateCommandRunner;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\System\Updates\UpdateInstaller;
use WebBlocks\Cms\Support\System\Updates\UpdateMigrationRunner;
use WebBlocks\Cms\Tests\TestCase;

class UpdateInstallerCatalogSyncTest extends TestCase
{
  #[Test]
  public function post_install_syncs_shipped_catalog_after_cache_clears(): void
  {
    $recorder = $this->recordingCommandRunner();
    $installer = new UpdateInstaller(
      $recorder,
      $this->noopMigrationRunner($recorder),
      $this->noopGitRemoteGuard(),
    );

    $output = [];
    $installer->runPostInstallCommands($output);

    $catalogIndex = null;
    $lastClearIndex = null;

    foreach ($recorder->commands as $index => $command) {
      if (in_array('webblocks:catalog-repair', $command, true) && in_array('--all', $command, true)) {
        $catalogIndex = $index;
      }

      if (in_array('optimize:clear', $command, true)) {
        $lastClearIndex = $index;
      }
    }

    $this->assertNotNull($catalogIndex, 'Expected the post-install flow to run webblocks:catalog-repair --all.');
    $this->assertNotNull($lastClearIndex, 'Expected the post-install flow to run the cache/optimize clears.');
    $this->assertGreaterThan(
      $lastClearIndex,
      $catalogIndex,
      'Catalog sync must run after cache/optimize clears so the subprocess boots a rebuilt service manifest.',
    );
  }

  #[Test]
  public function catalog_sync_failure_does_not_fail_the_update(): void
  {
    $recorder = new class extends UpdateCommandRunner
    {
      public function run(array $command, string $workingDirectory, array &$output): void
      {
        if (in_array('webblocks:catalog-repair', $command, true)) {
          throw new UpdateException('The update command sequence failed.', 'Command failed: catalog-repair');
        }

        $output[] = 'ok';
      }
    };

    $installer = new UpdateInstaller(
      $recorder,
      $this->noopMigrationRunner($recorder),
      $this->noopGitRemoteGuard(),
    );

    $output = [];
    $installer->runPostInstallCommands($output);

    $this->assertTrue(
      collect($output)->contains(fn (string $line): bool => str_contains($line, 'webblocks:catalog-repair')),
      'A best-effort catalog sync failure should be logged with manual re-run guidance instead of throwing.',
    );
  }

  private function recordingCommandRunner(): UpdateCommandRunner
  {
    return new class extends UpdateCommandRunner
    {
      /** @var array<int, array<int, string>> */
      public array $commands = [];

      public function run(array $command, string $workingDirectory, array &$output): void
      {
        $this->commands[] = $command;
        $output[] = '$ '.implode(' ', $command);
      }
    };
  }

  private function noopMigrationRunner(UpdateCommandRunner $commandRunner): UpdateMigrationRunner
  {
    return new class($commandRunner) extends UpdateMigrationRunner
    {
      public function run(string $targetPath, array &$output): void
      {
        $output[] = 'migrations (faked)';
      }
    };
  }

  private function noopGitRemoteGuard(): InstallationGitRemoteGuard
  {
    return new class extends InstallationGitRemoteGuard
    {
      public function protectCurrentInstall(?string $repositoryPath = null, array &$output = []): bool
      {
        return true;
      }
    };
  }
}
