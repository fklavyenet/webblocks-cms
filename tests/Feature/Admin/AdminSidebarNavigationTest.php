<?php

namespace Tests\Feature\Admin;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminSidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dashboard_sidebar_renders_the_expected_group_labels_and_key_links(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('>Dashboard<', false);
        $response->assertSee('>Content<', false);
        $response->assertSee('>Reports<', false);
        $response->assertSee('>Sites<', false);
        $response->assertSee('>Access<', false);
        $response->assertSee('>Structure<', false);
        $response->assertSee('>System<', false);
        $response->assertSee('>Maintenance<', false);
        $response->assertSee('href="'.route('admin.pages.index').'"', false);
        $response->assertSee('href="'.route('admin.navigation.index').'"', false);
        $response->assertSee('href="'.route('admin.media.index').'"', false);
        $response->assertSee('href="'.route('admin.reports.visitors.index').'"', false);
        $response->assertSee('href="'.route('admin.sites.index').'"', false);
        $response->assertSee('href="'.route('admin.locales.index').'"', false);
        $response->assertSee('href="'.route('admin.users.index').'"', false);
        $response->assertSee('href="'.route('admin.block-types.index').'"', false);
        $response->assertSee('href="'.route('admin.slot-types.index').'"', false);
        $response->assertSee('href="'.route('admin.system.settings.edit').'"', false);
        $response->assertSee('href="'.route('admin.system.updates.index').'"', false);
        $response->assertSee('href="'.route('admin.system.backups.index').'"', false);
        $response->assertSee('href="'.route('admin.site-transfers.exports.index').'"', false);
    }

    #[Test]
    public function settings_page_marks_system_group_and_settings_item_active(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.system.settings.edit'));

        $response->assertOk();
        $response->assertSee('wb-nav-group-toggle is-active', false);
        $response->assertSee('>System<', false);
        $response->assertSee('href="'.route('admin.system.settings.edit').'"', false);
        $response->assertSee('class="wb-nav-group-item is-active"', false);
    }

    #[Test]
    public function locales_page_marks_sites_group_and_locales_item_active(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.locales.index'));

        $response->assertOk();
        $response->assertSee('>Sites<', false);
        $response->assertSee('href="'.route('admin.locales.index').'"', false);
        $response->assertSee('class="wb-nav-group-item is-active"', false);
    }

    #[Test]
    public function pages_page_is_grouped_under_content_and_not_listed_as_a_top_level_item(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.pages.index'));

        $response->assertOk();
        $response->assertSee('>Content<', false);
        $response->assertSee('href="'.route('admin.pages.index').'"', false);
        $response->assertSee('class="wb-nav-group-item is-active"', false);
        $response->assertDontSee('class="wb-sidebar-link is-active"', false);
    }

    #[Test]
    public function visitor_reports_page_marks_reports_group_and_item_active(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.reports.visitors.index'));

        $response->assertOk();
        $response->assertSee('href="'.route('admin.reports.visitors.index').'"', false);
        $response->assertSee('class="wb-nav-group-item is-active"', false);
        $response->assertSee('>Reports<', false);
        $response->assertSee('wb-icon-list', false);
    }

    #[Test]
    public function admin_users_navigation_item_is_visible_only_to_super_admin_users(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->editor()->create();

        $adminResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
        $adminResponse->assertOk();
        $adminResponse->assertSee('href="'.route('admin.users.index').'"', false);
        $adminResponse->assertSee('>Access<', false);
        $this->assertSame(1, substr_count($adminResponse->getContent(), 'href="'.route('admin.users.index').'"'));

        $userResponse = $this->actingAs($user)->get(route('admin.dashboard'));
        $userResponse->assertOk();
        $userResponse->assertDontSee('href="'.route('admin.users.index').'"', false);
    }

    #[Test]
    public function users_page_is_grouped_under_access_and_not_listed_as_a_top_level_item(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('href="'.route('admin.users.index').'"', false);
        $response->assertSee('class="wb-nav-group-item is-active"', false);
        $response->assertSee('>Access<', false);
        $response->assertDontSee('class="wb-sidebar-link is-active"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'href="'.route('admin.users.index').'"'));
    }

    #[Test]
    public function sites_page_is_grouped_under_sites_and_not_listed_as_a_top_level_item(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = Site::query()->where('is_primary', true)->firstOrFail();

        $response = $this->actingAs($user)->get(route('admin.sites.index'));

        $response->assertOk();
        $response->assertSee('>Sites<', false);
        $response->assertSee('href="'.route('admin.sites.index').'"', false);
        $response->assertSee('class="wb-nav-group-item is-active"', false);
        $response->assertDontSee('class="wb-sidebar-link is-active"', false);
        $response->assertSee($site->name);
    }

    #[Test]
    public function block_types_page_marks_structure_group_and_item_active(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.block-types.index'));

        $response->assertOk();
        $response->assertSee('>Structure<', false);
        $response->assertSee('href="'.route('admin.block-types.index').'"', false);
        $response->assertSee('class="wb-nav-group-item is-active"', false);
    }

    #[Test]
    public function backups_page_marks_maintenance_group_and_item_active(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.system.backups.index'));

        $response->assertOk();
        $response->assertSee('>Maintenance<', false);
        $response->assertSee('href="'.route('admin.system.backups.index').'"', false);
        $response->assertSee('class="wb-nav-group-item is-active"', false);
    }
}
