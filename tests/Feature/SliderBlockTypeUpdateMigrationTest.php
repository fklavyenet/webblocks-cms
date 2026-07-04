<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SliderBlockTypeUpdateMigrationTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function update_migration_promotes_slider_and_creates_slide_block_types(): void
  {
    DB::table('wbcms_block_types')->insert([
      'name' => 'Legacy Slider',
      'slug' => 'slider',
      'category' => 'legacy',
      'description' => 'Legacy gallery slider.',
      'source_type' => 'asset',
      'is_system' => false,
      'is_container' => false,
      'sort_order' => 101,
      'status' => 'draft',
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_07_04_100000_promote_slider_block_types.php');
    $migration->up();

    $this->assertDatabaseHas('wbcms_block_types', [
      'slug' => 'slider',
      'name' => 'Slider',
      'category' => 'layout',
      'source_type' => 'static',
      'is_container' => true,
      'status' => 'published',
    ]);
    $this->assertDatabaseHas('wbcms_block_types', [
      'slug' => 'slide',
      'name' => 'Slide',
      'category' => 'layout',
      'source_type' => 'static',
      'is_container' => true,
      'status' => 'published',
    ]);
  }
}
