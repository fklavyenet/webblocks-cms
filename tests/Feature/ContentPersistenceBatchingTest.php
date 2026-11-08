<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockTextTranslation;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Relational\PersistentDocuments;
use WebBlocks\Cms\Support\Relational\StructuredSettings;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Support\Translations\SiteLocaleValidation;
use WebBlocks\Cms\Tests\TestCase;

class ContentPersistenceBatchingTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function new_block_settings_skip_the_lookup_but_replace_documents_written_by_creation_observers(): void
  {
    [$page] = $this->fixtures();
    $reads = 0;
    $measuring = true;
    DB::listen(function ($query) use (&$reads, &$measuring): void {
      // Count only the existence lookup before this new owner's first write.
      // Saved indexing may legitimately hydrate its current SQL settings later.
      if (preg_match('/^insert into ["`]wbcms_setting_documents["`]/i', $query->sql)) {
        $measuring = false;
      }
      if ($measuring && preg_match('/^select .*from ["`]wbcms_setting_documents["`]/i', $query->sql)) {
        $reads++;
      }
    });
    $first = DB::transaction(fn () => PublicSearchIndexer::deferring(function () use ($page): Block {
      $block = new Block(['page_id' => $page->id, 'type' => 'plain_text', 'settings' => '{"alignment":"left"}']);
      $block->setRelation('page', $page);
      $this->assertTrue($block->createInTransaction());

      return $block;
    }));
    $measuring = false;
    $this->assertSame(0, $reads);
    $this->assertSame('left', $first->fresh()->setting('alignment'));

    Block::created(function (Block $block): void {
      DB::table('wbcms_setting_documents')->insert(['block_id' => $block->id, 'attribute' => 'settings', 'format' => 'value', 'payload_bytes' => 0, 'node_count' => 0]);
      app(StructuredSettings::class)->write($block, 'settings', '{"columns":"obsolete","old":{"keep":false}}');
    });
    $second = DB::transaction(fn () => PublicSearchIndexer::deferring(function () use ($page): Block {
      $block = new Block(['page_id' => $page->id, 'type' => 'plain_text', 'settings' => '{"alignment":"right","current":[false,null,1,"1"]}']);
      $block->setRelation('page', $page);
      $this->assertTrue($block->createInTransaction());

      return $block;
    }));
    $this->assertSame(['alignment' => 'right', 'current' => [false, null, 1, '1']], $second->fresh()->decodedSettings());
    $this->assertSame(1, DB::table('wbcms_setting_documents')->where('block_id', $second->id)->count());
    $documentId = DB::table('wbcms_setting_documents')->where('block_id', $second->id)->value('id');
    $this->assertDatabaseHas('wbcms_block_appearance_settings', ['document_id' => $documentId, 'columns' => null, 'alignment' => 'right']);
  }

  #[Test]
  public function typed_setting_lists_batch_rows_without_losing_keys_duplicates_or_nulls(): void
  {
    $assets = array_merge(array_fill(0, 4, str_repeat('x', 600000)), array_fill(0, 230, '/synthetic.css'));
    $scripts = ['/one.js', ['path' => '/two.js', 'type' => 'module', 'load_position' => 'head']];
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
      if (preg_match('/^insert into ["`]wbcms_application_assets["`]/i', $query->sql)) {
        $queries[] = $query;
      }
    });
    $application = EmbeddedApplication::create([
      'handle' => 'batch-settings', 'name' => 'Synthetic', 'version' => '1.0.0', 'render_mode' => 'inline',
      'css_assets' => $assets, 'js_assets' => $scripts,
      'settings_schema' => ['choice' => ['type' => 'enum', 'values' => ['a', 'a', null, false, 1, '1'], 'default' => null]],
    ]);
    $fresh = $application->fresh();
    $this->assertSame($assets, $fresh->css_assets);
    $this->assertSame($scripts, $fresh->js_assets);
    $this->assertSame(['a', 'a', null, false, 1, '1'], $fresh->settings_schema['choice']['values']);
    $this->assertNull($fresh->settings_schema['choice']['default']);
    $this->assertLessThan(230, count($queries));
    foreach ($queries as $query) {
      $this->assertLessThanOrEqual(900, count($query->bindings));
      $this->assertLessThanOrEqual(2097152, array_sum(array_map(fn ($value) => is_string($value) ? strlen($value) : 8, $query->bindings)));
    }
  }

  #[Test]
  public function interleaved_metadata_batches_keep_each_translation_event_and_immediate_row_visibility(): void
  {
    [$page, $locales] = $this->fixtures();
    $events = [];
    foreach (['saving', 'creating', 'created', 'saved'] as $event) {
      BlockTextTranslation::{$event}(function (BlockTextTranslation $translation) use ($event, &$events): void {
        $key = $translation->block_id.':'.$translation->locale_id;
        $events[$key][] = $event;
        if ($event === 'saving') {
          $translation->content = 'Observer '.$translation->content;
        }
        if ($event === 'created' || $event === 'saved') {
          $this->assertTrue($translation->exists);
          $this->assertTrue($translation->wasRecentlyCreated);
          $this->assertSame($translation->content, DB::table($translation->getTable())->where('id', $translation->id)->value('content'));
        }
        if ($event === 'saved' && $translation->block_id % 57 === 0) {
          $this->assertJsonStringEqualsJsonString($translation->meta, $translation->fresh()->meta);
        }
      });
    }
    DB::transaction(fn () => PublicSearchIndexer::deferring(fn () => app(SiteLocaleValidation::class)->during(
      fn () => app(PersistentDocuments::class)->duringBlockClone(function () use ($page, $locales): void {
        for ($i = 0; $i < 160; $i++) {
          $block = new Block(['page_id' => $page->id, 'type' => 'plain_text', 'meta' => json_encode(['source' => 'block-'.$i])]);
          $block->setRelation('page', $page);
          $this->assertTrue($block->createInTransaction());
          foreach ($locales as $locale) {
            $translation = new BlockTextTranslation([
              'block_id' => $block->id, 'locale_id' => $locale->id, 'content' => $i.' '.$locale->code,
              'meta' => json_encode(['locale' => $locale->code, 'items' => [$i, false, null, '1']]),
            ]);
            $translation->setRelation('block', $block);
            $this->assertTrue($translation->createInTransaction());
          }
        }
      }),
    )));
    $this->assertCount(960, $events);
    foreach ($events as $sequence) {
      $this->assertSame(['saving', 'creating', 'created', 'saved'], $sequence);
    }
    $this->assertSame(160, DB::table('wbcms_persistent_documents')->whereNotNull('block_id')->where('attribute', 'meta')->count());
    $this->assertSame(960, DB::table('wbcms_persistent_documents')->whereNotNull('block_text_translation_id')->where('attribute', 'meta')->count());
    foreach (Block::orderBy('id')->get() as $i => $block) {
      $this->assertSame(['source' => 'block-'.$i], json_decode($block->meta, true));
      foreach ($block->textTranslations as $translation) {
        $code = $locales->firstWhere('id', $translation->locale_id)->code;
        $this->assertSame(['locale' => $code, 'items' => [$i, false, null, '1']], json_decode($translation->meta, true));
      }
    }
  }

  private function fixtures(): array
  {
    $site = Site::create(['name' => 'Synthetic batch storage', 'handle' => 'batch-storage']);
    $locales = collect(['en', 'tr', 'de', 'es', 'it', 'fr'])->map(fn ($code) => Locale::create(['code' => $code, 'name' => $code, 'is_enabled' => true, 'is_default' => $code === 'en']));
    $site->locales()->sync($locales->mapWithKeys(fn ($locale) => [$locale->id => ['is_enabled' => true]])->all());

    return [Page::create(['site_id' => $site->id, 'title' => 'Synthetic', 'status' => 'draft']), $locales];
  }
}
