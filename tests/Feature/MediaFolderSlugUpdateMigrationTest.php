<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaFolderSlugUpdateMigrationTest extends TestCase
{
  private ?string $sqlitePath = null;

  #[Test]
  public function package_update_migration_repairs_existing_media_folders_table_missing_slug(): void
  {
    $this->withSchemaRepairConnection(function (): void {
      Schema::create('wbcms_media_folders', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->string('name');
        $table->timestamps();
      });

      DB::table('wbcms_media_folders')->insert([
        [
          'parent_id' => null,
          'name' => 'Branding',
          'created_at' => now(),
          'updated_at' => now(),
        ],
        [
          'parent_id' => null,
          'name' => 'Branding',
          'created_at' => now(),
          'updated_at' => now(),
        ],
      ]);

      $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_05_24_120000_ensure_media_folders_slug.php');
      $migration->up();

      $this->assertTrue(Schema::hasColumn('wbcms_media_folders', 'slug'));
      $this->assertSame(['branding', 'branding-2'], DB::table('wbcms_media_folders')
        ->orderBy('id')
        ->pluck('slug')
        ->all());
    });
  }

  #[Test]
  public function package_update_migration_is_idempotent_and_preserves_existing_slugs(): void
  {
    $this->withSchemaRepairConnection(function (): void {
      Schema::create('wbcms_media_folders', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->timestamps();
      });

      DB::table('wbcms_media_folders')->insert([
        [
          'parent_id' => null,
          'name' => 'Existing',
          'slug' => 'custom-existing',
          'created_at' => now(),
          'updated_at' => now(),
        ],
        [
          'parent_id' => null,
          'name' => 'Needs Slug',
          'slug' => '',
          'created_at' => now(),
          'updated_at' => now(),
        ],
      ]);

      $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_05_24_120000_ensure_media_folders_slug.php');
      $migration->up();
      $migration->up();

      $this->assertSame(['custom-existing', 'needs-slug'], DB::table('wbcms_media_folders')
        ->orderBy('id')
        ->pluck('slug')
        ->all());
    });
  }

  protected function tearDown(): void
  {
    if ($this->sqlitePath !== null) {
      File::delete($this->sqlitePath);
    }

    parent::tearDown();
  }

  private function withSchemaRepairConnection(callable $callback): void
  {
    $originalConnection = DB::getDefaultConnection();
    $this->sqlitePath = storage_path('app/testing-schema-repairs/'.Str::uuid().'.sqlite');
    File::ensureDirectoryExists(dirname($this->sqlitePath));
    File::put($this->sqlitePath, '');

    config()->set('database.connections.schema_repair', [
      'driver' => 'sqlite',
      'database' => $this->sqlitePath,
      'prefix' => '',
      'foreign_key_constraints' => true,
    ]);

    DB::setDefaultConnection('schema_repair');

    try {
      $callback();
    } finally {
      DB::disconnect('schema_repair');
      DB::setDefaultConnection($originalConnection);
    }
  }
}
