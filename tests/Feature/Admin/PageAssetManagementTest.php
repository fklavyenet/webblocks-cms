<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\PageAsset;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageAssetManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function page_assets_tab_renders_compact_table_and_not_large_inline_editable_rows(): void
    {
        $page = $this->draftPage();
        PageAsset::query()->create([
            'page_id' => $page->id,
            'type' => 'css',
            'path' => '/site/default/playground/playground.css',
            'load_position' => 'head',
            'is_enabled' => true,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']));

        $response->assertOk();
        $response->assertSee('Page Assets');
        $response->assertSee('Add CSS asset');
        $response->assertSee('Add JS asset');
        $response->assertSee('<th>Type</th>', false);
        $response->assertSee('<th>Path</th>', false);
        $response->assertSee('<th>Loading</th>', false);
        $response->assertSee('<th>Status</th>', false);
        $response->assertSee('<th>Sort</th>', false);
        $response->assertSee('<th>Actions</th>', false);
        $response->assertSee('/site/default/playground/playground.css', false);
        $response->assertDontSee('name="page_assets[', false);
        $response->assertDontSee('Remove row');
        $response->assertDontSee('name="page_assets[0][type]"', false);
    }

    #[Test]
    public function empty_state_renders_when_no_page_assets_exist(): void
    {
        $page = $this->draftPage();

        $response = $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']));

        $response->assertOk();
        $response->assertSee('No page assets yet.');
        $response->assertSee('Add CSS asset');
        $response->assertSee('Add JS asset');
    }

    #[Test]
    public function super_admin_can_create_css_asset_from_dedicated_endpoint(): void
    {
        $page = $this->draftPage();

        $response = $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('admin.pages.assets.store', ['page' => $page, 'type' => 'css']), [
                'path' => '/site/webblocksui/playground/playground.css',
                'sort_order' => 3,
                'is_enabled' => '1',
                '_page_asset_close_url' => route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']),
            ]);

        $response->assertRedirect(route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']));
        $this->assertDatabaseHas('page_assets', [
            'page_id' => $page->id,
            'type' => 'css',
            'path' => '/site/webblocksui/playground/playground.css',
            'sort_order' => 3,
            'is_enabled' => true,
            'is_defer' => false,
            'is_async' => false,
            'is_module' => false,
        ]);
    }

    #[Test]
    public function super_admin_can_create_js_asset_with_default_defer_enabled(): void
    {
        $page = $this->draftPage();

        $response = $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('admin.pages.assets.store', ['page' => $page, 'type' => 'js']), [
                'path' => '/site/webblocksui/playground/playground.js',
                'sort_order' => 1,
                'is_enabled' => '1',
                '_page_asset_close_url' => route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']),
            ]);

        $response->assertRedirect(route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']));
        $this->assertDatabaseHas('page_assets', [
            'page_id' => $page->id,
            'type' => 'js',
            'path' => '/site/webblocksui/playground/playground.js',
            'is_defer' => true,
            'is_async' => false,
            'is_module' => false,
        ]);
    }

    #[Test]
    public function super_admin_can_update_existing_asset_and_js_flags(): void
    {
        $page = $this->draftPage();
        $asset = PageAsset::query()->create([
            'page_id' => $page->id,
            'type' => 'js',
            'path' => '/site/default/playground/original.js',
            'load_position' => 'body_end',
            'is_enabled' => true,
            'is_defer' => true,
            'is_async' => false,
            'is_module' => false,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs(User::factory()->superAdmin()->create())
            ->put(route('admin.pages.assets.update', ['page' => $page, 'page_asset' => $asset]), [
                'path' => '/site/default/playground/updated.js',
                'sort_order' => 8,
                'is_enabled' => '0',
                'is_defer' => '0',
                'is_async' => '1',
                'is_module' => '1',
                '_page_asset_close_url' => route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']),
            ]);

        $response->assertRedirect(route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']));
        $this->assertDatabaseHas('page_assets', [
            'id' => $asset->id,
            'path' => '/site/default/playground/updated.js',
            'sort_order' => 8,
            'is_enabled' => false,
            'is_defer' => false,
            'is_async' => true,
            'is_module' => true,
        ]);
    }

    #[Test]
    public function css_edit_does_not_persist_js_only_flags(): void
    {
        $page = $this->draftPage();
        $asset = PageAsset::query()->create([
            'page_id' => $page->id,
            'type' => 'css',
            'path' => '/site/default/playground/original.css',
            'load_position' => 'head',
            'is_enabled' => true,
            'is_defer' => false,
            'is_async' => false,
            'is_module' => false,
            'sort_order' => 0,
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put(route('admin.pages.assets.update', ['page' => $page, 'page_asset' => $asset]), [
                'path' => '/site/default/playground/updated.css',
                'sort_order' => 2,
                'is_enabled' => '1',
                'is_defer' => '1',
                'is_async' => '1',
                'is_module' => '1',
                '_page_asset_close_url' => route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']),
            ])
            ->assertRedirect(route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']));

        $this->assertDatabaseHas('page_assets', [
            'id' => $asset->id,
            'type' => 'css',
            'path' => '/site/default/playground/updated.css',
            'is_defer' => false,
            'is_async' => false,
            'is_module' => false,
        ]);
    }

    #[Test]
    public function super_admin_can_delete_page_asset(): void
    {
        $page = $this->draftPage();
        $asset = PageAsset::query()->create([
            'page_id' => $page->id,
            'type' => 'css',
            'path' => '/site/default/playground/delete-me.css',
            'load_position' => 'head',
            'is_enabled' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('admin.pages.assets.destroy', ['page' => $page, 'page_asset' => $asset]), [
                '_page_asset_close_url' => route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']),
            ])
            ->assertRedirect(route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']));

        $this->assertDatabaseMissing('page_assets', ['id' => $asset->id]);
    }

    #[Test]
    public function validation_errors_return_to_page_assets_tab(): void
    {
        $page = $this->draftPage();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->from(route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']))
            ->post(route('admin.pages.assets.store', ['page' => $page, 'type' => 'js']), [
                'path' => 'https://cdn.example.com/file.js',
                '_page_asset_close_url' => route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']),
            ])
            ->assertRedirect(route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']))
            ->assertSessionHasErrors('path');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets', 'modal' => 'create-page-asset', 'asset_type' => 'js']))
            ->assertSee('Page Assets')
            ->assertSee('Add JS Asset');
    }

    #[Test]
    public function non_super_admin_cannot_create_update_or_delete_page_assets(): void
    {
        $page = $this->draftPage();
        $asset = PageAsset::query()->create([
            'page_id' => $page->id,
            'type' => 'js',
            'path' => '/site/default/playground/locked.js',
            'load_position' => 'body_end',
            'is_enabled' => true,
            'is_defer' => true,
            'sort_order' => 0,
        ]);
        $siteAdmin = User::factory()->siteAdmin()->create();
        $siteAdmin->sites()->sync([$page->site_id]);

        $this->actingAs($siteAdmin)
            ->post(route('admin.pages.assets.store', ['page' => $page, 'type' => 'js']), [
                'path' => '/site/default/playground/new.js',
            ])
            ->assertForbidden();

        $this->actingAs($siteAdmin)
            ->put(route('admin.pages.assets.update', ['page' => $page, 'page_asset' => $asset]), [
                'path' => '/site/default/playground/updated.js',
            ])
            ->assertForbidden();

        $this->actingAs($siteAdmin)
            ->delete(route('admin.pages.assets.destroy', ['page' => $page, 'page_asset' => $asset]))
            ->assertForbidden();

        $this->assertDatabaseHas('page_assets', ['id' => $asset->id, 'path' => '/site/default/playground/locked.js']);
    }

    #[Test]
    public function non_super_admin_sees_existing_assets_read_only(): void
    {
        $page = $this->draftPage();
        PageAsset::query()->create([
            'page_id' => $page->id,
            'type' => 'css',
            'path' => '/site/default/playground/readonly.css',
            'load_position' => 'head',
            'is_enabled' => true,
            'sort_order' => 0,
        ]);
        $siteAdmin = User::factory()->siteAdmin()->create();
        $siteAdmin->sites()->sync([$page->site_id]);

        $response = $this->actingAs($siteAdmin)
            ->get(route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']));

        $response->assertOk();
        $response->assertSee('Only super admins can manage page assets.');
        $response->assertSee('/site/default/playground/readonly.css', false);
        $response->assertDontSee('Add CSS asset');
        $response->assertDontSee('Add JS asset');
        $response->assertDontSee('wb-action-btn-edit', false);
        $response->assertDontSee('wb-action-btn-delete', false);
    }

    #[Test]
    public function page_assets_type_is_selected_by_modal_action_not_editable_input(): void
    {
        $page = $this->draftPage();

        $response = $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets', 'modal' => 'create-page-asset', 'asset_type' => 'css']));

        $response->assertOk();
        $response->assertSee('Add CSS Asset');
        $response->assertSee('Type is fixed by the add action.');
        $response->assertSee('name="_page_asset_type" value="css"', false);
        $response->assertDontSee('name="type"', false);
    }

    #[Test]
    public function existing_path_validation_rules_still_reject_unsafe_paths(): void
    {
        $page = $this->draftPage();
        $user = User::factory()->superAdmin()->create();

        foreach ([
            'https://cdn.example.com/file.js',
            '/site/example/bad.js?version=1',
            '/site/example/../bad.js',
            '/site/example/file.css',
        ] as $path) {
            $this->actingAs($user)
                ->from(route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']))
                ->post(route('admin.pages.assets.store', ['page' => $page, 'type' => 'js']), [
                    'path' => $path,
                    '_page_asset_close_url' => route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']),
                ])
                ->assertRedirect(route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']))
                ->assertSessionHasErrors('path');
        }
    }

    private function draftPage(): Page
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->where('is_primary', true)->firstOrFail();

        return Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Playground',
            'slug' => 'playground',
            'status' => Page::STATUS_DRAFT,
        ]);
    }
}
