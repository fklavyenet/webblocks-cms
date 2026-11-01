<?php

namespace WebBlocks\Cms\Tests\Unit;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\Install\InstallationGitRemoteGuard;
use WebBlocks\Cms\Support\System\Updates\UpdateCommandRunner;
use WebBlocks\Cms\Support\System\Updates\UpdateInstaller;
use WebBlocks\Cms\Support\System\Updates\UpdateMigrationRunner;
use WebBlocks\Cms\Support\Updates\CmsClientPostApplyRunner;

class CmsClientPostApplyRunnerTest extends TestCase
{
  use MockeryPHPUnitIntegration;

  #[Test]
  public function embedded_client_post_apply_publishes_runtime_assets_last(): void
  {
    $installer = new class(Mockery::mock(UpdateCommandRunner::class), Mockery::mock(UpdateMigrationRunner::class), Mockery::mock(InstallationGitRemoteGuard::class)) extends UpdateInstaller
    {
      /** @var list<string> */
      public array $steps = [];

      public function installDependencies(array &$output): void
      {
        $this->steps[] = 'dependencies';
      }

      public function runPostInstallCommands(array &$output): void
      {
        $this->steps[] = 'post-install';
      }

      public function syncRuntimeAssets(array &$output): void
      {
        $this->steps[] = 'assets';
      }
    };

    $runner = new CmsClientPostApplyRunner($installer);
    $output = [];
    $runner->run(dirname(__DIR__, 2), $output);

    $this->assertSame(['dependencies', 'post-install', 'assets'], $installer->steps);
  }
}
