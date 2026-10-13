<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\File;
use RuntimeException;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Plugins\PluginDependencyManager;
use WebBlocks\Cms\Tests\TestCase;

class PluginDependencyLifecycleTest extends TestCase
{
  private string $root;

  protected function defineDatabaseMigrations(): void
  {
  $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
  parent::setUp();
  $this->root = sys_get_temp_dir().'/wb-dependencies-'.bin2hex(random_bytes(6));
  config(['webblocks-plugins.install.root' => $this->root]);
  }

  protected function tearDown(): void
  {
  File::deleteDirectory($this->root);
  parent::tearDown();
  }

  private function package(string $handle, array $dependencies = [], string $version = '1.0.0'): array
  {
    $manifest = ['handle' => $handle, 'label' => $handle, 'version' => $version, 'dependencies' => $dependencies];
    $path = $this->root.'/'.$handle.'/'.$version;
    File::ensureDirectoryExists($path);
    File::put($path.'/webblocks-plugin.json', json_encode($manifest));

    return $manifest;
  }

  public function test_required_provider_cannot_be_disabled_or_replaced_under_a_live_consumer(): void
  {
    $this->package('provider');
    $this->package('consumer', ['required' => ['provider' => '^1.0']]);
    $repository = app(InstalledPluginRepository::class);
    try {
    $repository->enable('consumer', '1.0.0');
    $this->fail('Missing provider was accepted.');
    } catch (RuntimeException) {
    $this->assertNull($repository->enabledVersion('consumer'));
    }
    $repository->enable('provider', '1.0.0');
    $repository->enable('consumer', '1.0.0');
    try {
    $repository->disable('provider');
    $this->fail('Live dependency was disabled.');
    } catch (RuntimeException) {
    $this->assertSame('1.0.0', $repository->enabledVersion('provider'));
    }
    $replacement = $this->package('provider', version: '2.0.0');
    try {
    app(PluginDependencyManager::class)->assertReplacement($replacement);
    $this->fail('Incompatible replacement was accepted.');
    } catch (RuntimeException) {
    $this->assertSame('1.0.0', $repository->enabledVersion('provider'));
    }
    $repository->disable('consumer');
    $repository->disable('provider');
    $this->assertNull($repository->enabledVersion('provider'));
  }

  public function test_optional_consumer_does_not_block_provider_disable(): void
  {
    $this->package('provider');
    $this->package('consumer', ['optional' => ['provider' => '^1.0']]);
    $repository = app(InstalledPluginRepository::class);
    $repository->enable('consumer', '1.0.0');
    $repository->enable('provider', '1.0.0');
    $repository->disable('provider');
    $this->assertSame('1.0.0', $repository->enabledVersion('consumer'));
  }

  public function test_quarantine_can_stop_a_broken_required_provider(): void
  {
    $this->package('provider');
    $this->package('consumer', ['required' => ['provider' => '^1.0']]);
    $repository = app(InstalledPluginRepository::class);
    $repository->enable('provider', '1.0.0');
    $repository->enable('consumer', '1.0.0');
    $repository->quarantine('provider', '1.0.0');
    $this->assertTrue($repository->isDisabled('provider'));
  }

  public function test_failed_provider_setup_cannot_activate_a_required_consumer(): void
  {
    $this->package('provider');
    $this->package('consumer', ['required' => ['provider' => '^1.0']]);
    $repository = app(InstalledPluginRepository::class);
    $repository->enable('provider', '1.0.0');
    $repository->recordSetupResult('provider', '1.0.0', ['status' => 'failed']);
    $this->expectException(RuntimeException::class);
    $repository->enable('consumer', '1.0.0');
  }
}
