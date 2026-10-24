<?php

namespace WebBlocks\Cms\Tests;

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;
use WebBlocks\Cms\Models\Concerns\HasCmsAdminAccess;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Tests\Support\SqliteDatabaseSnapshot;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

abstract class TestCase extends Orchestra
{
  protected bool $cacheFreshDatabaseSchema = true;

  private static array $freshDatabaseSnapshots = [];

  public static function tearDownAfterClass(): void
  {
    unset(self::$freshDatabaseSnapshots[static::class]);
    parent::tearDownAfterClass();
  }

  protected function loadMigrationsFrom(array|string $paths): void
  {
    // Keep migration/restore profiles, other engines, custom schemas and Laravel's
    // transaction-based RefreshDatabase lifecycle on Testbench's original path.
    if (! $this->cacheFreshDatabaseSchema
      || getenv('WEBBLOCKS_CMS_TEST_FRESH_SCHEMA_CACHE') === '0'
      || $paths !== dirname(__DIR__).'/database/migrations/fresh'
      || static::usesRefreshDatabaseTestingConcern()
      || ! $this->usesSqliteInMemoryDatabaseConnection()) {
      parent::loadMigrationsFrom($paths);

      return;
    }
    $connection = $this->app['db']->connection();
    $database = $connection->getPdo();
    if ($connection->getConfig('database') !== ':memory:'
      || $connection->transactionLevel() !== 0
      || $database->inTransaction()
      || $database->query('SELECT count(*) FROM sqlite_master')->fetchColumn() !== 0) {
      parent::loadMigrationsFrom($paths);

      return;
    }
    // Configuration variants within a class must get their own real migration run.
    try {
      $key = hash('sha256', serialize($this->app['config']->all()));
    } catch (\Exception) {
      parent::loadMigrationsFrom($paths);

      return;
    }
    $snapshot = self::$freshDatabaseSnapshots[static::class][$key] ?? null;
    if ($snapshot === null) {
      parent::loadMigrationsFrom($paths);
      self::$freshDatabaseSnapshots[static::class][$key] = SqliteDatabaseSnapshot::capture($database);
      // This connection is disposable. Do not run 21 reverse migrations just
      // before discarding it. Dedicated migration tests still exercise both ways.
      array_shift($this->cachedTestMigratorProcessors);
    } else {
      $snapshot->restore($database);
    }
    $this->beforeApplicationDestroyed(fn () => $connection->disconnect());
  }

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

  protected function cmsActor($actor, ?array $siteIds = null)
  {
    if (! app(CmsAuthorization::class)->ready()) {
    return $actor;
    }
    $role = $actor->role ?? 'editor';
    $id = $actor->id ?? (int) DB::table('users')->max('id') + 1;
    DB::table('users')->updateOrInsert(['id' => $id], ['name' => $actor->name ?? 'Synthetic operator', 'email' => 'operator-'.$id.'@example.test', 'password' => 'unused', 'role' => $role, 'is_active' => $actor->is_active ?? true, 'is_admin' => $role === 'super_admin']);
    $actor->id = $id;
    $actor->role = $role;
    $actor->is_active = $actor->is_active ?? true;
    if ($role === 'super_admin') {
      DB::table('wbcms_acl_system_administrators')->insertOrIgnore(['user_id' => $id]);
    } else {
      DB::table('wbcms_acl_system_administrators')->where('user_id', $id)->delete();
      if (method_exists($actor, 'sites')) {
        $roleId = DB::table('wbcms_acl_roles')->where('handle', $role)->value('id');
        $selectedSites = $siteIds === null ? ($actor->getRelationValue('sites') ?? []) : Site::query()->whereIn('id', $siteIds)->get();
        if ($siteIds !== null) {
        DB::table('wbcms_site_user')->where('user_id', $id)->delete();
        }
        foreach ($selectedSites as $site) {
          DB::table('wbcms_site_user')->updateOrInsert(['user_id' => $id, 'site_id' => $site->id], ['role_id' => $roleId]);
        }
      }
    }

    return $actor;
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

class CmsSqlTestUser extends User
{
  use HasCmsAdminAccess;

  protected $guarded = [];
}
