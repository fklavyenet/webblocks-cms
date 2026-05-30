<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\SlotType;

class CatalogRepairCommandTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function dry_run_reports_changes_without_writing_catalog_rows(): void
  {
    $this->artisan('webblocks:catalog-repair --dry-run --block-types')
      ->expectsOutputToContain('Catalog repair dry run complete.')
      ->expectsOutputToContain('block-types: created')
      ->assertExitCode(0);

    $this->assertDatabaseMissing('block_types', ['slug' => 'header']);
  }

  #[Test]
  public function scoped_block_type_repair_is_idempotent_and_preserves_custom_rows(): void
  {
    BlockType::query()->create([
      'name' => 'Custom Hero',
      'slug' => 'custom-hero',
      'category' => 'custom',
      'description' => 'Install-owned block type.',
      'source_type' => 'dynamic',
      'is_system' => false,
      'is_container' => true,
      'sort_order' => 321,
      'status' => 'published',
    ]);

    $this->artisan('webblocks:catalog-repair --block-types')
      ->expectsOutputToContain('Catalog repair complete.')
      ->expectsOutputToContain('Custom catalog rows are preserved.')
      ->assertExitCode(0);

    $this->artisan('webblocks:catalog-repair --block-types')
      ->expectsOutputToContain('updated 0')
      ->assertExitCode(0);

    $this->assertSame(1, BlockType::query()->where('slug', 'header')->count());
    $this->assertDatabaseHas('block_types', [
      'slug' => 'custom-hero',
      'name' => 'Custom Hero',
      'status' => 'published',
    ]);
  }

  #[Test]
  public function all_scopes_repair_shipped_catalogs_without_deleting_custom_rows(): void
  {
    SlotType::query()->create([
      'name' => 'Custom Slot',
      'slug' => 'custom-slot',
      'description' => 'Install-owned slot.',
      'axis' => 'vertical',
      'is_system' => false,
      'sort_order' => 99,
      'status' => 'published',
    ]);

    $this->artisan('webblocks:catalog-repair --all')
      ->expectsOutputToContain('block-types:')
      ->expectsOutputToContain('slot-types:')
      ->expectsOutputToContain('page-layouts:')
      ->expectsOutputToContain('icons:')
      ->assertExitCode(0);

    $this->assertDatabaseHas('slot_types', ['slug' => 'custom-slot', 'name' => 'Custom Slot']);
    $this->assertDatabaseHas('slot_types', ['slug' => 'header', 'name' => 'Header']);
    $this->assertDatabaseHas('page_layouts', ['handle' => 'default', 'name' => 'Default Layout']);
    $this->assertDatabaseHas('icon_catalog_items', ['source' => 'webblocks-ui', 'slug' => 'home']);
    $this->assertGreaterThan(0, PageLayout::query()->count());
    $this->assertGreaterThan(0, IconCatalogItem::query()->count());
  }

  #[Test]
  public function page_layout_scope_repairs_managed_slots_and_required_slot_types(): void
  {
    $this->artisan('webblocks:catalog-repair --page-layouts')
      ->expectsOutputToContain('page-layouts:')
      ->assertExitCode(0);

    $this->assertDatabaseHas('page_layouts', ['handle' => 'docs', 'name' => 'Docs Layout']);
    $this->assertDatabaseHas('slot_types', ['slug' => 'main', 'name' => 'Main']);
    $this->assertDatabaseHas('page_layout_slots', ['slot_name' => 'sidebar', 'html_id' => 'docsSidebar']);
  }
}
