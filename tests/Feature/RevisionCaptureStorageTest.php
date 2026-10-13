<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\RevisionCaptureEvent;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\SharedSlotRevision;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Revisions\RevisionCaptureHistory;
use WebBlocks\Cms\Support\Revisions\SnapshotContent;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotRevisionManager;
use WebBlocks\Cms\Tests\TestCase;

class RevisionCaptureStorageTest extends TestCase
{
  use RefreshDatabase;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  public static function owners(): array
  {
    return ['pages' => [false], 'shared slots' => [true]];
  }

  private function owner(bool $shared): Page|SharedSlot
  {
    $site = Site::query()->create(['name' => 'Synthetic', 'handle' => 'synthetic', 'is_primary' => true]);
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    SlotType::query()->create(['name' => 'Main', 'slug' => 'main', 'status' => 'published']);

    return $shared
      ? SharedSlot::query()->create(['site_id' => $site->id, 'name' => 'Original', 'handle' => 'original', 'slot_name' => 'main', 'is_active' => true])
      : Page::query()->create(['site_id' => $site->id, 'title' => 'Original', 'status' => Page::STATUS_DRAFT]);
  }

  private function capture(Page|SharedSlot $owner, string $event = 'saved', bool $force = false): PageRevision|SharedSlotRevision
  {
    return $owner instanceof Page
      ? app(PageRevisionManager::class)->capture($owner, label: $event, reason: 'Reason '.$event, event: $event, source: 'system', force: $force)
      : app(SharedSlotRevisionManager::class)->capture($owner, sourceEvent: $event, label: $event, summary: 'Reason '.$event, source: 'system', force: $force);
  }

  #[Test]
  #[DataProvider('owners')]
  public function unchanged_content_reuses_the_snapshot_but_preserves_each_capture_event(bool $shared): void
  {
    $owner = $this->owner($shared);
    $first = $this->capture($owner, 'first');
    $this->travel(3)->seconds();
    $second = $this->capture($owner, 'second');

    $this->assertSame($first->id, $second->id);
    $this->assertDatabaseCount($first->getTable(), 1);
    $events = $second->captureEvents()->get();
    $this->assertSame(['second', 'first'], $events->pluck('event')->all());
    $this->assertSame([false, true], $events->pluck('snapshot_created')->all());
    $this->assertSame('Reason second', $events->first()->reason);
    $this->assertSame('first', $second->event);
    $this->assertSame($first->snapshot['captured_at'], $second->snapshot['captured_at']);
    $this->assertSame((int) DB::table($first->getTable())->where('id', $first->id)->selectRaw('LENGTH(snapshot) as bytes')->value('bytes'), $second->snapshot_bytes);
    $history = app(RevisionCaptureHistory::class)->for($second);
    $this->assertSame('second', $history['events'][0]['event']);
    $this->assertFalse($history['has_more']);
  }

  #[Test]
  #[DataProvider('owners')]
  public function real_changes_and_explicit_safety_checkpoints_create_versions(bool $shared): void
  {
    $owner = $this->owner($shared);
    $first = $this->capture($owner);
    $owner->forceFill([$shared ? 'name' : 'title' => 'Changed'])->save();
    $changed = $this->capture($owner, 'changed');
    $safety = $this->capture($owner, 'safety', true);

    $this->assertNotSame($first->id, $changed->id);
    $this->assertNotSame($changed->id, $safety->id);
    $this->assertNotSame($first->snapshot_hash, $changed->snapshot_hash);
    $this->assertSame($changed->snapshot_hash, $safety->snapshot_hash);
    $this->assertDatabaseCount($first->getTable(), 3);
  }

  #[Test]
  #[DataProvider('owners')]
  public function legacy_snapshots_are_compared_without_capture_time_and_measured_lazily(bool $shared): void
  {
    $owner = $this->owner($shared);
    $first = $this->capture($owner);
    $first->forceFill(['snapshot_hash' => null, 'snapshot_bytes' => null])->saveQuietly();
    $this->travel(5)->seconds();
    $second = $this->capture($owner, 'again');

    $this->assertSame($first->id, $second->id);
    $this->assertNotNull($second->snapshot_hash);
    $this->assertGreaterThan(0, $second->snapshot_bytes);
    $this->assertDatabaseCount($first->getTable(), 1);
  }

  #[Test]
  #[DataProvider('owners')]
  public function failed_outer_transactions_leave_no_versions_or_capture_events(bool $shared): void
  {
    $owner = $this->owner($shared);
    try {
      DB::transaction(function () use ($owner): void {
        $this->capture($owner);
        throw new RuntimeException('Synthetic rollback');
      });
      $this->fail('The synthetic failure must escape the transaction.');
    } catch (RuntimeException $exception) {
      $this->assertSame('Synthetic rollback', $exception->getMessage());
    }
    $this->assertDatabaseCount($shared ? 'wbcms_shared_slot_revisions' : 'wbcms_page_revisions', 0);
    $this->assertDatabaseCount('wbcms_revision_capture_events', 0);
  }

  #[Test]
  public function fingerprint_ignores_capture_time_and_object_key_order_but_preserves_content_and_list_order(): void
  {
    $one = ['captured_at' => 'first', 'schema_version' => 1, 'blocks' => [['title' => 'One', 'settings' => ['b' => 2, 'a' => 1]], ['title' => 'Two']]];
    $two = ['blocks' => [['settings' => ['a' => 1, 'b' => 2], 'title' => 'One'], ['title' => 'Two']], 'schema_version' => 1, 'captured_at' => 'second'];
    $this->assertSame(SnapshotContent::hash($one), SnapshotContent::hash($two));
    $two['blocks'] = array_reverse($two['blocks']);
    $this->assertNotSame(SnapshotContent::hash($one), SnapshotContent::hash($two));
  }

  #[Test]
  public function update_migration_is_repeatable_and_preserves_historical_snapshots(): void
  {
    $owner = $this->owner(false);
    $revision = $this->capture($owner);
    $payload = $revision->snapshot;
    Schema::drop('wbcms_revision_capture_events');
    foreach (['wbcms_page_revisions', 'wbcms_shared_slot_revisions'] as $table) {
      Schema::table($table, function ($blueprint) use ($table): void {
        $blueprint->dropIndex($table.'_cleanup_order');
        $blueprint->dropIndex($table.'_latest_hash');
        $blueprint->dropIndex($table.'_site_sizes');
        $blueprint->dropIndex($table.'_missing_sizes');
        $blueprint->dropColumn(['snapshot_hash', 'snapshot_bytes']);
      });
    }
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_10_12_200000_ensure_revision_storage_metadata.php';
    $migration->up();
    $migration->up();

    $this->assertSame($payload, $revision->fresh()->snapshot);
    $this->assertGreaterThan(0, $revision->fresh()->snapshot_bytes);
    $this->assertNull($revision->fresh()->snapshot_hash);
    $this->assertTrue(Schema::hasIndex('wbcms_page_revisions', 'wbcms_page_revisions_cleanup_order'));
    $this->assertTrue(Schema::hasIndex('wbcms_shared_slot_revisions', 'wbcms_shared_slot_revisions_cleanup_order'));
    $this->assertDatabaseCount('wbcms_page_revisions', 1);
  }

  #[Test]
  public function capture_history_is_bounded_and_deleted_with_its_snapshot(): void
  {
    $revision = $this->capture($this->owner(false));
    $events = array_fill(0, 105, ['page_revision_id' => $revision->id, 'shared_slot_revision_id' => null, 'snapshot_created' => false]);
    RevisionCaptureEvent::query()->insert($events);
    $history = app(RevisionCaptureHistory::class)->for($revision);
    $this->assertCount(100, $history['events']);
    $this->assertTrue($history['has_more']);
    $revision->delete();
    $this->assertDatabaseCount('wbcms_revision_capture_events', 0);
  }
}
