<?php

namespace Tests\Feature\Admin;

use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Models\SystemSetting;
use App\Models\User;
use WebBlocks\Cms\Support\Icons\WebBlocksIconManifestSyncer;
use WebBlocks\Cms\Support\System\SystemSettings;
use Database\Seeders\IconCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IconCatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function icon_catalog_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('icon_catalog_items'));
    }

    #[Test]
    public function icon_catalog_seeder_is_idempotent(): void
    {
        $expected = count((new IconCatalogSeeder)->fallbackNavigationIcons());

        $this->seed(IconCatalogSeeder::class);
        $this->seed(IconCatalogSeeder::class);

        $this->assertDatabaseCount('icon_catalog_items', $expected);
    }

    #[Test]
    public function super_admin_can_update_icon_metadata_and_return_to_filtered_listing(): void
    {
        $user = User::factory()->superAdmin()->create();
        $icon = IconCatalogItem::query()->create([
            'source' => 'webblocks-ui',
            'slug' => 'layout',
            'label' => 'Layout',
            'css_class' => 'wb-icon-layout',
            'contexts' => ['navigation'],
            'categories' => ['navigation'],
            'keywords' => ['layout'],
            'is_active' => true,
            'sort_order' => 5,
        ]);
        $closeUrl = route('admin.system.icons.index', ['search' => 'layout', 'status' => 'active', 'page' => 2]);

        $response = $this->actingAs($user)->put(route('admin.system.icons.update', $icon), [
            'label' => 'Layout Grid',
            'contexts' => 'navigation, sidebar',
            'categories' => 'layout, docs',
            'keywords' => 'layout, grid, docs',
            'is_active' => '1',
            'sort_order' => 8,
            '_icon_index_url' => $closeUrl,
        ]);

        $response->assertRedirect($closeUrl);

        $icon->refresh();

        $this->assertSame('Layout Grid', $icon->label);
        $this->assertSame(['navigation', 'sidebar'], $icon->contexts);
        $this->assertSame(['layout', 'docs'], $icon->categories);
        $this->assertSame(['layout', 'grid', 'docs'], $icon->keywords);
        $this->assertTrue($icon->is_active);
        $this->assertSame(8, $icon->sort_order);
    }

    #[Test]
    public function validation_errors_reopen_the_icon_edit_modal(): void
    {
        $user = User::factory()->superAdmin()->create();
        $icon = IconCatalogItem::query()->create([
            'source' => 'webblocks-ui',
            'slug' => 'layout',
            'label' => 'Layout',
            'css_class' => 'wb-icon-layout',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $modalUrl = route('admin.system.icons.index', ['modal' => 'edit-icon', 'icon' => $icon->id]);

        $response = $this->actingAs($user)
            ->followingRedirects()
            ->from($modalUrl)
            ->put(route('admin.system.icons.update', $icon), [
                'label' => '',
                'sort_order' => 1,
                '_icon_modal' => 'edit-icon',
                '_icon_id' => $icon->id,
                '_icon_index_url' => route('admin.system.icons.index'),
            ]);

        $response->assertOk();
        $response->assertSee('Validation Error');
        $response->assertSee('id="iconEditModal-'.$icon->id.'"', false);
        $response->assertSee('class="wb-modal wb-modal-lg is-open"', false);
    }

    #[Test]
    public function icons_index_can_render_newly_shipped_v270_icon_classes(): void
    {
        $user = User::factory()->superAdmin()->create();

        foreach ([
            'images' => 'wb-icon-images',
            'cookie' => 'wb-icon-cookie',
            'megaphone' => 'wb-icon-megaphone',
            'route' => 'wb-icon-route',
            'circle-dot' => 'wb-icon-circle-dot',
            'box' => 'wb-icon-box',
        ] as $slug => $cssClass) {
            IconCatalogItem::query()->create([
                'source' => 'webblocks-ui',
                'slug' => $slug,
                'label' => str($slug)->replace('-', ' ')->title()->toString(),
                'css_class' => $cssClass,
                'contexts' => ['navigation'],
                'categories' => ['navigation'],
                'is_active' => true,
                'sort_order' => 1,
            ]);
        }

        $response = $this->actingAs($user)->get(route('admin.system.icons.index'));

        $response->assertOk();
        $response->assertSee('wb-icon-images', false);
        $response->assertSee('wb-icon-cookie', false);
        $response->assertSee('wb-icon-megaphone', false);
        $response->assertSee('wb-icon-route', false);
        $response->assertSee('wb-icon-circle-dot', false);
        $response->assertSee('wb-icon-box', false);
    }

    #[Test]
    public function default_icon_sync_manifest_is_pinned_to_webblocks_ui_v276(): void
    {
        $this->assertSame(
            'https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@v2.7.6/packages/webblocks/dist/webblocks-icons.json',
            WebBlocksIconManifestSyncer::DEFAULT_MANIFEST,
        );
    }

    #[Test]
    public function icons_index_uses_configured_admin_listing_rows_per_page(): void
    {
        $user = User::factory()->superAdmin()->create();

        SystemSetting::query()->updateOrCreate(
            ['key' => SystemSettings::ADMIN_LISTING_PER_PAGE],
            ['value' => '12'],
        );

        foreach (range(1, 13) as $index) {
            IconCatalogItem::query()->create([
                'source' => 'webblocks-ui',
                'slug' => 'configured-icon-'.$index,
                'label' => 'Configured Icon '.$index,
                'css_class' => 'wb-icon-box',
                'contexts' => ['navigation'],
                'categories' => ['navigation'],
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }

        $response = $this->actingAs($user)->get(route('admin.system.icons.index', [
            'search' => 'Configured Icon',
            'status' => 'active',
        ]));
        $expectedPageTwoUrl = route('admin.system.icons.index', [
            'search' => 'Configured Icon',
            'status' => 'active',
            'page' => 2,
        ]);

        $response->assertOk();
        $response->assertSee('1-12/13', false);
        $response->assertSee(e($expectedPageTwoUrl), false);
    }

    #[Test]
    public function icons_page_header_count_ignores_filters_while_card_count_uses_filtered_results(): void
    {
        $user = User::factory()->superAdmin()->create();

        foreach (range(1, 3) as $index) {
            IconCatalogItem::query()->create([
                'source' => 'webblocks-ui',
                'slug' => 'count-icon-'.$index,
                'label' => 'Count Icon '.$index,
                'css_class' => 'wb-icon-box',
                'contexts' => ['navigation'],
                'categories' => ['navigation'],
                'is_active' => $index === 1,
                'sort_order' => 300 + $index,
            ]);
        }

        $totalCount = IconCatalogItem::query()->count();

        $response = $this->actingAs($user)->get(route('admin.system.icons.index', [
            'search' => 'Count Icon',
            'status' => 'active',
        ]));

        $response->assertOk();
        $response->assertSee('data-admin-page-count', false);
        $response->assertSee('data-admin-list-count', false);
        $response->assertSee('<span class="wb-status-pill wb-status-info" data-admin-page-count>'.$totalCount.'</span>', false);
        $response->assertSee('<span class="wb-status-pill wb-status-info" data-admin-list-count>1</span>', false);
    }
}
