<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Install\InstallState;
use App\Support\System\InstalledVersionStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstallWizardTest extends TestCase
{
    use RefreshDatabase;

    private string $envPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envPath = storage_path('framework/testing/install-'.Str::uuid().'.env');

        if (! is_dir(dirname($this->envPath))) {
            mkdir(dirname($this->envPath), 0755, true);
        }

        file_put_contents($this->envPath, "APP_NAME=WebBlocks CMS\nAPP_ENV=testing\nAPP_URL=http://localhost\nSESSION_DRIVER=file\nCACHE_STORE=array\nQUEUE_CONNECTION=sync\n");

        Config::set('cms.install.guard_enabled', true);
        Config::set('cms.install.storage_link_enabled', false);
        Config::set('cms.install.environment_path', $this->envPath);
        Config::set('cms.install.environment_example_path', $this->envPath);
    }

    protected function tearDown(): void
    {
        if (is_file($this->envPath)) {
            unlink($this->envPath);
        }

        parent::tearDown();
    }

    #[Test]
    public function installer_is_reachable_on_a_fresh_install(): void
    {
        $response = $this->get('/install');

        $response->assertOk();
        $response->assertSee('Install WebBlocks CMS');
        $response->assertSee('Environment readiness');
    }

    #[Test]
    public function public_routes_redirect_to_install_before_setup_is_complete(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('install.core'));
    }

    #[Test]
    public function requirements_step_renders_expected_checks(): void
    {
        $response = $this->get(route('install.welcome'));

        $response->assertOk();
        $response->assertSee('PHP version');
        $response->assertSee('Environment file access');
        $response->assertSee('Runtime directories');
    }

    #[Test]
    public function database_step_validates_required_fields_for_network_drivers(): void
    {
        $response = $this->from(route('install.database'))->post(route('install.database.store'), [
            'db_connection' => 'mysql',
            'db_host' => '',
            'db_port' => '',
            'db_database' => '',
            'db_username' => '',
            'db_password' => 'secret',
        ]);

        $response->assertRedirect(route('install.database'));
        $response->assertSessionHasErrors(['db_host', 'db_port', 'db_database', 'db_username']);
    }

    #[Test]
    public function invalid_database_configuration_is_handled_clearly(): void
    {
        $response = $this->from(route('install.database'))->post(route('install.database.store'), [
            'db_connection' => 'sqlite',
            'db_database' => 'missing-dir/test.sqlite',
        ]);

        $response->assertRedirect(route('install.database'));
        $response->assertSessionHasErrors('database');
        $this->assertStringNotContainsString('db_password', (string) file_get_contents($this->envPath));
    }

    #[Test]
    public function successful_database_save_writes_env_without_re_rendering_the_password(): void
    {
        $response = $this->post(route('install.database.store'), [
            'db_connection' => 'sqlite',
            'db_database' => ':memory:',
        ]);

        $response->assertRedirect(route('install.core'));

        $contents = (string) file_get_contents($this->envPath);

        $this->assertStringContainsString('DB_CONNECTION=sqlite', $contents);
        $this->assertStringContainsString('DB_DATABASE=:memory:', $contents);
        $this->assertStringContainsString('SESSION_DRIVER=file', $contents);
    }

    #[Test]
    public function successful_core_install_runs_expected_actions(): void
    {
        $response = $this->post(route('install.core.store'));

        $response->assertRedirect(route('install.admin'));
        $response->assertSessionHas('install.core_results');

        $this->assertTrue(app(InstallState::class)->coreInstalled());
        $this->assertSame(config('app.version', 'dev'), app(InstalledVersionStore::class)->storedVersion());
    }

    #[Test]
    public function first_admin_can_be_created_through_the_installer(): void
    {
        $this->runCoreInstall();

        $response = $this->post(route('install.admin.store'), [
            'name' => 'Install Admin',
            'email' => 'install-admin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('install.finish'));

        $user = User::query()->where('email', 'install-admin@example.com')->firstOrFail();

        $this->assertSame(User::ROLE_SUPER_ADMIN, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->is_admin);
        $this->assertDatabaseHas('system_settings', ['key' => InstallState::INSTALL_COMPLETED_AT]);
    }

    #[Test]
    public function installer_routes_are_unavailable_after_install_completion(): void
    {
        $this->markInstalled();

        $response = $this->get('/install');

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function installer_cannot_create_duplicate_first_admin_after_completion(): void
    {
        $this->markInstalled();

        $response = $this->post(route('install.admin.store'), [
            'name' => 'Another Admin',
            'email' => 'another-admin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('users', ['email' => 'another-admin@example.com']);
    }

    #[Test]
    public function partial_install_state_can_resume_safely(): void
    {
        $this->runCoreInstall();

        $response = $this->get(route('install.admin'));

        $response->assertOk();
        $response->assertSee('Create the first super admin');
    }

    private function runCoreInstall(): void
    {
        $response = $this->post(route('install.core.store'));
        $response->assertRedirect(route('install.admin'));
    }

    private function markInstalled(): void
    {
        $this->runCoreInstall();

        User::factory()->superAdmin()->create();
        SystemSetting::query()->updateOrCreate(
            ['key' => InstallState::INSTALL_COMPLETED_AT],
            ['value' => now()->toIso8601String()],
        );
    }
}
