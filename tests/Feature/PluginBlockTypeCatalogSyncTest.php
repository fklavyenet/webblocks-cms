<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;
use WebBlocks\Cms\Support\Plugins\PluginBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\Plugins\PluginBlockTypeDefinition;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRuntimeRefresher;
use WebBlocks\Cms\Tests\TestCase;

/**
 * A plugin block type that never reaches the database catalog cannot be placed:
 * pickers read `wbcms_block_types`, and `PluginBlockCatalog` only filters that
 * list. These cover the syncer that writes the row.
 */
class PluginBlockTypeCatalogSyncTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    (require dirname(__DIR__, 2).'/database/migrations/2026_04_08_020030_create_block_types_table.php')->up();
  }

  #[Test]
  public function a_declared_plugin_block_type_gets_a_published_catalog_row(): void
  {
    $this->syncerFor($this->pluginWithForm())->sync();

    $blockType = BlockType::query()->where('slug', 'appointments-form')->first();

    $this->assertNotNull($blockType);
    $this->assertSame('Appointment Form', $blockType->name);
    $this->assertSame('Book without leaving the site.', $blockType->description);
    $this->assertSame('content', $blockType->category);
    $this->assertSame('static', $blockType->source_type);
    $this->assertSame('published', $blockType->status);
    $this->assertFalse($blockType->is_system);
    $this->assertFalse($blockType->is_container);
  }

  #[Test]
  public function syncing_twice_reports_the_second_run_as_unchanged(): void
  {
    $syncer = $this->syncerFor($this->pluginWithForm());

    $this->assertSame(1, $syncer->sync()['created']);
    $this->assertSame(1, $syncer->sync()['unchanged']);
    $this->assertSame(1, BlockType::query()->where('slug', 'appointments-form')->count());
  }

  #[Test]
  public function a_dry_run_reports_the_row_without_writing_it(): void
  {
    $summary = $this->syncerFor($this->pluginWithForm())->sync(dryRun: true);

    $this->assertSame(1, $summary['created']);
    $this->assertSame(0, BlockType::query()->count());
  }

  #[Test]
  public function a_disabled_plugin_still_gets_its_catalog_row(): void
  {
    $this->syncerFor($this->pluginWithForm(), enabled: false)->sync();

    $this->assertSame(1, BlockType::query()->where('slug', 'appointments-form')->count());
  }

  /**
   * Placement is filtered by `PluginBlockCatalog`, not by the row, so a
   * disabled plugin's block stays out of pickers while its row keeps existing
   * blocks resolvable.
   */
  #[Test]
  public function a_disabled_plugins_row_is_filtered_out_of_pickers(): void
  {
    $this->syncerFor($this->pluginWithForm(), enabled: false)->sync();

    $catalog = app(PluginBlockCatalog::class);
    $discoverable = $catalog->filterDiscoverableBlockTypes(BlockType::query()->get());

    $this->assertTrue($catalog->isPluginCatalogSlug('appointments-form'));
    $this->assertFalse($catalog->isEnabledCatalogSlug('appointments-form'));
    $this->assertCount(0, $discoverable);
  }

  #[Test]
  public function a_re_sync_corrects_the_label_but_keeps_operator_curation(): void
  {
    $this->syncerFor($this->pluginWithForm())->sync();

    BlockType::query()->where('slug', 'appointments-form')->update([
      'name' => 'Renamed by hand',
      'category' => 'form',
      'sort_order' => 42,
      'status' => 'draft',
    ]);

    $this->syncerFor($this->pluginWithForm())->sync();

    $blockType = BlockType::query()->where('slug', 'appointments-form')->first();

    $this->assertSame('Appointment Form', $blockType->name);
    $this->assertSame('form', $blockType->category);
    $this->assertSame(42, $blockType->sort_order);
    $this->assertSame('draft', $blockType->status);
  }

  #[Test]
  public function block_metadata_can_choose_the_picker_category(): void
  {
    $blockType = PluginBlockTypeDefinition::make('appointments::form')
      ->label('Appointment Form')
      ->metadata(['category' => 'form', 'sort_order' => 7]);

    $this->syncerFor($this->pluginDefinition()->blockTypes([$blockType]))->sync();

    $row = BlockType::query()->where('slug', 'appointments-form')->first();

    $this->assertSame('form', $row->category);
    $this->assertSame(7, $row->sort_order);
  }

  #[Test]
  public function a_shipped_core_slug_is_never_rewritten_by_a_plugin(): void
  {
    BlockType::query()->create([
      'name' => 'Hero',
      'slug' => 'hero',
      'category' => 'content',
      'source_type' => 'static',
      'is_system' => false,
      'is_container' => false,
      'sort_order' => 1,
      'status' => 'published',
    ]);

    $blockType = PluginBlockTypeDefinition::make('appointments::form')
      ->label('Impostor')
      ->metadata(['catalog_slug' => 'hero']);

    $summary = $this->syncerFor($this->pluginDefinition()->blockTypes([$blockType]))->sync();

    $this->assertSame(1, $summary['skipped']);
    $this->assertSame('Hero', BlockType::query()->where('slug', 'hero')->value('name'));
  }

  /**
   * Every plugin lifecycle transition — install, enable, disable, setup,
   * update — ends in a runtime refresh, which is what makes the catalog row
   * appear without an operator running anything.
   */
  #[Test]
  public function a_runtime_refresh_writes_the_catalog_row(): void
  {
    $this->bindRegistryFor($this->pluginWithForm());

    $summary = app(PluginRuntimeRefresher::class)->refresh();

    $this->assertSame(1, $summary['plugin_block_types']['created']);
    $this->assertSame(1, BlockType::query()->where('slug', 'appointments-form')->count());
  }

  #[Test]
  public function catalog_repair_covers_plugin_block_types(): void
  {
    $this->bindRegistryFor($this->pluginWithForm());

    $this->artisan('webblocks:catalog-repair', ['--plugin-block-types' => true])
      ->assertSuccessful();

    $this->assertSame(1, BlockType::query()->where('slug', 'appointments-form')->count());
  }

  private function pluginWithForm(): PluginDefinition
  {
    return $this->pluginDefinition()->blockTypes([
      PluginBlockTypeDefinition::make('appointments::form')
        ->label('Appointment Form')
        ->description('Book without leaving the site.'),
    ]);
  }

  private function pluginDefinition(): PluginDefinition
  {
    return PluginDefinition::make('appointments')->label('Appointments')->version('1.0.0');
  }

  private function syncerFor(PluginDefinition $plugin, bool $enabled = true): PluginBlockTypeCatalogSyncer
  {
    $registry = $this->bindRegistryFor($plugin, $enabled);

    return new PluginBlockTypeCatalogSyncer(
      $registry,
      app(PluginBlockCatalog::class),
      new CoreBlockTypeCatalogSyncer,
    );
  }

  /**
   * Bound as a singleton factory rather than an instance: a runtime refresh
   * forgets the resolved registry, and an instance binding would not survive
   * it.
   */
  private function bindRegistryFor(PluginDefinition $plugin, bool $enabled = true): PluginRegistry
  {
    $registry = new PluginRegistry([$plugin->handle() => $enabled]);
    $registry->register($plugin);

    $this->app->forgetInstance(PluginRegistry::class);
    $this->app->singleton(PluginRegistry::class, fn (): PluginRegistry => $registry);
    $this->app->forgetInstance(PluginBlockCatalog::class);

    return $registry;
  }
}
