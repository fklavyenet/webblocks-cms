<?php

namespace WebBlocks\Cms\Tests;

use Illuminate\Database\Events\MigrationEnded;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use WebBlocks\Cms\Models\Concerns\HasCmsAdminAccess;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Tests\Support\FreshDatabaseSnapshots;
use WebBlocks\Cms\Tests\Support\SqliteDatabaseSnapshot;
use WebBlocks\Cms\Tests\Support\TestLifecycleProfile;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

abstract class TestCase extends Orchestra
{
  protected bool $cacheFreshDatabaseSchema = true;

  private static array $classSnapshotKeys = [];

  private int $profileStartedAt = 0;

  #[Before(PHP_INT_MAX)]
  protected function profileSetupStart(): void
  {
    $this->profileStartedAt = hrtime(true);
  }

  #[Before(PHP_INT_MIN)]
  protected function profileSetupEnd(): void
  {
    TestLifecycleProfile::record(static::class.'::'.$this->nameWithDataSet(), 'full_setup', $this->profileStartedAt);
    $this->profileStartedAt = hrtime(true);
  }

  #[After(PHP_INT_MAX)]
  protected function profileTeardownStart(): void
  {
    TestLifecycleProfile::record(static::class.'::'.$this->nameWithDataSet(), 'test_body', $this->profileStartedAt);
    $this->profileStartedAt = hrtime(true);
  }

  #[After(PHP_INT_MIN)]
  protected function profileTeardownEnd(): void
  {
    TestLifecycleProfile::record(static::class.'::'.$this->nameWithDataSet(), 'full_teardown', $this->profileStartedAt);
  }

  protected function setUp(): void
  {
    TestLifecycleProfile::measure(static::class.'::'.$this->nameWithDataSet(), 'setup', fn () => parent::setUp());
  }

  protected function tearDown(): void
  {
    TestLifecycleProfile::measure(static::class.'::'.$this->nameWithDataSet(), 'teardown', fn () => parent::tearDown());
  }

  public static function tearDownAfterClass(): void
  {
    foreach (self::$classSnapshotKeys[static::class] ?? [] as $key) {
      FreshDatabaseSnapshots::forget($key);
    }
    unset(self::$classSnapshotKeys[static::class]);
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
      || ! $this->usesSqliteInMemoryDatabaseConnection()
      || (new \ReflectionMethod($this, 'getPackageProviders'))->getDeclaringClass()->getName() !== self::class
      || $this->hasCustomMigrationListeners()) {
      TestLifecycleProfile::measure(static::class.'::'.$this->nameWithDataSet(), 'migration_bypass', fn () => parent::loadMigrationsFrom($paths), ['reason' => 'ineligible']);

      return;
    }
    $connection = $this->app['db']->connection();
    $database = $connection->getPdo();
    if ($connection->getConfig('database') !== ':memory:'
      || $connection->transactionLevel() !== 0
      || $database->inTransaction()
      || $database->query('SELECT count(*) FROM sqlite_master')->fetchColumn() !== 0) {
      TestLifecycleProfile::measure(static::class.'::'.$this->nameWithDataSet(), 'migration_bypass', fn () => parent::loadMigrationsFrom($paths), ['reason' => 'nonempty_or_transaction']);

      return;
    }
    // Configurations/providers/source identities must not share incompatible schemas.
    try {
      $boundary = getenv('WEBBLOCKS_CMS_TEST_FRESH_SCHEMA_SCOPE') === 'class' ? static::class : null;
      $key = FreshDatabaseSnapshots::key(
        $this->app['config']->all(), $this->app->getLoadedProviders(),
        FreshDatabaseSnapshots::sourceFingerprint(), $boundary,
      );
      if ($boundary !== null) {
        self::$classSnapshotKeys[static::class][$key] = $key;
      }
    } catch (\Exception) {
      TestLifecycleProfile::measure(static::class.'::'.$this->nameWithDataSet(), 'migration_bypass', fn () => parent::loadMigrationsFrom($paths), ['reason' => 'unserializable_config']);

      return;
    }
    $snapshot = FreshDatabaseSnapshots::get($key);
    if ($snapshot === null) {
      TestLifecycleProfile::measure(static::class.'::'.$this->nameWithDataSet(), 'migration_miss', fn () => parent::loadMigrationsFrom($paths), ['configuration' => $key]);
      FreshDatabaseSnapshots::put($key, TestLifecycleProfile::measure(static::class.'::'.$this->nameWithDataSet(), 'snapshot_capture', fn () => SqliteDatabaseSnapshot::capture($database)));
      // This connection is disposable. Do not run 21 reverse migrations just
      // before discarding it. Dedicated migration tests still exercise both ways.
      array_shift($this->cachedTestMigratorProcessors);
    } else {
      TestLifecycleProfile::measure(static::class.'::'.$this->nameWithDataSet(), 'snapshot_hit', fn () => $snapshot->restore($database), ['configuration' => $key]);
    }
    $this->beforeApplicationDestroyed(fn () => $connection->disconnect());
  }

  private function hasCustomMigrationListeners(): bool
  {
    foreach ([MigrationsStarted::class, MigrationsEnded::class,
      MigrationStarted::class, MigrationEnded::class] as $event) {
      if ($this->app['events']->hasListeners($event)) {
        return true;
      }
    }

    return false;
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
