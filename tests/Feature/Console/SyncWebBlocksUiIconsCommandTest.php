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
            ], 200),
        ]);

        $this->artisan('icons:sync-webblocks-ui')
            ->expectsOutputToContain('Manifest: '.WebBlocksIconManifestSyncer::DEFAULT_MANIFEST)
            ->expectsOutputToContain('Created: 1')
            ->expectsOutputToContain('Updated: 0')
            ->expectsOutputToContain('Unchanged: 0')
            ->expectsOutputToContain('Deactivated: 0')
            ->assertExitCode(0);

        $icon = IconCatalogItem::query()->firstOrFail();

        $this->assertSame('layout-grid', $icon->slug);
        $this->assertSame('Layout Grid', $icon->label);
        $this->assertSame(['layout'], $icon->categories);
        $this->assertSame(['navigation'], $icon->contexts);
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
