<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Console\InstallWebBlocksCmsCommand;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageLayoutSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\Database\CmsTable;
use WebBlocks\Cms\Support\Install\InstallState;
use WebBlocks\Cms\Support\Pages\PageLayoutCatalog;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteTransferDisk;
use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\Users\EnsuresCmsUserAccess;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PackageConsumerInstallCommandTest extends TestCase
{
  use RefreshDatabase;

  private string $tempUserModelPath;

  private string $tempRoutePath;

  protected function setUp(): void
  {
    parent::setUp();

    $this->tempUserModelPath = storage_path('framework/testing/user-models/User-'.Str::uuid().'.php');
    $this->tempRoutePath = storage_path('framework/testing/routes/web-'.Str::uuid().'.php');
    File::ensureDirectoryExists(dirname($this->tempUserModelPath));
    File::ensureDirectoryExists(dirname($this->tempRoutePath));
    config()->set('webblocks-cms.install.web_routes_path', $this->tempRoutePath);
  }

  protected function tearDown(): void
  {
    $backupFiles = glob($this->tempUserModelPath.'.webblocks-cms.*.bak') ?: [];
    $routeBackupFiles = glob($this->tempRoutePath.'.webblocks-cms.*.bak') ?: [];

    foreach (array_merge([$this->tempUserModelPath, $this->tempRoutePath], $backupFiles, $routeBackupFiles) as $path) {
      if (is_file($path)) {
        @unlink($path);
      }
    }

    parent::tearDown();
  }

  #[Test]
  public function install_command_is_registered_in_consumer_mode(): void
  {
    $provider = new class($this->app) extends WebBlocksCmsServiceProvider
    {
      public array $commandsRegistered = [];

      protected function runningInsideMaintenanceRepository(): bool
      {
        return false;
      }

      public function commands($commands): void
      {
        foreach ((array) $commands as $command) {
          $this->commandsRegistered[] = $command;
        }
      }

      public function bootCommandsForTest(): void
      {
        $this->bootCommands();
      }
    };

    $provider->{'bootCommandsForTest'}();

    $this->assertContains(InstallWebBlocksCmsCommand::class, $provider->commandsRegistered);
  }

  #[Test]
  public function user_model_patcher_is_idempotent_and_creates_a_backup(): void
  {
    File::put($this->tempUserModelPath, <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
  use HasFactory, Notifiable;
}
PHP);

    config()->set('webblocks-cms.auth.user_model_path', $this->tempUserModelPath);

    $patcher = app(EnsuresCmsUserAccess::class);

    $this->assertTrue($patcher->ensure());
    $this->assertNotNull($patcher->lastBackupPath());
    $this->assertFileExists((string) $patcher->lastBackupPath());

    $contents = (string) File::get($this->tempUserModelPath);
    $this->assertStringContainsString('use WebBlocks\\Cms\\Auth\\Concerns\\HasWebBlocksCmsAccess;', $contents);
    $this->assertStringContainsString('use HasFactory, Notifiable, HasWebBlocksCmsAccess;', $contents);

    $this->assertFalse($patcher->ensure());
  }

  #[Test]
  public function unexpected_user_model_shape_fails_safely_without_modifying_the_file(): void
  {
    $original = <<<'PHP'
<?php

namespace App\Domain;

class UserProfile
{
}
PHP;

    File::put($this->tempUserModelPath, $original);

    config()->set('webblocks-cms.auth.user_model_path', $this->tempUserModelPath);

    $patcher = app(EnsuresCmsUserAccess::class);

    $this->expectExceptionMessage('Unable to patch App\\Models\\User');

    try {
      $patcher->ensure();
    } finally {
      $this->assertSame($original, (string) File::get($this->tempUserModelPath));
      $this->assertNull($patcher->lastBackupPath());
    }
  }

  #[Test]
  public function install_command_removes_stock_laravel_welcome_route_and_creates_backup(): void
  {
    File::put($this->tempRoutePath, $this->stockLaravelWelcomeRouteFile());

    $this->artisan('webblocks:install', [
      '--name' => 'Route Admin',
      '--email' => 'route-admin@example.com',
      '--password' => 'secret-password',
      '--site-name' => 'Route Site',
      '--site-handle' => 'route-site',
      '--no-interaction' => true,
      '--force' => true,
    ])
      ->expectsOutputToContain('Removed untouched Laravel welcome route so WebBlocks CMS can serve public routes.')
      ->assertExitCode(0);

    $contents = (string) File::get($this->tempRoutePath);
    $this->assertStringNotContainsString("return view('welcome');", $contents);
    $this->assertStringContainsString('use Illuminate\Support\Facades\Route;', $contents);

    $backupFiles = glob($this->tempRoutePath.'.webblocks-cms.*.bak') ?: [];
    $this->assertCount(1, $backupFiles);
    $this->assertSame($this->stockLaravelWelcomeRouteFile(), (string) File::get($backupFiles[0]));
  }

  #[Test]
  public function install_command_does_not_overwrite_custom_route_file(): void
  {
    $customRoutes = <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', [App\Http\Controllers\HomeController::class, 'index']);
Route::get('/status', fn () => 'ok');
PHP;

    File::put($this->tempRoutePath, $customRoutes);

    $this->artisan('webblocks:install', [
      '--name' => 'Custom Route Admin',
      '--email' => 'custom-route-admin@example.com',
      '--password' => 'secret-password',
      '--no-interaction' => true,
      '--force' => true,
    ])->assertExitCode(0);

    $this->assertSame($customRoutes, (string) File::get($this->tempRoutePath));
    $this->assertSame([], glob($this->tempRoutePath.'.webblocks-cms.*.bak') ?: []);
  }

  #[Test]
  public function install_command_welcome_route_cleanup_is_idempotent(): void
  {
    File::put($this->tempRoutePath, $this->stockLaravelWelcomeRouteFile());

    $this->artisan('webblocks:install', [
      '--name' => 'Idempotent Route Admin',
      '--email' => 'idempotent-route-admin@example.com',
      '--password' => 'secret-password',
      '--no-interaction' => true,
      '--force' => true,
    ])->assertExitCode(0);

    $afterFirstRun = (string) File::get($this->tempRoutePath);
    $firstBackupFiles = glob($this->tempRoutePath.'.webblocks-cms.*.bak') ?: [];

    $this->artisan('webblocks:install', [
      '--name' => 'Idempotent Route Admin Again',
      '--email' => 'idempotent-route-admin-again@example.com',
      '--password' => 'secret-password',
      '--no-interaction' => true,
    ])->assertExitCode(0);

    $this->assertSame($afterFirstRun, (string) File::get($this->tempRoutePath));
    $this->assertSame($firstBackupFiles, glob($this->tempRoutePath.'.webblocks-cms.*.bak') ?: []);
  }

  #[Test]
  public function package_fresh_install_migrations_are_discoverable_in_consumer_mode(): void
  {
    $provider = new class($this->app) extends WebBlocksCmsServiceProvider
    {
      public array $loadedMigrationPaths = [];

      protected function loadMigrationsFrom($paths): void
      {
        foreach ((array) $paths as $path) {
          $this->loadedMigrationPaths[] = $path;
        }
      }

      public function bootMigrationsForTest(): void
      {
        config()->set(self::PACKAGE_MIGRATION_LOADING_CONFIG, true);
        $this->bootMigrations();
      }
    };

    $provider->{'bootMigrationsForTest'}();

    $this->assertSame([
      base_path('packages/webblocks-cms/database/migrations/fresh'),
    ], $provider->loadedMigrationPaths);
  }

  #[Test]
  public function install_command_runs_non_interactively_and_creates_baseline_consumer_state(): void
  {
    $expectedHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

    $this->artisan('webblocks:install', [
      '--name' => 'Consumer Admin',
      '--email' => 'consumer-admin@example.com',
      '--password' => 'secret-password',
      '--site-name' => 'Consumer Site',
      '--site-handle' => 'consumer-site',
      '--no-interaction' => true,
      '--force' => true,
    ])->assertExitCode(0);

    $this->assertDatabaseHas('users', [
      'email' => 'consumer-admin@example.com',
      'role' => 'super_admin',
      'is_admin' => 1,
      'is_active' => 1,
    ]);
    $this->assertDatabaseHas(CmsTable::name('sites'), [
      'handle' => 'consumer-site',
      'name' => 'Consumer Site',
      'is_primary' => 1,
    ]);
    $this->assertDatabaseHas(CmsTable::name('site_domains'), [
      'domain' => $expectedHost !== '' ? $expectedHost : 'localhost',
      'is_primary' => 1,
      'status' => 'active',
    ]);
    $this->assertDatabaseHas(CmsTable::name('locales'), [
      'code' => 'en',
      'is_default' => 1,
      'is_enabled' => 1,
    ]);
    $this->assertDatabaseHas(CmsTable::name('system_settings'), [
      'key' => InstalledVersionStore::VERSION_KEY,
      'value' => WebBlocks::version(),
    ]);
    $this->assertDatabaseHas(CmsTable::name('system_settings'), [
      'key' => InstallState::INSTALL_COMPLETED_AT,
    ]);

    $this->assertGreaterThan(0, SlotType::query()->count());
    $this->assertGreaterThan(0, PageLayout::query()->count());
    $this->assertEmpty(array_diff(app(CoreBlockTypeCatalogSyncer::class)->slugs(), BlockType::query()->pluck('slug')->all()));
    $this->assertEmpty(array_diff($this->coreLayoutSlotTypeSlugs(), SlotType::query()->pluck('slug')->all()));
    $this->assertDatabaseHas(CmsTable::name('block_types'), ['slug' => 'card-grid', 'status' => 'draft']);
    $this->assertDatabaseHas(CmsTable::name('block_types'), ['slug' => 'navigation-auto', 'status' => 'published']);
    $this->assertTrue(Page::query()->exists());
    $this->assertFileExists(public_path('cms/brand/logo-mark.svg'));
    $this->assertFileExists(public_path('cms/brand/favicon-32x32.png'));
    $this->assertFileExists(public_path('cms/css/admin.css'));
    $this->assertFileExists(public_path('cms/js/admin/core.js'));
    $this->assertFileExists(public_path('cms/js/admin/listing-bulk-actions.js'));
    $this->assertSame(storage_path('app/site-transfers'), config('filesystems.disks.'.SiteTransferDisk::DISK.'.root'));
    $this->assertDirectoryExists(storage_path('app/site-transfers'));
  }

  #[Test]
  public function install_command_is_idempotent_and_does_not_replace_existing_admins_on_rerun(): void
  {
    $this->artisan('webblocks:install', [
      '--name' => 'First Admin',
      '--email' => 'first-admin@example.com',
      '--password' => 'secret-password',
      '--no-interaction' => true,
      '--force' => true,
    ])->assertExitCode(0);

    $firstAdmin = User::query()->where('email', 'first-admin@example.com')->firstOrFail();

    $this->artisan('webblocks:install', [
      '--name' => 'Second Admin',
      '--email' => 'second-admin@example.com',
      '--password' => 'another-secret',
      '--no-interaction' => true,
    ])->assertExitCode(0);

    $this->assertSame(1, User::query()->where('role', 'super_admin')->count());
    $this->assertSame($firstAdmin->id, User::query()->where('role', 'super_admin')->value('id'));
    $this->assertDatabaseMissing('users', ['email' => 'second-admin@example.com']);
    $this->assertSame(WebBlocks::version(), SystemSetting::query()->where('key', InstalledVersionStore::VERSION_KEY)->value('value'));
    $this->assertSame(1, Site::query()->where('handle', 'default')->orWhere('handle', 'consumer-site')->count());
  }

  #[Test]
  public function install_command_repairs_missing_core_catalog_rows_idempotently(): void
  {
    $this->artisan('webblocks:install', [
      '--name' => 'Repair Admin',
      '--email' => 'repair-admin@example.com',
      '--password' => 'secret-password',
      '--no-interaction' => true,
      '--force' => true,
    ])->assertExitCode(0);

    BlockType::query()->whereIn('slug', ['header-actions', 'card-grid', 'navigation-auto'])->delete();
    PageLayoutSlot::query()->where('slot_name', 'footer')->delete();
    SlotType::query()->where('slug', 'footer')->delete();

    $this->artisan('webblocks:install', [
      '--name' => 'Repair Admin Two',
      '--email' => 'repair-admin-two@example.com',
      '--password' => 'secret-password',
      '--no-interaction' => true,
    ])->assertExitCode(0);

    $this->assertDatabaseHas(CmsTable::name('block_types'), ['slug' => 'header-actions']);
    $this->assertDatabaseHas(CmsTable::name('block_types'), ['slug' => 'card-grid']);
    $this->assertDatabaseHas(CmsTable::name('block_types'), ['slug' => 'navigation-auto']);
    $this->assertDatabaseHas(CmsTable::name('slot_types'), ['slug' => 'footer']);
    $this->assertDatabaseHas(CmsTable::name('page_layout_slots'), ['slot_name' => 'footer']);
    $this->assertSame(1, BlockType::query()->where('slug', 'header-actions')->count());
    $this->assertSame(1, BlockType::query()->where('slug', 'card-grid')->count());
    $this->assertSame(1, BlockType::query()->where('slug', 'navigation-auto')->count());
    $this->assertSame(1, SlotType::query()->where('slug', 'footer')->count());
  }

  #[Test]
  public function install_command_creates_database_backed_laravel_support_tables_without_running_host_migrations(): void
  {
    config()->set('session.driver', 'database');
    config()->set('session.table', 'sessions');
    config()->set('cache.default', 'database');
    config()->set('cache.stores.database', [
      'driver' => 'database',
      'table' => 'cache',
      'lock_table' => 'cache_locks',
    ]);

    $this->artisan('webblocks:install', [
      '--name' => 'Support Admin',
      '--email' => 'support-admin@example.com',
      '--password' => 'secret-password',
      '--no-interaction' => true,
      '--force' => true,
    ])->assertExitCode(0);

    $this->assertTrue(Schema::hasTable('users'));
    $this->assertTrue(Schema::hasTable('password_reset_tokens'));
    $this->assertTrue(Schema::hasTable('sessions'));
    $this->assertTrue(Schema::hasTable('cache'));
    $this->assertTrue(Schema::hasTable('cache_locks'));
    $this->assertDatabaseHas('users', [
      'email' => 'support-admin@example.com',
      'role' => 'super_admin',
    ]);
    $this->assertSame(1, DB::table('migrations')->where('migration', '0001_01_01_000000_create_users_table')->count());

    $this->assertSame(0, Artisan::call('optimize:clear'));
  }

  #[Test]
  public function install_command_support_table_creation_is_idempotent(): void
  {
    config()->set('session.driver', 'database');
    config()->set('session.table', 'sessions');
    config()->set('cache.default', 'database');
    config()->set('cache.stores.database', [
      'driver' => 'database',
      'table' => 'cache',
      'lock_table' => 'cache_locks',
    ]);

    $this->artisan('webblocks:install', [
      '--name' => 'Idempotent Admin',
      '--email' => 'idempotent-admin@example.com',
      '--password' => 'secret-password',
      '--no-interaction' => true,
      '--force' => true,
    ])->assertExitCode(0);

    $this->artisan('webblocks:install', [
      '--name' => 'Idempotent Admin Two',
      '--email' => 'idempotent-admin-two@example.com',
      '--password' => 'secret-password',
      '--no-interaction' => true,
    ])->assertExitCode(0);

    $this->assertTrue(Schema::hasTable('sessions'));
    $this->assertTrue(Schema::hasTable('password_reset_tokens'));
    $this->assertTrue(Schema::hasTable('cache'));
    $this->assertTrue(Schema::hasTable('cache_locks'));
    $this->assertSame(1, User::query()->where('role', 'super_admin')->count());
  }

  private function coreLayoutSlotTypeSlugs(): array
  {
    return collect(PageLayoutCatalog::definitions())
      ->flatMap(fn (array $definition) => collect($definition['managed_slots'] ?? [])->pluck('slot_type_slug'))
      ->filter()
      ->unique()
      ->values()
      ->all();
  }

  private function stockLaravelWelcomeRouteFile(): string
  {
    return <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
  return view('welcome');
});
PHP;
  }
}
