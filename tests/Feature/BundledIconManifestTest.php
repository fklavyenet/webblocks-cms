<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Database\Seeders\IconCatalogSeeder;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Support\Catalog\CatalogRepairer;
use WebBlocks\Cms\Support\Icons\WebBlocksIconManifestSyncer;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The icon catalog is not optional content: every icon field in the admin is
 * empty without it. So the package carries the manifest for its pinned UI
 * version and both automatic paths — install and the catalog repair a System
 * Update runs — fill the catalog from it, with no outbound network.
 */
class BundledIconManifestTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function the_package_ships_a_manifest_for_the_pinned_ui_version(): void
  {
    $path = WebBlocksIconManifestSyncer::bundledManifestPath();

    // Bumping UI_VERSION without vendoring the matching manifest would leave
    // every install seeding an empty catalog; this is the guard for that.
    $this->assertFileExists($path, 'Missing bundled icon manifest for '.WebBlocks::uiVersion().'.');

    $decoded = json_decode((string) file_get_contents($path), true);

    $this->assertIsArray($decoded);
    $this->assertGreaterThan(100, count($decoded));
    $this->assertArrayHasKey('slug', $decoded[0]);
    $this->assertArrayHasKey('contexts', $decoded[0]);
  }

  #[Test]
  public function installing_fills_the_catalog_without_a_manual_sync(): void
  {
    $this->seed(IconCatalogSeeder::class);

    $this->assertGreaterThan(100, IconCatalogItem::query()->count());

    // The complaint this answers: a content icon field offered nothing on a
    // fresh install, because only navigation slugs were seeded.
    $this->assertTrue(IconCatalogItem::query()->active()->tagged('content')->exists());
    $this->assertTrue(IconCatalogItem::query()->active()->tagged('navigation')->exists());
  }

  #[Test]
  public function catalog_repair_brings_an_older_install_up_to_the_same_catalog(): void
  {
    // A site seeded before the manifest was bundled: a few navigation slugs.
    foreach (['home', 'rocket'] as $index => $slug) {
      IconCatalogItem::query()->create([
        'source' => 'webblocks-ui',
        'slug' => $slug,
        'label' => ucfirst($slug),
        'css_class' => 'wb-icon-'.$slug,
        'categories' => ['navigation'],
        'contexts' => ['navigation'],
        'keywords' => IconCatalogItem::normalizeKeywords([$slug]),
        'is_active' => true,
        'sort_order' => $index + 1,
      ]);
    }

    app(CatalogRepairer::class)->repair(['icons'], dryRun: false);

    $this->assertGreaterThan(100, IconCatalogItem::query()->count());
    $this->assertTrue(IconCatalogItem::query()->active()->tagged('content')->exists());
  }
}
