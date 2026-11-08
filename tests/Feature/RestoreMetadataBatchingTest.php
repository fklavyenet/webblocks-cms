<?php

namespace WebBlocks\Cms\Tests\Feature;

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
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Relational\PersistentDocuments;
use WebBlocks\Cms\Support\Revisions\RestoredBlockReads;
use WebBlocks\Cms\Support\Revisions\RevisionContentUpdates;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class RestoreMetadataBatchingTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(RestoreMetadataTestUser::class, 'App\\Models\\User');
    }
    parent::setUp();
  }

  #[Test]
  public function restored_meta_groups_keep_typed_values_and_every_primary_model_event(): void
  {
    [$page, $type, $locales] = $this->fixture();
    $documents = app(PersistentDocuments::class);
    $events = [];
    foreach (['saving', 'creating', 'created', 'saved'] as $event) {
      BlockTextTranslation::{$event}(function ($row) use (&$events, $event): void {
        $events[$row->block_id.':'.$row->locale_id][] = $event;
      });
    }
    $translationDocuments = 0;
    DB::listen(function ($query) use (&$translationDocuments): void {
      if (str_starts_with($query->sql, 'insert into "wbcms_persistent_documents"') && str_contains($query->sql, 'block_text_translation_id')) {
        $translationDocuments++;
      }
    });
    app(RevisionContentUpdates::class)->during(function () use ($documents, $page, $type, $locales): void {
      for ($i = 0; $i < 4; $i++) {
        $block = new Block(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => 'plain_text', 'meta' => '{"source":"block"}']);
        $this->assertTrue($documents->createRestored($block));
        app(RestoredBlockReads::class)->duringBlock($block->id, function (Block $target) use ($locales, $documents): void {
          foreach ($locales as $locale) {
            $row = $target->textTranslations()->make(['locale_id' => $locale->id, 'content' => $locale->code, 'meta' => json_encode(['source' => $locale->code, 'values' => [false, null, 1, '1', []]])]);
            $this->assertTrue($documents->createRestored($row));
          }
        });
      }
    });
    $this->assertCount(24, $events);
    foreach ($events as $sequence) {
      $this->assertSame(['saving', 'creating', 'created', 'saved'], $sequence);
    }
    $this->assertLessThan(24, $translationDocuments, 'Metadata headers are grouped; primary translation saves stay individual.');
    foreach ($page->blocks()->with('textTranslations')->get() as $block) {
      $this->assertSame(['source' => 'block'], json_decode($block->meta, true));
      foreach ($block->textTranslations as $row) {
        $this->assertSame(['source' => $row->content, 'values' => [false, null, 1, '1', []]], json_decode($row->meta, true));
      }
    }
  }

  #[Test]
  public function saved_observers_can_read_and_amend_metadata_through_raw_sql(): void
  {
    [$page, $type, $locales] = $this->fixture();
    $documents = app(PersistentDocuments::class);
    $saved = 0;
    BlockTextTranslation::created(function ($row): void {
      $this->assertTrue(DB::table($row->getTable())->where('id', $row->id)->exists());
      $this->assertFalse(DB::table('wbcms_persistent_documents')->where('block_text_translation_id', $row->id)->exists(), 'The current row metadata is still written after created, as before.');
    });
    BlockTextTranslation::saved(function ($row) use (&$saved): void {
      $saved++;
      $source = DB::table('wbcms_persistent_documents as d')->join('wbcms_persistent_metadata as m', 'm.document_id', '=', 'd.id')
        ->where('d.block_text_translation_id', $row->id)->value('m.source');
      $this->assertSame($row->content, $source);
      $document = DB::table('wbcms_persistent_documents')->where('block_text_translation_id', $row->id)->value('id');
      DB::table('wbcms_persistent_metadata')->where('document_id', $document)->update(['source' => 'Observer '.$row->content]);
    });
    app(RevisionContentUpdates::class)->during(function () use ($documents, $page, $type, $locales): void {
      $block = new Block(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => 'plain_text']);
      $this->assertTrue($documents->createRestored($block));
      foreach ($locales as $locale) {
        $row = $block->textTranslations()->make(['locale_id' => $locale->id, 'content' => $locale->code, 'meta' => json_encode(['source' => $locale->code])]);
        $this->assertTrue($documents->createRestored($row));
      }
    });
    $this->assertSame(6, $saved);
    foreach ($page->blocks()->sole()->textTranslations as $row) {
      $this->assertSame(['source' => 'Observer '.$row->content], json_decode($row->meta, true));
    }
  }

  #[Test]
  public function nested_rollback_preserves_earlier_metadata_and_discards_rolled_back_owners(): void
  {
    [$page, $type, $locales] = $this->fixture();
    $documents = app(PersistentDocuments::class);
    app(RevisionContentUpdates::class)->during(function () use ($documents, $page, $type, $locales): void {
      $block = new Block(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => 'plain_text']);
      $documents->createRestored($block);
      $make = function ($locale, string $source) use ($documents, $block): BlockTextTranslation {
        $row = $block->textTranslations()->make(['locale_id' => $locale->id, 'content' => $source, 'meta' => json_encode(['source' => $source])]);
        $this->assertTrue($documents->createRestored($row));

        return $row;
      };
      $retained = $make($locales[0], 'Retained');
      try {
        DB::transaction(function () use ($make, $locales): void {
          $make($locales[1], 'Rolled back');
          throw new RuntimeException('Synthetic nested rollback');
        });
      } catch (RuntimeException) {
      }
      $this->assertSame(['source' => 'Retained'], json_decode($retained->fresh()->meta, true));
      $make($locales[1], 'Replacement');
    });
    $rows = $page->blocks()->sole()->textTranslations()->orderBy('locale_id')->get();
    $this->assertCount(2, $rows);
    $this->assertSame(['Retained', 'Replacement'], $rows->map(fn ($row) => json_decode($row->meta, true)['source'])->all());
    $this->assertSame(2, DB::table('wbcms_persistent_documents')->whereNotNull('block_text_translation_id')->count());
  }

  #[Test]
  public function metadata_written_by_creation_observers_is_replaced_without_stale_typed_rows(): void
  {
    [$page, $type, $locales] = $this->fixture();
    $documents = app(PersistentDocuments::class);
    BlockTextTranslation::created(function ($row) use ($documents): void {
      $documents->write($row, 'meta', '{"source":"Created observer","obsolete":[1,2,3]}');
    });
    app(RevisionContentUpdates::class)->during(function () use ($documents, $page, $type, $locales): void {
      $block = new Block(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => 'plain_text']);
      $documents->createRestored($block);
      app(RestoredBlockReads::class)->duringBlock($block->id, function (Block $target) use ($documents, $locales): void {
        foreach ($locales as $locale) {
          $row = $target->textTranslations()->make(['locale_id' => $locale->id, 'content' => $locale->code, 'meta' => json_encode(['source' => $locale->code, 'values' => [false, null, 1, '1']])]);
          $this->assertTrue($documents->createRestored($row));
        }
      });
    });
    foreach ($page->blocks()->sole()->textTranslations as $row) {
      $this->assertSame(['source' => $row->content, 'values' => [false, null, 1, '1']], json_decode($row->meta, true));
      $document = DB::table('wbcms_persistent_documents')->where('block_text_translation_id', $row->id)->sole();
      $this->assertFalse(DB::table('wbcms_persistent_nodes')->where('document_id', $document->id)->where('key_text', 'obsolete')->exists());
    }
  }

  #[Test]
  public function an_observer_deleting_a_new_owner_cannot_leave_buffered_orphan_metadata(): void
  {
    [$page, $type, $locales] = $this->fixture();
    $documents = app(PersistentDocuments::class);
    BlockTextTranslation::saved(function ($row) use ($locales): void {
      if ($row->locale_id === $locales[0]->id) {
        DB::table($row->getTable())->where('id', $row->id)->delete();
      }
    });
    app(RevisionContentUpdates::class)->during(function () use ($documents, $page, $type, $locales): void {
      $block = new Block(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => 'plain_text']);
      $documents->createRestored($block);
      app(RestoredBlockReads::class)->duringBlock($block->id, function (Block $target) use ($documents, $locales): void {
        foreach ($locales->take(2) as $locale) {
          $row = $target->textTranslations()->make(['locale_id' => $locale->id, 'content' => $locale->code, 'meta' => json_encode(['source' => $locale->code])]);
          $this->assertTrue($documents->createRestored($row));
        }
      });
    });
    $row = $page->blocks()->sole()->textTranslations()->sole();
    $this->assertSame($locales[1]->id, $row->locale_id);
    $this->assertSame(['source' => $locales[1]->code], json_decode($row->meta, true));
    $this->assertSame(1, DB::table('wbcms_persistent_documents')->whereNotNull('block_text_translation_id')->count());
  }

  #[Test]
  public function a_caught_batch_failure_still_aborts_the_outer_transaction_and_does_not_poison_the_next_scope(): void
  {
    [$page, $type, $locales] = $this->fixture();
    $documents = app(PersistentDocuments::class);
    $fail = false;
    DB::connection()->beforeExecuting(function ($sql) use (&$fail): void {
      if ($fail && str_starts_with($sql, 'insert into "wbcms_persistent_nodes"')) {
        $fail = false;
        throw new RuntimeException('Synthetic meta batch failure');
      }
    });
    $run = function (bool $breakBatch) use ($page, $type, $locales, $documents, &$fail): void {
      app(RevisionContentUpdates::class)->during(function () use ($page, $type, $locales, $documents, $breakBatch, &$fail): void {
        $block = new Block(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => 'plain_text']);
        $documents->createRestored($block);
        app(RestoredBlockReads::class)->duringBlock($block->id, function (Block $target) use ($documents, $locales, $breakBatch, &$fail): void {
          $row = $target->textTranslations()->make(['locale_id' => $locales[0]->id, 'content' => 'Saved', 'meta' => '{"source":"Saved"}']);
          $documents->createRestored($row);
          $fail = $breakBatch;
          try {
            DB::table('wbcms_persistent_documents')->where('block_text_translation_id', $row->id)->count();
          } catch (RuntimeException) {
            // An observer swallowing the transport/storage exception cannot
            // convert a partially flushed batch into a successful restoration.
          }
        });
      });
    };
    try {
      $run(true);
      $this->fail('The outer restoration must retain the batch failure.');
    } catch (RuntimeException $exception) {
      $this->assertSame('Synthetic meta batch failure', $exception->getMessage());
    }
    $this->assertSame(0, $page->blocks()->count());
    $run(false);
    $this->assertSame(['source' => 'Saved'], json_decode($page->blocks()->sole()->textTranslations()->sole()->meta, true));
  }

  #[Test]
  public function a_model_event_veto_rolls_back_the_whole_restoration_and_the_next_attempt_succeeds(): void
  {
    [$page, $type, $locales] = $this->fixture();
    $block = Block::create(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => 'plain_text']);
    foreach ($locales as $locale) {
      $block->textTranslations()->create(['locale_id' => $locale->id, 'content' => $locale->code, 'meta' => '{"source":"Saved"}']);
    }
    $manager = app(PageRevisionManager::class);
    $revision = $manager->capture($page);
    $class = 'App\\Models\\User';
    $actor = $this->cmsActor((new $class)->forceFill(['role' => 'super_admin', 'is_active' => true]));
    $cancel = true;
    BlockTextTranslation::creating(function ($row) use (&$cancel, $locales): bool {
      return ! $cancel || $row->locale_id !== $locales[1]->id;
    });
    try {
      $manager->restore($page, $revision, $actor);
      $this->fail('A cancelled model creation must abort the complete restore.');
    } catch (RuntimeException $exception) {
      $this->assertSame('Restoration was cancelled. No changes were saved.', $exception->getMessage());
    }
    $this->assertSame([$block->id], $page->blocks()->pluck('id')->all());
    $this->assertSame(1, $page->revisions()->count());
    $cancel = false;
    $manager->restore($page, $revision, $actor);
    $this->assertSame(6, $page->blocks()->sole()->textTranslations()->count());
    $this->assertSame(3, $page->revisions()->count());
  }

  public static function normalizationCounts(): array
  {
    return [[4], [51]];
  }

  #[Test]
  #[DataProvider('normalizationCounts')]
  public function normalization_keeps_metadata_grouped_without_delaying_primary_events(int $count): void
  {
    [$page, $type, $locales] = $this->fixture();
    $blocks = PublicSearchIndexer::coalescing(fn () => collect(range(1, $count))->map(fn () => Block::create(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => 'plain_text', 'meta' => null])));
    $documents = app(PersistentDocuments::class);
    $watch = false;
    $inserts = 0;
    $sequence = [];
    DB::listen(function ($query) use (&$watch, &$inserts): void {
      if ($watch && str_starts_with($query->sql, 'insert into "wbcms_persistent_documents"') && str_contains($query->sql, 'block_text_translation_id')) {
        $inserts++;
      }
    });
    BlockTextTranslation::saved(function ($row) use (&$sequence): void {
      $sequence[] = $row->block_id.':'.$row->locale_id;
    });
    $expected = [];
    app(RevisionContentUpdates::class)->during(function () use ($blocks, $locales, $documents, &$watch, &$sequence, &$expected): void {
      $watch = true;
      app(RestoredBlockReads::class)->each(
        $blocks->map(fn ($block) => ['snapshot_id' => $block->id]),
        $blocks->mapWithKeys(fn ($block) => [$block->id => $block->id])->all(),
        function (Block $target) use ($locales, $documents, &$sequence, &$expected): void {
          foreach ($locales as $locale) {
            $row = $target->textTranslations()->make(['locale_id' => $locale->id, 'content' => $locale->code, 'meta' => '{"source":"Saved","typed":[false,null,1,"1",[],{}]}']);
            $this->assertTrue($documents->createRestored($row));
            $expected[] = $target->id.':'.$locale->id;
          }
          $normalized = app(RestoredBlockReads::class)->forNormalization($target, false);
          $this->assertCount(6, $normalized->textTranslations);
          $this->assertJsonStringEqualsJsonString('{"source":"Saved","typed":[false,null,1,"1",[],{}]}', $normalized->textTranslations->first()->meta);
          app(BlockTranslationWriter::class)->normalizeCanonicalStorage($normalized);
          $sequence[] = $target->id.':normalized';
          $expected[] = $target->id.':normalized';
        },
      );
    });
    $watch = false;
    $this->assertSame($expected, $sequence);
    $this->assertLessThan($count, $inserts, 'Normalization must not force a six-owner flush after every block.');
    $this->assertSame($count * 6, DB::table('wbcms_persistent_documents')->whereNotNull('block_text_translation_id')->count());
    foreach (BlockTextTranslation::all() as $row) {
      $this->assertJsonStringEqualsJsonString('{"source":"Saved","typed":[false,null,1,"1",[],{}]}', $row->meta);
    }
  }

  #[Test]
  public function normalization_readback_matches_sql_and_does_not_leak_mutable_metadata(): void
  {
    [$page, $type, $locales] = $this->fixture();
    $documents = app(PersistentDocuments::class);
    $values = [null, '{"source":"JSON","nested":{"empty":{},"array":[]},"double":1.25}', ['source' => 'array', 'nested' => (object) ['value' => 'Saved']], 'plain text', 'false', ['site_id' => '01', 'status' => false]];
    app(RevisionContentUpdates::class)->during(function () use ($page, $type, $locales, $documents, $values): void {
      $block = new Block(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => 'plain_text', 'meta' => null]);
      $documents->createRestored($block);
      app(RestoredBlockReads::class)->duringBlock($block->id, function (Block $target) use ($locales, $documents, $values): void {
        foreach ($locales as $i => $locale) {
          $row = $target->textTranslations()->make(['locale_id' => $locale->id, 'content' => $locale->code, 'meta' => $values[$i]]);
          $documents->createRestored($row);
        }
        $first = app(RestoredBlockReads::class)->forNormalization($target, false);
        $saved = $first->textTranslations->mapWithKeys(fn ($row) => [$row->locale_id => serialize($row->meta)])->all();
        $mutable = $first->textTranslations->firstWhere('locale_id', $locales[2]->id)->meta;
        $mutable['nested']->value = 'Memory only';
        $again = app(RestoredBlockReads::class)->forNormalization($target, false);
        $this->assertSame('Saved', $again->textTranslations->firstWhere('locale_id', $locales[2]->id)->meta['nested']->value);
        $documents->flushRestoreMetadata(DB::connection());
        foreach ($again->textTranslations as $row) {
          $this->assertSame($saved[$row->locale_id], serialize($row->fresh()->meta));
        }
      });
    });
  }

  #[Test]
  public function normalization_sql_reads_and_late_read_observers_still_flush_before_observing_meta(): void
  {
    [$page, $type, $locales] = $this->fixture();
    $documents = app(PersistentDocuments::class);
    $observerReads = 0;
    app(RevisionContentUpdates::class)->during(function () use ($page, $type, $locales, $documents, &$observerReads): void {
      $block = new Block(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => 'plain_text', 'meta' => null]);
      $documents->createRestored($block);
      app(RestoredBlockReads::class)->duringBlock($block->id, function (Block $target) use ($locales, $documents, &$observerReads): void {
        foreach ($locales as $locale) {
          $row = $target->textTranslations()->make(['locale_id' => $locale->id, 'content' => $locale->code, 'meta' => '{"source":"Saved"}']);
          $documents->createRestored($row);
        }
        app(RestoredBlockReads::class)->forNormalization($target, false);
        BlockTextTranslation::retrieved(function ($row) use (&$observerReads): void {
          $source = DB::table('wbcms_persistent_documents as d')->join('wbcms_persistent_metadata as m', 'm.document_id', '=', 'd.id')->where('d.block_text_translation_id', $row->id)->value('m.source');
          $this->assertSame('Saved', $source);
          $observerReads++;
        });
        $normalized = app(RestoredBlockReads::class)->forNormalization($target, false);
        $this->assertCount(6, $normalized->textTranslations);
      });
    });
    $this->assertSame(6, $observerReads);
  }

  #[Test]
  public function mixed_flushed_and_pending_metadata_keeps_observer_changes_during_normalization(): void
  {
    [$page, $type, $locales] = $this->fixture();
    $documents = app(PersistentDocuments::class);
    BlockTextTranslation::saved(function ($row) use ($locales): void {
      if ($row->locale_id === $locales->first()->id) {
        $id = DB::table('wbcms_persistent_documents')->where('block_text_translation_id', $row->id)->value('id');
        DB::table('wbcms_persistent_metadata')->where('document_id', $id)->update(['source' => 'Observer']);
      }
    });
    app(RevisionContentUpdates::class)->during(function () use ($page, $type, $locales, $documents): void {
      $block = new Block(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => 'plain_text', 'meta' => null]);
      $documents->createRestored($block);
      app(RestoredBlockReads::class)->duringBlock($block->id, function (Block $target) use ($locales, $documents): void {
        foreach ($locales as $locale) {
          $documents->createRestored($target->textTranslations()->make(['locale_id' => $locale->id, 'content' => $locale->code, 'meta' => '{"source":"Saved"}']));
        }
        $normalized = app(RestoredBlockReads::class)->forNormalization($target, false);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($normalized);
        foreach ($normalized->textTranslations as $row) {
          $this->assertSame(['source' => $row->locale_id === $locales->first()->id ? 'Observer' : 'Saved'], json_decode($row->meta, true));
          $this->assertSame($row->meta, $row->fresh()->meta);
        }
      });
    });
  }

  #[Test]
  public function a_failed_normalization_read_scope_cannot_change_ordinary_model_read_visibility(): void
  {
    [$page, $type, $locales] = $this->fixture();
    $documents = app(PersistentDocuments::class);
    $inserts = 0;
    DB::listen(function ($query) use (&$inserts): void {
      if (str_starts_with($query->sql, 'insert into "wbcms_persistent_documents"') && str_contains($query->sql, 'block_text_translation_id')) {
        $inserts++;
      }
    });
    app(RevisionContentUpdates::class)->during(function () use ($page, $type, $locales, $documents, &$inserts): void {
      $block = new Block(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => 'plain_text', 'meta' => null]);
      $documents->createRestored($block);
      app(RestoredBlockReads::class)->duringBlock($block->id, function (Block $target) use ($locales, $documents, &$inserts): void {
        $row = $target->textTranslations()->make(['locale_id' => $locales->first()->id, 'content' => 'Saved', 'meta' => '{"source":"Saved"}']);
        $documents->createRestored($row);
        try {
          $documents->duringRestoreNormalization(fn () => throw new RuntimeException('Synthetic normalization failure'));
        } catch (RuntimeException) {
        }
        $this->assertSame(0, $inserts);
        $this->assertSame(['source' => 'Saved'], json_decode($row->fresh()->meta, true));
        $this->assertSame(1, $inserts, 'Outside normalization, an ordinary ORM read must flush before hydration.');
      });
    });
  }

  private function fixture(): array
  {
    return PublicSearchIndexer::coalescing(function (): array {
      $site = Site::create(['name' => 'Restore meta fixture', 'handle' => 'restore-meta-fixture']);
      $locales = collect(['en', 'tr', 'de', 'fr', 'es', 'it'])->map(fn ($code) => Locale::create(['code' => $code, 'name' => $code, 'is_default' => $code === 'en', 'is_enabled' => true]));
      $site->locales()->sync($locales->mapWithKeys(fn ($locale) => [$locale->id => ['is_enabled' => true]])->all());
      $type = BlockType::create(['name' => 'Plain text', 'slug' => 'plain_text', 'source_type' => 'static']);
      $page = Page::create(['site_id' => $site->id, 'title' => 'Metadata page', 'status' => 'draft']);

      return [$page, $type, $locales];
    });
  }
}

class RestoreMetadataTestUser extends CmsSqlTestUser
{
  protected $table = 'users';

  public function getForeignKey(): string
  {
    return 'user_id';
  }
}
