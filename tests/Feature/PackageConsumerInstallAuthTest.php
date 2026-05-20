<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PackageConsumerInstallAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('webblocks:install', [
            '--name' => 'Auth Admin',
            '--email' => 'auth-admin@example.com',
            '--password' => 'secret-password',
            '--site-name' => 'Auth Site',
            '--site-handle' => 'auth-site',
            '--no-interaction' => true,
            '--force' => true,
        ])->assertExitCode(0);
    }

    #[Test]
    public function package_login_route_and_view_exist_after_install(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('login');
        $response->assertSee('email');
    }

    #[Test]
    public function admin_route_redirects_guests_to_the_login_page(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function first_super_admin_can_log_in_and_reach_the_admin_dashboard(): void
    {
        $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();

        $loginResponse = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $loginResponse->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);

        $dashboard = $this->actingAs($user)->get(route('admin.dashboard'));

        $dashboard->assertOk();
        $dashboard->assertSee('Dashboard');
    }

    #[Test]
    public function public_home_route_renders_after_install(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Install WebBlocks CMS');
    }
}
