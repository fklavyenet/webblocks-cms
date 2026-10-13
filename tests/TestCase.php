<?php

namespace WebBlocks\Cms\Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Orchestra\Testbench\TestCase as Orchestra;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

abstract class TestCase extends Orchestra
{
  /** Legacy-copy/backfill fixtures deliberately exercise the 1.98.7 bridge schema. */
  protected function loadRelationalBridgeFixtureMigrations(): void
  {
    $paths = array_values(array_filter(glob(dirname(__DIR__).'/database/migrations/fresh/*.php'),
      fn ($path) => ! str_ends_with($path, '_retire_legacy_json_persistence.php')));
    $register = static::usesRefreshDatabaseTestingConcern()
      && ! RefreshDatabaseState::$migrated
      && ! RefreshDatabaseState::$lazilyRefreshed;
    $this->loadMigrationsFrom($register ? $paths : ['--path' => $paths]);
  }

  protected function getPackageProviders($app): array
  {
    return [WebBlocksCmsServiceProvider::class];
  }

  protected function defineEnvironment($app): void
  {
    $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('w', 32)));
    $app['config']->set('cache.default', 'array');
    $app['config']->set('database.default', 'sqlite');
    $app['config']->set('database.connections.sqlite', [
      'driver' => 'sqlite',
      'database' => ':memory:',
      'prefix' => '',
      'foreign_key_constraints' => true,
    ]);
    $app['config']->set('mail.default', 'array');
    $app['config']->set('queue.default', 'sync');
    $app['config']->set('session.driver', 'array');
    $app['config']->set('webblocks-cms.diagnostics.enabled', false);
    $app['config']->set('webblocks-cms.routes.admin', false);
    $app['config']->set('webblocks-cms.routes.public', false);
    $app['config']->set('webblocks-cms.migrations.load', false);
    $app['config']->set('webblocks-cms.install.load_routes', false);
    $app['config']->set('webblocks-plugins.enabled', []);
    $app['config']->set('webblocks-updates.enabled', false);
  }
}
