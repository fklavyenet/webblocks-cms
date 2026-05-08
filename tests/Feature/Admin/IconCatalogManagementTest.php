<?php

namespace Tests\Feature\Admin;

use App\Models\IconCatalogItem;
use App\Models\User;
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
        $expected = count((new IconCatalogSeeder())->fallbackNavigationIcons());

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
}
