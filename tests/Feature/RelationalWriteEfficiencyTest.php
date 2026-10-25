<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Relational\PersistentDocuments;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Tests\TestCase;

class RelationalWriteEfficiencyTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function new_documents_do_not_delete_nonexistent_children_but_replacements_remove_old_values(): void
  {
    [$page] = $this->fixtures();
    $deletes = [];
    $measuring = true;
    DB::listen(function ($query) use (&$deletes, &$measuring): void {
      if ($measuring && preg_match('/^delete from ["`]wbcms_(?:persistent_|setting_|block_appearance|import_identity_maps|import_copied_files)/', $query->sql)) {
        $deletes[] = $query->sql;
      }
    });
    $block = Block::create(['page_id' => $page->id, 'type' => 'rich-text', 'status' => 'draft',
      'settings' => json_encode(['alignment' => 'center', 'historical' => ['values' => [false, null, 1, '1']]]),
      'meta' => '{"source":"original","extra":[1,2]}']);
    $measuring = false;
    $this->assertSame([], $deletes, 'New relational documents have no child rows to clear.');
    $persistentId = DB::table('wbcms_persistent_documents')->where('block_id', $block->id)->where('attribute', 'meta')->value('id');
    $settingsId = DB::table('wbcms_setting_documents')->where('block_id', $block->id)->where('attribute', 'settings')->value('id');
    $this->assertSame([false, null, 1, '1'], $block->fresh()->setting('historical.values'));
    $block->update(['settings' => '{"alignment":"left"}', 'meta' => 'replacement']);
    $this->assertSame(['alignment' => 'left'], json_decode($block->fresh()->serializedSettings(), true));
    $this->assertSame('replacement', $block->fresh()->meta);
    $this->assertSame(0, DB::table('wbcms_persistent_metadata')->where('document_id', $persistentId)->count());
    $this->assertSame($persistentId, DB::table('wbcms_persistent_documents')->where('block_id', $block->id)->where('attribute', 'meta')->value('id'));
    $this->assertSame($settingsId, DB::table('wbcms_setting_documents')->where('block_id', $block->id)->where('attribute', 'settings')->value('id'));
    $block->update(['settings' => null, 'meta' => null]);
    $this->assertNull($block->fresh()->serializedSettings());
    $this->assertNull($block->fresh()->meta);
  }

  #[Test]
  public function row_writes_reuse_schema_discovery_and_still_read_the_latest_sql_values(): void
  {
    [$page, $locale] = $this->fixtures();
    $block = Block::create(['page_id' => $page->id, 'type' => 'plain_text', 'status' => 'draft']);
    $translation = $block->textTranslations()->create(['locale_id' => $locale->id, 'content' => 'Synthetic', 'meta' => 'initial']);
    $this->assertTrue(app(PersistentDocuments::class)->retired($translation, 'meta'));
    $schemaQueries = [];
    $measuring = true;
    DB::listen(function ($query) use (&$schemaQueries, &$measuring): void {
      if ($measuring && (str_contains($query->sql, 'pragma_table_xinfo') || str_contains($query->sql, 'sqlite_master'))) {
        $schemaQueries[] = $query->sql;
      }
    });
    PublicSearchIndexer::deferring(function () use ($translation): void {
      for ($i = 0; $i < 12; $i++) {
        $translation->update(['meta' => 'Value '.$i]);
        $this->assertSame('Value '.$i, $translation->fresh()->meta);
      }
    });
    $measuring = false;
    $this->assertSame([], $schemaQueries, 'DML must not rediscover unchanged table schemas on every row.');
  }

  private function fixtures(): array
  {
    $site = Site::create(['name' => 'Synthetic', 'handle' => 'synthetic', 'is_primary' => true]);
    $locale = Locale::create(['code' => 'en', 'name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $site->locales()->sync([$locale->id => ['is_enabled' => true]]);

    return [Page::create(['site_id' => $site->id, 'title' => 'Synthetic', 'status' => 'draft']), $locale];
  }
}
