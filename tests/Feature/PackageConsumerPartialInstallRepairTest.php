<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Database\CmsTable;

class PackageConsumerPartialInstallRepairTest extends TestCase
{
  use DatabaseMigrations;

  private string $tempRoutePath;

  protected function setUp(): void
  {
    parent::setUp();

    $this->tempRoutePath = storage_path('framework/testing/routes/web-'.Str::uuid().'.php');
    File::ensureDirectoryExists(dirname($this->tempRoutePath));
    File::put($this->tempRoutePath, <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/status', fn () => 'ok');
PHP);

    config()->set('webblocks-cms.install.web_routes_path', $this->tempRoutePath);
  }

  protected function tearDown(): void
  {
    $routeBackupFiles = glob($this->tempRoutePath.'.webblocks-cms.*.bak') ?: [];

    foreach (array_merge([$this->tempRoutePath], $routeBackupFiles) as $path) {
      if (is_file($path)) {
        @unlink($path);
      }
    }

    parent::tearDown();
  }

  #[Test]
  public function install_command_fails_early_when_partial_cms_schema_exists_without_repair_flag(): void
  {
    $this->resetCmsTablesForPartialInstallTest();
    $this->createEmptyPartialCmsTables(['page_types', 'layout_types', 'slot_types']);

    try {
      $this->artisan('webblocks:install', [
        '--name' => 'Partial Admin',
        '--email' => 'partial-admin@example.com',
        '--password' => 'secret-password',
        '--no-interaction' => true,
        '--force' => true,
      ]);

      $this->fail('The installer should fail before fresh migrations when partial CMS tables exist.');
    } catch (\RuntimeException $exception) {
      $this->assertStringContainsString('Partial WebBlocks CMS schema detected.', $exception->getMessage());
    } finally {
      $this->restoreCmsSchemaAfterExpectedFailure();
    }
  }

  #[Test]
  public function install_command_repairs_empty_partial_cms_schema_before_running_fresh_migrations(): void
  {
    $this->resetCmsTablesForPartialInstallTest();
    $this->createEmptyPartialCmsTables([
      'page_types',
      'layout_types',
      'slot_types',
      'block_types',
      'system_settings',
      'system_update_runs',
    ]);

    $this->artisan('webblocks:install', [
      '--name' => 'Partial Repair Admin',
      '--email' => 'partial-repair-admin@example.com',
      '--password' => 'secret-password',
      '--site-name' => 'Partial Repair Site',
      '--site-handle' => 'partial-repair-site',
      '--repair-partial' => true,
      '--no-interaction' => true,
      '--force' => true,
    ])
      ->expectsOutputToContain('Partial WebBlocks CMS schema detected before fresh-install migrations.')
      ->expectsOutputToContain('Renamed empty partial CMS table wbcms_page_types to wbcms_page_types_before_cms_install_')
      ->assertExitCode(0);

    $this->assertTrue(Schema::hasTable(CmsTable::name('page_types')));
    $this->assertTrue(Schema::hasColumn(CmsTable::name('page_types'), 'slug'));
    $this->assertTrue($this->tableExistsLike(CmsTable::name('page_types').'_before_cms_install_%'));
    $this->assertDatabaseHas('users', [
      'email' => 'partial-repair-admin@example.com',
      'role' => 'super_admin',
    ]);
    $this->assertDatabaseHas(CmsTable::name('sites'), [
      'handle' => 'partial-repair-site',
      'is_primary' => 1,
    ]);
    $this->assertGreaterThan(0, Page::query()->count());
    $this->assertGreaterThan(0, BlockType::query()->count());
  }

  #[Test]
  public function install_command_never_repairs_non_empty_partial_cms_tables_automatically(): void
  {
    $this->resetCmsTablesForPartialInstallTest();
    $this->createEmptyPartialCmsTables(['page_types']);
    DB::table(CmsTable::name('page_types'))->insert(['id' => 1]);

    try {
      $this->artisan('webblocks:install', [
        '--name' => 'Unsafe Repair Admin',
        '--email' => 'unsafe-repair-admin@example.com',
        '--password' => 'secret-password',
        '--repair-partial' => true,
        '--no-interaction' => true,
        '--force' => true,
      ]);

      $this->fail('The installer should not repair non-empty partial CMS tables automatically.');
    } catch (\RuntimeException $exception) {
      $this->assertStringContainsString('Partial CMS schema contains rows and cannot be repaired automatically.', $exception->getMessage());
    } finally {
      $this->restoreCmsSchemaAfterExpectedFailure();
    }
  }

  private function resetCmsTablesForPartialInstallTest(): void
  {
    if (DB::connection()->getDriverName() === 'sqlite') {
      DB::statement('PRAGMA foreign_keys = OFF');
    }

    Schema::disableForeignKeyConstraints();

    foreach ([
      CmsTable::name('block_gallery_item_translations'),
      CmsTable::name('block_contact_form_translations'),
      CmsTable::name('block_image_translations'),
      CmsTable::name('block_button_translations'),
      CmsTable::name('block_text_translations'),
      CmsTable::name('system_backup_restores'),
      CmsTable::name('system_backups'),
      CmsTable::name('system_update_runs'),
      CmsTable::name('public_search_index'),
      CmsTable::name('visitor_events'),
      CmsTable::name('contact_messages'),
      CmsTable::name('site_imports'),
      CmsTable::name('site_exports'),
      CmsTable::name('icon_catalog_items'),
      CmsTable::name('page_assets'),
      CmsTable::name('shared_slot_revisions'),
      CmsTable::name('shared_slot_blocks'),
      CmsTable::name('shared_slots'),
      CmsTable::name('page_revisions'),
      CmsTable::name('block_media'),
      CmsTable::name('page_slots'),
      CmsTable::name('blocks'),
      CmsTable::name('page_translations'),
      CmsTable::name('pages'),
      CmsTable::name('page_layout_slots'),
      CmsTable::name('page_layouts'),
      CmsTable::name('layouts'),
      CmsTable::name('site_variables'),
      CmsTable::name('site_domains'),
      CmsTable::name('site_user'),
      CmsTable::name('site_locales'),
      CmsTable::name('locales'),
      CmsTable::name('sites'),
      CmsTable::name('media'),
      CmsTable::name('media_folders'),
      CmsTable::name('navigation_items'),
      CmsTable::name('system_settings'),
      CmsTable::name('block_types'),
      CmsTable::name('slot_types'),
      CmsTable::name('layout_types'),
      CmsTable::name('page_types'),
    ] as $table) {
      Schema::dropIfExists($table);
    }

    Schema::enableForeignKeyConstraints();

    if (DB::connection()->getDriverName() === 'sqlite') {
      DB::statement('PRAGMA foreign_keys = ON');
    }
  }

  private function createEmptyPartialCmsTables(array $tables): void
  {
    foreach ($tables as $table) {
      Schema::create(CmsTable::name($table), function ($blueprint): void {
        $blueprint->id();
      });
    }
  }

  private function tableExistsLike(string $pattern): bool
  {
    if (DB::connection()->getDriverName() === 'sqlite') {
      return DB::table('sqlite_master')
        ->where('type', 'table')
        ->where('name', 'like', $pattern)
        ->exists();
    }

    return DB::table('information_schema.tables')
      ->whereRaw('table_schema = database()')
      ->where('table_name', 'like', $pattern)
      ->exists();
  }

  private function restoreCmsSchemaAfterExpectedFailure(): void
  {
    $this->resetCmsTablesForPartialInstallTest();

    $this->artisan('webblocks:install', [
      '--name' => 'Restored Admin',
      '--email' => 'restored-admin@example.com',
      '--password' => 'secret-password',
      '--no-interaction' => true,
      '--force' => true,
    ])->assertExitCode(0);
  }
}
