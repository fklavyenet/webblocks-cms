<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminDashboardRouteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_dashboard_route_name_points_to_canonical_admin_path(): void
    {
        $this->assertSame('/admin', route('admin.dashboard', absolute: false));
    }

    #[Test]
    public function admin_root_opens_dashboard_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
        $response->assertSee('Dashboard');
    }

    #[Test]
    public function admin_dashboard_legacy_path_redirects_to_canonical_admin_path(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    #[Test]
    public function guests_are_redirected_to_login_from_canonical_admin_path(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function top_level_dashboard_redirect_uses_canonical_admin_path(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }
}
