<?php

namespace WebBlocks\Cms\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\System\Updates\CmsComposerState;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\System\Updates\UpdateInstaller;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateResult;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\TestCase;

class SystemUpdateConsoleTest extends TestCase
{
  public function test_cli_restores_cms_configuration_before_running_the_same_publisher_engine(): void
  {
    config()->set('publisher-client.product', 'foreign-product');
    config()->set('publisher-client.version.resolver', 'Foreign\\Resolver');
    $engine = Mockery::mock(SystemUpdater::class);
    $engine->expects('run')->andReturnUsing(function (): UpdateResult {
      $this->assertSame('webblocks-cms', config('publisher-client.product'));
      $this->assertSame('composer-package', config('publisher-client.apply.strategy'));

      return new UpdateResult('1.0.0', WebBlocks::version(), 'success', 'Complete update finished.', '', 0, CarbonImmutable::now(), CarbonImmutable::now(), 1, null);
    });
    app()->instance(SystemUpdater::class, $engine);

    $this->artisan('webblocks:updates:run', ['--force' => true])
      ->expectsOutput('Complete update finished.')
      ->assertExitCode(0);
  }

  public function test_finalizer_runs_the_product_steps_only_after_composer_consistency_validation(): void
  {
    $this->mock(CmsComposerState::class)->expects('assertCurrent')->ordered();
    $installer = $this->mock(UpdateInstaller::class);
    $installer->expects('installDependencies')->with(Mockery::type('array'))->ordered();
    $installer->expects('runPostInstallCommands')->with(Mockery::type('array'))->ordered()->andReturnUsing(function (): void {
      Http::preventStrayRequests();
      try {
        app(SystemUpdater::class)->run();
        $this->fail('Publisher took over an active finalizer lock.');
      } catch (\WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException $exception) {
        $this->assertStringContainsString('Another update is already running', $exception->userMessage());
      }
    });
    $installer->expects('syncRuntimeAssets')->with(Mockery::type('array'))->ordered();
    $this->mock(InstalledVersionStore::class)->expects('persist')->with(WebBlocks::version())->ordered();

    $this->artisan('webblocks:updates:finalize', ['--force' => true])->assertExitCode(0);
    $lock = Cache::lock('system-updates:run', 5);
    $this->assertTrue($lock->get());
    $lock->release();
    $this->assertNull(Cache::get('system-updates:run:heartbeat'));
  }

  public function test_inconsistent_composer_installation_cannot_run_migrations(): void
  {
    $this->mock(CmsComposerState::class)->expects('assertCurrent')->andThrow(new UpdateException('Synthetic inconsistent install.'));
    $this->mock(UpdateInstaller::class)->shouldNotReceive('runPostInstallCommands');
    $this->mock(InstalledVersionStore::class)->shouldNotReceive('persist');

    $this->artisan('webblocks:updates:finalize', ['--force' => true])->assertExitCode(1);
  }

  public function test_finalizer_cannot_overlap_a_publisher_update(): void
  {
    $lock = Cache::lock('system-updates:run', 60);
    $this->assertTrue($lock->get());
    $this->mock(CmsComposerState::class)->shouldNotReceive('assertCurrent');
    try {
      $this->artisan('webblocks:updates:finalize', ['--force' => true])->assertExitCode(1);
    } finally {
      $lock->release();
    }
  }
}
