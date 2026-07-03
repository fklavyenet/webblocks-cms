<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebBlocksCmsTablePrefixUpdateMigrationTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function update_migration_renames_existing_cms_tables_to_wbcms_prefix_without_touching_host_users_table(): void
  {
    Schema::disableForeignKeyConstraints();
    Schema::dropIfExists('wbcms_system_settings');
    Schema::dropIfExists('wbcms_system_update_runs');
    Schema::dropIfExists('system_settings');
    Schema::dropIfExists('system_update_runs');
    Schema::enableForeignKeyConstraints();

    Schema::create('system_settings', function (Blueprint $table): void {
      $table->id();
      $table->string('key')->unique();
      $table->longText('value')->nullable();
      $table->timestamps();
    });

    Schema::create('system_update_runs', function (Blueprint $table): void {
      $table->id();
      $table->string('target_version');
      $table->string('status')->default('pending');
      $table->timestamps();
    });

    DB::table('system_settings')->insert([
      'key' => 'table-prefix-test',
      'value' => 'ok',
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_07_03_120000_prefix_webblocks_cms_tables.php');
    $migration->up();

    $this->assertFalse(Schema::hasTable('system_settings'));
    $this->assertFalse(Schema::hasTable('system_update_runs'));
    $this->assertTrue(Schema::hasTable('wbcms_system_settings'));
    $this->assertTrue(Schema::hasTable('wbcms_system_update_runs'));
    $this->assertTrue(Schema::hasTable('users'));
    $this->assertFalse(Schema::hasTable('wbcms_users'));

    $this->assertSame('ok', DB::table('wbcms_system_settings')->where('key', 'table-prefix-test')->value('value'));
  }
}
