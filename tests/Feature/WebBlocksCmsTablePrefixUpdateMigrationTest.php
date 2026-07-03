<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\Database\CmsTableCompatibilityViews;

class WebBlocksCmsTablePrefixUpdateMigrationTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function update_migration_renames_existing_cms_tables_and_bridges_legacy_update_request_writes(): void
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
      $table->text('summary')->nullable();
      $table->timestamps();
    });

    DB::table('system_settings')->insert([
      'key' => 'table-prefix-test',
      'value' => 'ok',
      'created_at' => now(),
      'updated_at' => now(),
    ]);
    $runId = DB::table('system_update_runs')->insertGetId([
      'target_version' => '1.32.214',
      'status' => 'pending',
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_07_03_120000_prefix_webblocks_cms_tables.php');
    $migration->up();

    $this->assertTrue(Schema::hasTable('wbcms_system_settings'));
    $this->assertTrue(Schema::hasTable('wbcms_system_update_runs'));
    $this->assertTrue($this->isView('system_settings'));
    $this->assertTrue($this->isView('system_update_runs'));
    $this->assertTrue(Schema::hasTable('users'));
    $this->assertFalse(Schema::hasTable('wbcms_users'));

    $this->assertSame('ok', DB::table('wbcms_system_settings')->where('key', 'table-prefix-test')->value('value'));

    DB::table('system_settings')->where('key', 'table-prefix-test')->update([
      'value' => 'written-through-legacy-view',
      'updated_at' => now(),
    ]);
    DB::table('system_update_runs')->where('id', $runId)->update([
      'status' => 'success',
      'summary' => 'Legacy request completed after table prefix migration.',
      'updated_at' => now(),
    ]);

    $this->assertSame('written-through-legacy-view', DB::table('wbcms_system_settings')->where('key', 'table-prefix-test')->value('value'));
    $this->assertSame('success', DB::table('wbcms_system_update_runs')->where('id', $runId)->value('status'));

    app(CmsTableCompatibilityViews::class)->dropLegacyUpdateBridgeViews();

    $this->assertFalse($this->isView('system_settings'));
    $this->assertFalse($this->isView('system_update_runs'));
    $this->assertFalse(Schema::hasTable('system_settings'));
    $this->assertFalse(Schema::hasTable('system_update_runs'));
    $this->assertTrue(Schema::hasTable('wbcms_system_settings'));
    $this->assertTrue(Schema::hasTable('wbcms_system_update_runs'));
  }

  private function isView(string $name): bool
  {
    if (DB::connection()->getDriverName() === 'sqlite') {
      return DB::table('sqlite_master')
        ->where('type', 'view')
        ->where('name', $name)
        ->exists();
    }

    return DB::table('information_schema.views')
      ->where('table_schema', DB::connection()->getDatabaseName())
      ->where('table_name', $name)
      ->exists();
  }
}
