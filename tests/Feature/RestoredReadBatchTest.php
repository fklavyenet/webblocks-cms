<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockTextTranslation;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Blocks\BlockTranslationWriter;
use WebBlocks\Cms\Support\Relational\PersistentDocuments;
use WebBlocks\Cms\Support\Relational\StructuredSettings;
use WebBlocks\Cms\Support\Revisions\RestoredBlockReads;
use WebBlocks\Cms\Support\Revisions\RevisionContentUpdates;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class RestoredReadBatchTest extends TestCase
{
  private bool $watch = false;

  private array $selects = [];

  private array $blockBatchSizes = [];

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(RestoredBatchTestUser::class, 'App\\Models\\User');
    }
    parent::setUp();
    DB::listen(function ($query): void {
      if ($this->watch && ! str_contains($query->sql, 'budget_rows') && preg_match('/^select .*from ["`](wbcms_[a-z_]+)["`]/i', $query->sql, $match)) {
        $this->selects[$match[1]] = ($this->selects[$match[1]] ?? 0) + 1;
        if ($match[1] === 'wbcms_blocks' && str_contains($query->sql, ' in (')) {
          preg_match('/\bin\s*\(([^)]+)\)/i', $query->sql, $ids);
          $this->blockBatchSizes[] = count(explode(',', $ids[1]));
        }
      }
    });
  }

  public static function counts(): array
  {
    return [[12], [101]];
  }

  #[Test]
  #[DataProvider('counts')]
  public function blocks_and_unchanged_normalization_relations_are_read_in_bounded_groups(int $count): void
  {
    [$blocks, $locales] = $this->fixture($count, true);
    $seen = [];
    $this->runBatch($blocks, function (Block $block) use (&$seen, $locales): void {
      $seen[] = $block->id;
      $target = app(RestoredBlockReads::class)->forNormalization($block, false);
      $this->assertSame($block, $target);
      $this->assertCount(6, $target->textTranslations);
      $this->assertSame('Saved '.$locales->first()->code, $target->textTranslations->first()->content);
      $this->assertTrue($target->buttonTranslations->isEmpty());
      $target->textTranslations->first()->content = 'Memory only';
      $this->assertSame('Saved '.$locales->first()->code, app(RestoredBlockReads::class)->forNormalization($block, false)->textTranslations->first()->content);
    });
    $groups = (int) ceil($count / 50);
    foreach (['blocks', 'block_text_translations', 'block_button_translations', 'block_image_translations', 'block_contact_form_translations', 'block_plugin_translations'] as $table) {
      $this->assertSame($groups, $this->selects['wbcms_'.$table] ?? 0, $table);
    }
    $this->assertSame($blocks->modelKeys(), $seen);
    $this->assertLessThanOrEqual(50, max($this->blockBatchSizes));
  }

  #[Test]
  public function normal_translation_events_and_per_block_normalization_keep_their_original_order(): void
  {
    [$blocks, $locales] = $this->fixture(3);
    $events = [];
    foreach (['saving', 'creating', 'created', 'saved'] as $event) {
      BlockTextTranslation::{$event}(function (BlockTextTranslation $row) use (&$events, $event): void {
        $events[] = $row->block_id.':'.$row->locale_id.':'.$event;
      });
    }
    $this->runBatch($blocks, function (Block $block) use ($locales, &$events): void {
      foreach ($locales as $locale) {
        $row = $block->textTranslations()->make(['locale_id' => $locale->id, 'content' => 'Restored '.$locale->code, 'meta' => '{"source":"Restored"}']);
        $this->assertTrue(app(PersistentDocuments::class)->createRestored($row));
      }
      $target = app(RestoredBlockReads::class)->forNormalization($block, false);
      app(BlockTranslationWriter::class)->normalizeCanonicalStorage($target);
      $this->assertCount(6, $target->textTranslations);
      $events[] = $block->id.':normalized';
    });
    $expected = [];
    foreach ($blocks as $block) {
      foreach ($locales as $locale) {
        foreach (['saving', 'creating', 'created', 'saved'] as $event) {
          $expected[] = $block->id.':'.$locale->id.':'.$event;
        }
      }
      $expected[] = $block->id.':normalized';
    }
    $this->assertSame($expected, $events);
    $this->assertSame(18, BlockTextTranslation::count());
  }

  #[Test]
  public function observer_changes_to_future_blocks_settings_and_metadata_are_not_hidden_by_read_ahead(): void
  {
    [$blocks] = $this->fixture(3, true);
    $future = $blocks->last();
    $this->runBatch($blocks, function (Block $block) use ($blocks, $future): void {
      if ($block->id === $blocks->first()->id) {
        DB::table('wbcms_blocks')->where('id', $blocks[1]->id)->update(['sort_order' => 77]);
        app(StructuredSettings::class)->write($future, 'settings', '{"alignment":"right"}');
        app(PersistentDocuments::class)->write($future, 'meta', '{"source":"Observer"}');
      }
      if ($block->id === $blocks[1]->id) {
        $this->assertSame(77, $block->sort_order);
      }
      if ($block->id === $future->id) {
        $this->assertJsonStringEqualsJsonString('{"alignment":"right"}', $block->settings);
        $this->assertJsonStringEqualsJsonString('{"source":"Observer"}', $block->meta);
      }
    });
  }

  #[Test]
  public function relation_inserts_moves_and_unscoped_updates_are_visible_during_normalization(): void
  {
    [$blocks, $locales] = $this->fixture(3, true);
    $button = DB::table('wbcms_block_button_translations')->insertGetId(['block_id' => $blocks[1]->id, 'locale_id' => $locales->first()->id, 'title' => 'Before']);
    $this->runBatch($blocks, function (Block $block) use ($blocks, $locales, $button): void {
      if ($block->id === $blocks->first()->id) {
        DB::table('wbcms_block_button_translations')->where('id', $button)->update(['title' => 'After']);
        DB::table('wbcms_block_image_translations')->insert(['block_id' => $blocks[2]->id, 'locale_id' => $locales->first()->id, 'caption' => 'Inserted']);
      }
      $target = app(RestoredBlockReads::class)->forNormalization($block, false);
      if ($block->id === $blocks[1]->id) {
        $this->assertSame('After', $target->buttonTranslations->sole()->title);
        DB::table('wbcms_block_image_translations')->where('block_id', $blocks[2]->id)->update(['block_id' => $blocks[1]->id]);
      }
      if ($block->id === $blocks[2]->id) {
        $this->assertTrue($target->imageTranslations->isEmpty());
      }
    });
  }

  #[Test]
  public function rollback_and_failed_batches_discard_read_ahead_without_changing_the_next_run(): void
  {
    [$blocks] = $this->fixture(2, true);
    $this->runBatch($blocks, function (Block $block) use ($blocks): void {
      if ($block->id === $blocks->first()->id) {
        try {
          DB::transaction(function () use ($blocks): void {
            DB::table('wbcms_blocks')->where('id', $blocks[1]->id)->update(['sort_order' => 99]);
            app(RestoredBlockReads::class)->duringBlock($blocks[1]->id, fn (Block $nested) => $this->assertSame(99, $nested->sort_order));
            throw new RuntimeException('Synthetic rollback');
          });
        } catch (RuntimeException) {
        }
      } else {
        $this->assertSame(1, $block->sort_order);
      }
    });
    try {
      $this->runBatch($blocks, function (Block $block): void {
        DB::table('wbcms_blocks')->where('id', $block->id)->update(['sort_order' => 88]);
        throw new RuntimeException('Synthetic failed batch');
      });
    } catch (RuntimeException) {
    }
    $this->runBatch($blocks, fn (Block $block) => $this->assertSame($blocks->firstWhere('id', $block->id)->sort_order, $block->sort_order));
  }

  #[Test]
  public function existing_and_late_retrieved_observers_keep_native_reads(): void
  {
    [$blocks] = $this->fixture(3);
    $retrieved = 0;
    $this->runBatch($blocks, function (Block $block) use ($blocks, &$retrieved): void {
      if ($block->id === $blocks->first()->id) {
        Block::retrieved(function () use (&$retrieved): void {
          $retrieved++;
        });
      }
    });
    $this->assertSame(2, $retrieved);
    $retrieved = 0;
    $this->runBatch($blocks, fn () => null);
    $this->assertSame(3, $retrieved);
  }

  #[Test]
  public function a_late_scope_cannot_expose_a_prefetched_block(): void
  {
    [$blocks] = $this->fixture(2);
    $scopes = Block::getAllGlobalScopes();
    try {
      $this->runBatch($blocks, function (Block $block) use ($blocks): void {
        if ($block->id === $blocks->first()->id) {
          Block::addGlobalScope('hidden-batch', fn (Builder $query) => $query->where('id', '!=', $blocks[1]->id));
        }
      });
      $this->fail('A scoped-out prefetched block must not be exposed.');
    } catch (ModelNotFoundException) {
      $this->assertTrue(true);
    } finally {
      Block::setAllGlobalScopes($scopes);
    }
  }

  #[Test]
  public function writes_during_the_bulk_select_discard_stale_hydration(): void
  {
    [$blocks] = $this->fixture(2);
    $changed = false;
    DB::listen(function ($query) use ($blocks, &$changed): void {
      if ($this->watch && ! $changed && preg_match('/^select \* from ["`]wbcms_blocks["`].* in \(/i', $query->sql)) {
        $changed = true;
        DB::table('wbcms_blocks')->where('id', $blocks[1]->id)->update(['sort_order' => 77]);
      }
    });
    $this->runBatch($blocks, function (Block $block) use ($blocks): void {
      if ($block->id === $blocks[1]->id) {
        $this->assertSame(77, $block->sort_order);
      }
    });
    $this->assertTrue($changed);
  }

  #[Test]
  public function actual_document_sizes_split_batches_and_oversized_owners_keep_single_reads(): void
  {
    [$blocks] = $this->fixture(2);
    foreach ($blocks as $block) {
      $block->update(['settings' => json_encode(['plugin' => str_repeat('x', 600000)])]);
    }
    $this->runBatch($blocks, fn (Block $block) => $this->assertSame(600000, strlen(json_decode($block->settings, true)['plugin'])));
    $this->assertSame([1, 1], $this->blockBatchSizes);
    $blocks->first()->update(['settings' => json_encode(['plugin' => str_repeat('x', 1100000)])]);
    $this->blockBatchSizes = [];
    $this->runBatch($blocks->take(1), fn (Block $block) => $this->assertSame(1100000, strlen(json_decode($block->settings, true)['plugin'])));
    $this->assertSame([], $this->blockBatchSizes);
  }

  #[Test]
  public function node_and_translation_payload_limits_also_split_read_groups(): void
  {
    [$blocks, $locales] = $this->fixture(2);
    foreach ($blocks as $block) {
      $block->update(['settings' => json_encode(['plugin' => range(1, 2600)])]);
    }
    $this->runBatch($blocks, fn (Block $block) => $this->assertCount(2600, json_decode($block->settings, true)['plugin']));
    $this->assertSame([1, 1], $this->blockBatchSizes);
    foreach ($blocks as $block) {
      $block->update(['settings' => null]);
      DB::table('wbcms_block_text_translations')->insert(['block_id' => $block->id, 'locale_id' => $locales->first()->id, 'content' => str_repeat('ü', 300000)]);
    }
    $this->blockBatchSizes = [];
    $this->runBatch($blocks, function (Block $block): void {
      $target = app(RestoredBlockReads::class)->forNormalization($block, false);
      $this->assertSame(600000, strlen($target->textTranslations->sole()->content));
    });
    $this->assertSame([1, 1], $this->blockBatchSizes);
  }

  #[Test]
  public function writes_during_budget_reads_keep_the_native_path(): void
  {
    [$blocks] = $this->fixture(2);
    $changed = false;
    DB::listen(function ($query) use ($blocks, &$changed): void {
      if ($this->watch && ! $changed && str_contains($query->sql, 'budget_rows')) {
        $changed = true;
        DB::table('wbcms_blocks')->where('id', $blocks[1]->id)->update(['sort_order' => 91]);
      }
    });
    $this->runBatch($blocks, function (Block $block) use ($blocks): void {
      if ($block->id === $blocks[1]->id) {
        $this->assertSame(91, $block->sort_order);
      }
    });
    $this->assertTrue($changed);
    $this->assertSame([], $this->blockBatchSizes);
  }

  #[Test]
  public function large_translation_row_counts_split_groups_without_losing_historical_rows(): void
  {
    [$blocks, $locales] = $this->fixture(2);
    foreach ($blocks as $block) {
      $rows = [];
      for ($i = 0; $i < 700; $i++) {
        $rows[] = ['block_id' => $block->id, 'locale_id' => $locales->first()->id, 'field' => 'field_'.$i, 'value' => 'Saved'];
      }
      DB::table('wbcms_block_plugin_translations')->insert($rows);
    }
    $this->runBatch($blocks, fn (Block $block) => $this->assertNull(app(RestoredBlockReads::class)->forNormalization($block, false)));
    $this->assertSame([1, 1], $this->blockBatchSizes);
    $this->assertSame(1400, DB::table('wbcms_block_plugin_translations')->count());
  }

  private function runBatch($blocks, \Closure $write): void
  {
    $snapshots = $blocks->map(fn (Block $block) => ['snapshot_id' => $block->id]);
    $map = $blocks->mapWithKeys(fn (Block $block) => [$block->id => $block->id])->all();
    app(RevisionContentUpdates::class)->during(function () use ($snapshots, $map, $write): void {
      $this->watch = true;
      try {
        app(RestoredBlockReads::class)->each($snapshots, $map, $write);
      } finally {
        $this->watch = false;
      }
    });
  }

  private function fixture(int $count, bool $translations = false): array
  {
    return PublicSearchIndexer::coalescing(function () use ($count, $translations): array {
      $site = Site::create(['name' => 'Batch restoration', 'handle' => 'batch-restoration']);
      $locales = collect(['en', 'tr', 'de', 'es', 'it', 'fr'])->map(fn ($code) => Locale::create(['code' => $code, 'name' => $code, 'is_default' => $code === 'en', 'is_enabled' => true]));
      $site->locales()->sync($locales->mapWithKeys(fn ($locale) => [$locale->id => ['is_enabled' => true]])->all());
      $page = Page::create(['site_id' => $site->id, 'title' => 'Batch page', 'status' => 'draft']);
      $type = BlockType::create(['name' => 'Plain text', 'slug' => 'plain_text', 'source_type' => 'static', 'status' => 'published']);
      $blocks = new Collection;
      $rows = [];
      for ($i = 0; $i < $count; $i++) {
        $block = Block::create(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => 'plain_text', 'sort_order' => $i, 'settings' => '{"alignment":"left"}', 'meta' => null]);
        $blocks->push($block);
        if ($translations) {
          foreach ($locales as $locale) {
            $rows[] = ['block_id' => $block->id, 'locale_id' => $locale->id, 'content' => 'Saved '.$locale->code];
          }
        }
      }
      if ($rows !== []) {
        DB::table('wbcms_block_text_translations')->insert($rows);
      }

      return [$blocks, $locales];
    });
  }
}

class RestoredBatchTestUser extends CmsSqlTestUser
{
  protected $table = 'users';

  public function getForeignKey(): string
  {
    return 'user_id';
  }
}
