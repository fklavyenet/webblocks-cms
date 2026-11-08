<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Database\ContentVersionUpdates;
use WebBlocks\Cms\Support\Seo\SeoSourceVersion;
use WebBlocks\Cms\Tests\TestCase;

class SnapshotOwnerReuseTest extends TestCase
{
  private bool $watch = false;

  private int $reads = 0;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    DB::listen(function ($query): void {
      if ($this->watch && $this->ownerRead($query->sql)) {
        $this->reads++;
      }
    });
  }

  #[Test]
  public function ownership_is_reused_only_inside_nested_transactional_snapshot_writes(): void
  {
    [$document] = $this->fixture();
    $seo = app(SeoSourceVersion::class);
    $before = $seo->current();
    $result = $this->record(fn () => DB::transaction(fn () => $seo->duringSnapshotDocument(DB::connection(), $document, function () use ($seo, $document): string {
      $this->touchNode($document);
      $seo->duringSnapshotDocument(DB::connection(), $document, function () use ($document): void {
        $this->touchNode($document);
        $this->touchNode($document);
      });
      $this->touchNode($document);

      return 'complete';
    })));
    $this->assertSame('complete', $result);
    $this->assertSame(1, $this->reads);
    $this->assertSame($before, $seo->current());
    $this->record(fn () => $seo->duringSnapshotDocument(DB::connection(), $document, function () use ($document): void {
      $this->touchNode($document);
      $this->touchNode($document);
    }));
    $this->assertSame(2, $this->reads, 'No transaction means no ownership reuse.');
    $this->record(fn () => DB::transaction(fn () => $seo->duringSnapshotDocument(DB::connection(), $document, fn () => $this->touchNode($document))));
    $this->assertSame(1, $this->reads, 'A later scope must read ownership again.');
  }

  #[Test]
  public function raw_owner_changes_invalidate_even_while_a_generation_is_pending(): void
  {
    [$document, $block] = $this->fixture();
    $seo = app(SeoSourceVersion::class);
    DB::transaction(fn () => app(ContentVersionUpdates::class)->during(DB::connection(), fn () => $seo->duringSnapshotDocument(DB::connection(), $document, function () use ($seo, $document, $block): void {
      $this->touchNode($document); // Known non-content snapshot.
      DB::table('wbcms_blocks')->where('id', $block->id)->update(['title' => 'Pending generation']);
      DB::table('wbcms_persistent_documents')->where('id', $document)->update(['block_id' => $block->id]);
      $flushed = $seo->current();
      $this->touchNode($document);
      $this->assertNotSame($flushed, $seo->current(), 'The changed owner must not remain classified as a snapshot.');
    })));
  }

  #[Test]
  public function rolled_back_owner_changes_cannot_leave_negative_ownership_cached(): void
  {
    [$document, $block] = $this->fixture();
    DB::table('wbcms_persistent_documents')->where('id', $document)->update(['block_id' => $block->id]);
    $seo = app(SeoSourceVersion::class);
    DB::transaction(fn () => $seo->duringSnapshotDocument(DB::connection(), $document, function () use ($seo, $document): void {
      $this->touchNode($document);
      try {
        DB::transaction(function () use ($document): void {
          DB::table('wbcms_persistent_documents')->where('id', $document)->update(['block_id' => null]);
          $this->touchNode($document); // Cache the temporary non-content owner.
          throw new RuntimeException('Synthetic savepoint rollback');
        });
      } catch (RuntimeException) {
      }
      $before = $seo->current();
      $this->touchNode($document);
      $this->assertNotSame($before, $seo->current());
    }));
  }

  #[Test]
  public function mutations_during_owner_reads_do_not_repopulate_stale_classification(): void
  {
    [$document, $block] = $this->fixture();
    $mutate = true;
    DB::listen(function ($query) use (&$mutate, $document, $block): void {
      if ($mutate && $this->ownerRead($query->sql)) {
        $mutate = false;
        DB::table('wbcms_persistent_documents')->where('id', $document)->update(['block_id' => $block->id]);
      }
    });
    $seo = app(SeoSourceVersion::class);
    DB::transaction(fn () => $seo->duringSnapshotDocument(DB::connection(), $document, function () use ($seo, $document): void {
      $this->touchNode($document);
      $before = $seo->current();
      $this->touchNode($document);
      $this->assertNotSame($before, $seo->current());
    }));
  }

  #[Test]
  public function other_connections_do_not_reuse_the_scope_and_their_mutations_invalidate_it(): void
  {
    [$document, $block] = $this->fixture();
    $connection = DB::connection();
    $other = new SQLiteConnection($connection->getPdo(), ':memory:', '', ['driver' => 'sqlite', 'name' => 'owner-alias']);
    $other->setEventDispatcher(app('events'));
    $seo = app(SeoSourceVersion::class);
    $this->record(fn () => DB::transaction(fn () => $seo->duringSnapshotDocument($connection, $document, function () use ($other, $seo, $document, $block): void {
      $this->touchNode($document);
      $other->table('wbcms_persistent_nodes')->where('document_id', $document)->where('node_index', 0)->update(['ordinal' => 0]);
      $this->assertSame(2, $this->reads, 'The alias connection must perform its own owner read.');
      $other->table('wbcms_persistent_documents')->where('id', $document)->update(['block_id' => $block->id]);
      $before = $seo->current();
      $this->touchNode($document);
      $this->assertNotSame($before, $seo->current());
    })));
  }

  #[Test]
  public function mixed_missing_and_unrecognized_document_writes_remain_conservative(): void
  {
    [$document, $block, $contentDocument] = $this->fixture();
    $seo = app(SeoSourceVersion::class);
    DB::transaction(fn () => $seo->duringSnapshotDocument(DB::connection(), $document, function () use ($seo, $document, $contentDocument): void {
      $this->touchNode($document);
      $before = $seo->current();
      DB::table('wbcms_persistent_nodes')->insert(array_map(fn ($id) => [
        'document_id' => $id, 'node_index' => 9000, 'parent_index' => 0, 'ordinal' => 99,
        'key_kind' => 'string', 'key_text' => 'synthetic', 'kind' => 'value', 'value_kind' => 'string', 'value' => 'Synthetic',
      ], [$document, $contentDocument]));
      $this->assertNotSame($before, $seo->current(), 'A mixed bulk INSERT still affects live content.');
      $before = $seo->current();
      DB::table('wbcms_persistent_nodes')->whereIn('document_id', [$document])->where('node_index', 0)->update(['ordinal' => 0]);
      $this->assertNotSame($before, $seo->current(), 'Unrecognized predicates must not bypass invalidation.');
      $before = $seo->current();
      DB::insert('insert into wbcms_persistent_nodes (document_id, node_index, parent_index, ordinal, key_kind, key_text, kind, value_kind, value) values (:document, :node, :parent, :ordinal, :key_kind, :key, :kind, :value_kind, :value)', [
        'document' => $document, 'node' => 9001, 'parent' => 0, 'ordinal' => 100,
        'key_kind' => 'string', 'key' => 'named', 'kind' => 'value', 'value_kind' => 'string', 'value' => 'Synthetic',
      ]);
      $this->assertNotSame($before, $seo->current(), 'Named raw bindings must be handled conservatively.');
      DB::table('wbcms_persistent_documents')->where('id', $document)->delete();
      $before = $seo->current();
      $this->touchNode($document);
      $this->assertNotSame($before, $seo->current(), 'A missing owner remains conservatively content-affecting.');
    }));
  }

  #[Test]
  public function exceptions_end_the_scope_without_suppressing_later_owner_reads(): void
  {
    [$document] = $this->fixture();
    $seo = app(SeoSourceVersion::class);
    $this->record(fn () => DB::transaction(function () use ($document, $seo): void {
      try {
        $seo->duringSnapshotDocument(DB::connection(), $document, function () use ($document): void {
          $this->touchNode($document);
          $this->touchNode($document);
          throw new RuntimeException('Synthetic write failure');
        });
      } catch (RuntimeException) {
      }
      $this->touchNode($document);
      $this->touchNode($document);
    }));
    $this->assertSame(3, $this->reads);
  }

  #[Test]
  public function parent_deletion_cascades_invalidate_cached_snapshot_ownership(): void
  {
    [$document] = $this->fixture();
    $revision = DB::table('wbcms_persistent_documents')->where('id', $document)->value('page_revision_id');
    $seo = app(SeoSourceVersion::class);
    DB::transaction(fn () => $seo->duringSnapshotDocument(DB::connection(), $document, function () use ($seo, $document, $revision): void {
      $this->touchNode($document);
      DB::table('wbcms_page_revisions')->where('id', $revision)->delete();
      $this->assertDatabaseMissing('wbcms_persistent_documents', ['id' => $document]);
      $before = $seo->current();
      $this->touchNode($document);
      $this->assertNotSame($before, $seo->current());
    }));
  }

  private function touchNode(int $document): void
  {
    DB::table('wbcms_persistent_nodes')->where('document_id', $document)->where('node_index', 0)->update(['ordinal' => 0]);
  }

  private function record(callable $write): mixed
  {
    $this->reads = 0;
    $this->watch = true;
    try {
      return $write();
    } finally {
      $this->watch = false;
    }
  }

  private function ownerRead(string $sql): bool
  {
    return str_starts_with($sql, 'select') && str_contains($sql, 'wbcms_persistent_documents')
      && str_contains($sql, 'page_slot_id') && str_contains($sql, 'page_layout_id');
  }

  private function fixture(): array
  {
    $site = Site::create(['name' => 'Owner studio', 'handle' => 'owner']);
    $page = Page::create(['site_id' => $site->id, 'status' => 'draft']);
    $revision = PageRevision::create(['page_id' => $page->id, 'site_id' => $site->id, 'snapshot' => ['schema_version' => 2, 'blocks' => []]]);
    $block = Block::create(['page_id' => $page->id, 'type' => 'plain_text', 'meta' => '{"extension":[]}']);

    return [
      (int) DB::table('wbcms_persistent_documents')->where('page_revision_id', $revision->id)->value('id'),
      $block,
      (int) DB::table('wbcms_persistent_documents')->where('block_id', $block->id)->value('id'),
    ];
  }
}
