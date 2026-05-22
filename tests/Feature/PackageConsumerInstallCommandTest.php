<?php

namespace Tests\Feature;

use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Models\SystemSetting;
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
use WebBlocks\Cms\Support\Install\InstallState;
use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\Users\EnsuresCmsUserAccess;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PackageConsumerInstallCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $tempUserModelPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempUserModelPath = storage_path('framework/testing/user-models/User-'.Str::uuid().'.php');
        File::ensureDirectoryExists(dirname($this->tempUserModelPath));
    }

    protected function tearDown(): void
    {
        $backupFiles = glob($this->tempUserModelPath.'.webblocks-cms.*.bak') ?: [];

        foreach (array_merge([$this->tempUserModelPath], $backupFiles) as $path) {
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
        $this->assertDatabaseHas('sites', [
            'handle' => 'consumer-site',
            'name' => 'Consumer Site',
            'is_primary' => 1,
        ]);
        $this->assertDatabaseHas('site_domains', [
            'domain' => $expectedHost !== '' ? $expectedHost : 'localhost',
            'is_primary' => 1,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('locales', [
            'code' => 'en',
            'is_default' => 1,
            'is_enabled' => 1,
        ]);
        $this->assertDatabaseHas('system_settings', [
            'key' => InstalledVersionStore::VERSION_KEY,
            'value' => WebBlocks::version(),
        ]);
        $this->assertDatabaseHas('system_settings', [
            'key' => InstallState::INSTALL_COMPLETED_AT,
        ]);

        $this->assertGreaterThan(0, SlotType::query()->count());
        $this->assertGreaterThan(0, PageLayout::query()->count());
        $this->assertTrue(Page::query()->exists());
        $this->assertFileExists(public_path('cms/css/admin.css'));
        $this->assertFileExists(public_path('cms/js/admin/core.js'));
        $this->assertFileExists(public_path('cms/js/admin/listing-bulk-actions.js'));
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
        $this->assertTrue(Schema::hasTable('cache'));
        $this->assertTrue(Schema::hasTable('cache_locks'));
        $this->assertSame(1, User::query()->where('role', 'super_admin')->count());
    }
}
