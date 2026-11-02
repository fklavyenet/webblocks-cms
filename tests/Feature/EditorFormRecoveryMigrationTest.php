<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\EditorRecovery\RecoverySchema;
use WebBlocks\Cms\Tests\TestCase;

class EditorFormRecoveryMigrationTest extends TestCase
{
  protected bool $cacheFreshDatabaseSchema = false;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function upgrade_is_repeatable_and_preserves_legacy_values_and_parent_foreign_keys(): void
  {
    Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $site = Site::create(['name' => 'Synthetic migration site', 'handle' => 'recovery-migration']);
    $page = Page::create(['site_id' => $site->id, 'title' => 'Existing page', 'slug' => 'existing-page', 'status' => 'draft']);
    $user = DB::table('users')->insertGetId(['name' => 'Synthetic operator', 'email' => 'migration-forms@example.test', 'password' => 'unused', 'role' => 'super_admin', 'is_active' => true]);
    $legacy = DB::table('wbcms_editor_recoveries')->insertGetId(['user_id' => $user, 'site_id' => $site->id, 'page_id' => $page->id, 'editing_session' => (string) Str::uuid(), 'locale' => 'en', 'base_fingerprint' => str_repeat('a', 64), 'expires_at' => now()->addDays(7)]);
    DB::table('wbcms_editor_recovery_values')->insert(['recovery_id' => $legacy, 'position' => 0, 'field_name' => 'title', 'format' => 'text', 'text_value' => 'Legacy unfinished title']);
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_11_02_120000_extend_editor_recovery_forms.php';
    $migration->down();
    $this->assertFalse(Schema::hasColumn('wbcms_editor_recoveries', 'form_kind'));
    $migration->up();
    $migration->up();
    RecoverySchema::migrate();
    $this->assertDatabaseHas('wbcms_editor_recovery_values', ['recovery_id' => $legacy, 'text_value' => 'Legacy unfinished title']);
    $this->assertDatabaseHas('wbcms_editor_recoveries', ['id' => $legacy, 'page_id' => $page->id, 'form_kind' => null]);
    $new = DB::table('wbcms_editor_recoveries')->insertGetId(['user_id' => $user, 'site_id' => $site->id, 'page_id' => null, 'form_kind' => 'new-page', 'editing_session' => (string) Str::uuid(), 'locale' => 'en', 'base_fingerprint' => str_repeat('b', 64), 'expires_at' => now()->addDays(7)]);
    DB::table('wbcms_editor_recovery_values')->insert(['recovery_id' => $new, 'position' => 0, 'field_name' => 'title', 'format' => 'text', 'text_value' => 'New unfinished title']);
    try {
      DB::table('wbcms_editor_recoveries')->insert(['user_id' => $user, 'site_id' => $site->id, 'page_id' => 999999, 'editing_session' => (string) Str::uuid(), 'locale' => 'en', 'base_fingerprint' => str_repeat('c', 64), 'expires_at' => now()->addDays(7)]);
      $this->fail('The original page foreign key must remain enforced.');
    } catch (QueryException $exception) {
      $this->assertStringContainsString('FOREIGN KEY', $exception->getMessage());
    }
    $migration->down();
    $this->assertDatabaseMissing('wbcms_editor_recovery_values', ['recovery_id' => $new]);
    $this->assertDatabaseHas('wbcms_editor_recovery_values', ['recovery_id' => $legacy, 'text_value' => 'Legacy unfinished title']);
  }

  #[Test]
  public function fresh_and_update_entry_points_define_identical_relational_context_fields(): void
  {
    $root = dirname(__DIR__, 2).'/database/migrations/';
    $this->assertSame(file_get_contents($root.'fresh/2026_11_02_120000_extend_editor_recovery_forms.php'), file_get_contents($root.'updates/2026_11_02_120000_extend_editor_recovery_forms.php'));
    foreach (['form_kind', 'block_type_id', 'slot_type_id', 'parent_block_id', 'translation_id', 'completed_page_id', 'completed_block_id', 'completed_translation_id'] as $column) {
      $this->assertTrue(Schema::hasColumn('wbcms_editor_recoveries', $column));
    }
    foreach (Schema::getColumns('wbcms_editor_recoveries') as $column) {
      $this->assertNotSame('json', strtolower($column['type_name']));
    }
  }
}
