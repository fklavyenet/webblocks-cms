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
    public function dashboard_sidebar_renders_direct_editorial_links_and_the_new_system_groups(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $content = $response->getContent();
        $reportsHref = 'href="'.route('admin.reports.visitors.index').'"';
        $settingsHref = 'href="'.route('admin.system.settings.edit').'"';
        $backupsHref = 'href="'.route('admin.system.backups.index').'"';
        $transfersHref = 'href="'.route('admin.site-transfers.exports.index').'"';
        $updatesHref = 'href="'.route('admin.system.updates.index').'"';
        $usersHref = 'href="'.route('admin.users.index').'"';
        $sitesHref = 'href="'.route('admin.sites.index').'"';
        $localesHref = 'href="'.route('admin.locales.index').'"';
        $slotTypesHref = 'href="'.route('admin.slot-types.index').'"';
        $layoutTypesHref = 'href="'.route('admin.layout-types.index').'"';
        $blockTypesHref = 'href="'.route('admin.block-types.index').'"';

        $response->assertOk();
        $response->assertSee('>Dashboard<', false);
        $response->assertSee('href="'.route('admin.pages.index').'"', false);
        $response->assertSee('href="'.route('admin.navigation.index').'"', false);
        $response->assertSee('href="'.route('admin.media.index').'"', false);
        $response->assertSee('href="'.route('admin.contact-messages.index').'"', false);
        $response->assertSee('>System<', false);
        $response->assertSee('>Maintenance<', false);
        $response->assertSee('href="'.route('admin.reports.visitors.index').'"', false);
        $response->assertSee('href="'.route('admin.system.settings.edit').'"', false);
        $response->assertSee('href="'.route('admin.system.backups.index').'"', false);
        $response->assertSee('href="'.route('admin.site-transfers.exports.index').'"', false);
        $response->assertSee('href="'.route('admin.system.updates.index').'"', false);
        $response->assertSee('href="'.route('admin.users.index').'"', false);
        $response->assertSee('href="'.route('admin.sites.index').'"', false);
        $response->assertSee('href="'.route('admin.locales.index').'"', false);
        $response->assertSee('href="'.route('admin.slot-types.index').'"', false);
        $response->assertSee('href="'.route('admin.layout-types.index').'"', false);
        $response->assertSee('href="'.route('admin.block-types.index').'"', false);
        $response->assertDontSee('>Reports<', false);
        $response->assertDontSee('>Access<', false);
        $response->assertDontSee('>Structure<', false);
        $this->assertSame(1, substr_count($content, $usersHref));
        $this->assertTrue(
            strpos($content, $reportsHref) < strpos($content, $settingsHref)
            && strpos($content, $settingsHref) < strpos($content, $backupsHref)
            && strpos($content, $backupsHref) < strpos($content, $transfersHref)
            && strpos($content, $transfersHref) < strpos($content, $updatesHref)
        );
        $this->assertTrue(
            strpos($content, $usersHref) < strpos($content, $sitesHref)
            && strpos($content, $sitesHref) < strpos($content, $localesHref)
            && strpos($content, $localesHref) < strpos($content, $slotTypesHref)
            && strpos($content, $slotTypesHref) < strpos($content, $layoutTypesHref)
            && strpos($content, $layoutTypesHref) < strpos($content, $blockTypesHref)
        );
        $this->assertTrue(
            strpos($content, '>System<') < strpos($content, '>Maintenance<')
        );
    }

    #[Test]
    public function settings_page_marks_maintenance_group_and_settings_item_active(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.system.settings.edit'));

        $response->assertOk();
        $response->assertSee('wb-nav-group-toggle is-active', false);
        $response->assertSee('>Maintenance<', false);
        $response->assertSee('href="'.route('admin.system.settings.edit').'"', false);
        $response->assertSee('class="wb-nav-group-item is-active"', false);
    }

    #[Test]
    public function locales_page_marks_system_group_and_locales_item_active(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.locales.index'));

        $response->assertOk();
        $response->assertSee('>System<', false);
        $response->assertSee('href="'.route('admin.locales.index').'"', false);
        $response->assertSee('class="wb-nav-group-item is-active"', false);
    }

    #[Test]
    public function pages_page_is_a_direct_top_level_sidebar_item(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.pages.index'));

        $response->assertOk();
        $response->assertSee('href="'.route('admin.pages.index').'"', false);
        $response->assertSee('class="wb-sidebar-link is-active"', false);
    }

    #[Test]
    public function visitor_reports_page_marks_maintenance_group_and_item_active(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.reports.visitors.index'));

        $response->assertOk();
        $response->assertSee('class="wb-nav-group-item is-active"', false);
        $response->assertSee('>Maintenance<', false);
        $response->assertSee('wb-icon-file', false);
    }

    #[Test]
    public function admin_users_navigation_item_is_visible_only_to_super_admin_users(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->editor()->create();

        $adminResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
        $adminResponse->assertOk();
        $adminResponse->assertSee('href="'.route('admin.users.index').'"', false);
        $adminResponse->assertSee('>System<', false);
        $this->assertSame(1, substr_count($adminResponse->getContent(), 'href="'.route('admin.users.index').'"'));

        $userResponse = $this->actingAs($user)->get(route('admin.dashboard'));
        $userResponse->assertOk();
        $userResponse->assertDontSee('href="'.route('admin.users.index').'"', false);
    }

    #[Test]
    public function users_page_is_grouped_under_system_and_not_listed_as_a_top_level_item(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('href="'.route('admin.users.index').'"', false);
        $response->assertSee('class="wb-nav-group-item is-active"', false);
        $response->assertSee('>System<', false);
        $response->assertDontSee('class="wb-sidebar-link is-active"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'href="'.route('admin.users.index').'"'));
    }

    #[Test]
    public function sites_page_is_grouped_under_system_and_not_listed_as_a_top_level_item(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = Site::query()->where('is_primary', true)->firstOrFail();

        $response = $this->actingAs($user)->get(route('admin.sites.index'));

        $response->assertOk();
        $response->assertSee('>System<', false);
        $response->assertSee('href="'.route('admin.sites.index').'"', false);
        $response->assertSee('class="wb-nav-group-item is-active"', false);
        $response->assertDontSee('class="wb-sidebar-link is-active"', false);
        $response->assertSee($site->name);
    }

    #[Test]
    public function block_types_page_marks_system_group_and_item_active(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.block-types.index'));

        $response->assertOk();
        $response->assertSee('>System<', false);
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
