<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MediaFocalPointUpdateMigrationTest extends TestCase
{
  use RefreshDatabase;

  public function test_package_update_migration_adds_media_focal_point_columns_idempotently(): void
  {
    $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_07_11_120000_ensure_media_focal_point.php');
    $migration->up();
    $migration->up();

    $this->assertTrue(Schema::hasColumns('wbcms_media', ['focal_point_x', 'focal_point_y']));
  }
}
