<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Models\VisitorEvent;
use App\Support\WebBlocks;
use Carbon\CarbonImmutable;
use App\Support\System\InstalledVersionStore;
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
        $user = User::factory()->editor()->create();
        $site = Site::query()->where('is_primary', true)->firstOrFail();
        app(InstalledVersionStore::class)->persist('0.1.4');

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Dashboard Landing',
            'slug' => 'dashboard-landing',
            'status' => 'published',
        ]);

        VisitorEvent::query()->create([
            'site_id' => $page->site_id,
            'page_id' => $page->id,
            'path' => '/p/dashboard-landing',
            'session_key' => 'dashboard-session',
            'ip_hash' => 'dashboard-hash',
            'visited_at' => CarbonImmutable::today()->setTime(9, 0),
        ]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
        $response->assertSee('Dashboard');
        $response->assertSee('WebBlocks CMS v'.WebBlocks::version());
        $response->assertSee('Visitor Summary');
        $response->assertSee('/p/dashboard-landing');
        $response->assertSee('Actions and Shortcuts');
        $response->assertSee('href="'.route('admin.pages.create').'"', false);
        $response->assertSee('New Page');
        $response->assertSee('href="'.route('admin.pages.index').'"', false);
        $response->assertSee('Pages');
        $response->assertDontSee('href="'.route('admin.system.updates.index').'"', false);
        $response->assertSee('Sites, backups, and system updates are available to super admins only.');

        $content = $response->getContent();

        $this->assertNotFalse($content);
        $this->assertLessThan(strpos($content, 'Recent Pages'), strpos($content, 'Actions and Shortcuts'));
        $this->assertLessThan(strpos($content, 'Recent Media'), strpos($content, 'Overview'));
        $this->assertLessThan(strpos($content, 'Visitor Summary'), strpos($content, 'Recent Pages'));
    }

    #[Test]
    public function dashboard_shortcuts_include_system_links_for_super_admins(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Actions and Shortcuts');
        $response->assertSee('href="'.route('admin.pages.create').'"', false);
        $response->assertSee('New Page');
        $response->assertSee('href="'.route('admin.pages.index').'"', false);
        $response->assertSee('Pages');
        $response->assertSee('href="'.route('admin.sites.index').'"', false);
        $response->assertSee('Sites');
        $response->assertSee('href="'.route('admin.system.backups.index').'"', false);
        $response->assertSee('Backups');
        $response->assertSee('href="'.route('admin.system.updates.index').'"', false);
        $response->assertSee('Update');
    }

    #[Test]
    public function admin_dashboard_legacy_path_redirects_to_canonical_admin_path(): void
    {
        $user = User::factory()->editor()->create();

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
    public function admin_layout_resets_transient_overlay_and_sidebar_state_on_restore(): void
    {
        $user = User::factory()->editor()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
        $response->assertSee('assets/webblocks-cms/js/admin/core.js', false);
        $response->assertDontSee('function resetAdminTransientUiState()', false);
        $response->assertDontSee("document.body.classList.remove('wb-overlay-lock', 'overflow-y-hidden');", false);
        $response->assertDontSee("window.addEventListener('pageshow'", false);
        $response->assertDontSee("document.querySelectorAll('[data-wb-sidebar-backdrop]')", false);
        $response->assertDontSee("overlayRoot.querySelector('.wb-overlay-layer--dialog > .wb-overlay-backdrop')", false);
        $response->assertDontSee("overlayRoot.querySelectorAll('[data-wb-overlay-runtime=\"true\"]')", false);
    }

    #[Test]
    public function top_level_dashboard_redirect_uses_canonical_admin_path(): void
    {
        $user = User::factory()->editor()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }
}
