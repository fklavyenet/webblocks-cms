<?php

namespace Tests\Feature\Console;

use App\Models\IconCatalogItem;
use App\Support\Icons\WebBlocksIconManifestSyncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncWebBlocksUiIconsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sync_command_imports_icons_from_the_default_manifest(): void
    {
        Http::fake([
            WebBlocksIconManifestSyncer::DEFAULT_MANIFEST => Http::response([
                [
                    'slug' => 'layout-grid',
                    'label' => 'Layout Grid',
                    'css_class' => 'wb-icon-layout-grid',
                    'source' => 'webblocks-ui',
                    'categories' => ['layout'],
                    'contexts' => ['navigation'],
                    'keywords' => ['layout', 'grid'],
                ],
                [
                    'slug' => 'images',
                    'label' => 'Images',
                    'css_class' => 'wb-icon-images',
                    'source' => 'webblocks-ui',
                    'categories' => ['media'],
                    'contexts' => ['navigation'],
                    'keywords' => ['images', 'gallery'],
                ],
                [
                    'slug' => 'cookie',
                    'label' => 'Cookie',
                    'css_class' => 'wb-icon-cookie',
                    'source' => 'webblocks-ui',
                    'categories' => ['privacy'],
                    'contexts' => ['navigation'],
                    'keywords' => ['cookie', 'consent'],
                ],
                [
                    'slug' => 'megaphone',
                    'label' => 'Megaphone',
                    'css_class' => 'wb-icon-megaphone',
                    'source' => 'webblocks-ui',
                    'categories' => ['marketing'],
                    'contexts' => ['navigation'],
                    'keywords' => ['announce', 'promo'],
                ],
                [
                    'slug' => 'route',
                    'label' => 'Route',
                    'css_class' => 'wb-icon-route',
                    'source' => 'webblocks-ui',
                    'categories' => ['navigation'],
                    'contexts' => ['navigation'],
                    'keywords' => ['path', 'route'],
                ],
                [
                    'slug' => 'circle-dot',
                    'label' => 'Circle Dot',
                    'css_class' => 'wb-icon-circle-dot',
                    'source' => 'webblocks-ui',
                    'categories' => ['status'],
                    'contexts' => ['navigation'],
                    'keywords' => ['dot', 'status'],
                ],
                [
                    'slug' => 'box',
                    'label' => 'Box',
                    'css_class' => 'wb-icon-box',
                    'source' => 'webblocks-ui',
                    'categories' => ['layout'],
                    'contexts' => ['navigation'],
                    'keywords' => ['box', 'package'],
                ],
            ], 200),
        ]);

        $this->artisan('icons:sync-webblocks-ui')
            ->expectsOutputToContain('Manifest: '.WebBlocksIconManifestSyncer::DEFAULT_MANIFEST)
            ->expectsOutputToContain('Created: 7')
            ->expectsOutputToContain('Updated: 0')
            ->expectsOutputToContain('Unchanged: 0')
            ->expectsOutputToContain('Deactivated: 0')
            ->assertExitCode(0);

        $icon = IconCatalogItem::query()->where('slug', 'layout-grid')->firstOrFail();

        $this->assertSame('layout-grid', $icon->slug);
        $this->assertSame('Layout Grid', $icon->label);
        $this->assertSame(['layout'], $icon->categories);
        $this->assertSame(['navigation'], $icon->contexts);

        foreach ([
            'images' => 'wb-icon-images',
            'cookie' => 'wb-icon-cookie',
            'megaphone' => 'wb-icon-megaphone',
            'route' => 'wb-icon-route',
            'circle-dot' => 'wb-icon-circle-dot',
            'box' => 'wb-icon-box',
        ] as $slug => $cssClass) {
            $this->assertDatabaseHas('icon_catalog_items', [
                'source' => 'webblocks-ui',
                'slug' => $slug,
                'css_class' => $cssClass,
                'is_active' => true,
            ]);
        }
    }

    #[Test]
    public function sync_command_can_deactivate_missing_icons(): void
    {
        IconCatalogItem::query()->create([
            'source' => 'webblocks-ui',
            'slug' => 'legacy-icon',
            'label' => 'Legacy Icon',
            'css_class' => 'wb-icon-legacy-icon',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Http::fake([
            WebBlocksIconManifestSyncer::DEFAULT_MANIFEST => Http::response([
                [
                    'slug' => 'layout',
                    'label' => 'Layout',
                    'css_class' => 'wb-icon-layout',
                    'source' => 'webblocks-ui',
                ],
            ], 200),
        ]);

        $this->artisan('icons:sync-webblocks-ui', ['--deactivate-missing' => true])
            ->expectsOutputToContain('Created: 1')
            ->expectsOutputToContain('Deactivated: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('icon_catalog_items', [
            'source' => 'webblocks-ui',
            'slug' => 'legacy-icon',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('icon_catalog_items', [
            'source' => 'webblocks-ui',
            'slug' => 'layout',
            'is_active' => true,
        ]);
    }
}
