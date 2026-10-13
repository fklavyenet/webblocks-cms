<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageRevisionCandidate;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\SharedSlotRevision;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\System\MaintenanceCleanup;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Tests\TestCase;

class MaintenanceCleanupRevisionQueryTest extends TestCase
{
  use RefreshDatabase;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadRelationalBridgeFixtureMigrations();
  }

  public static function revisionCategories(): array
  {
    return [
      'pages' => [PageRevision::class, Page::class, 'page_id', MaintenanceCleanup::PAGE_REVISIONS],
      'shared slots' => [SharedSlotRevision::class, SharedSlot::class, 'shared_slot_id', MaintenanceCleanup::SHARED_SLOT_REVISIONS],
    ];
  }

  #[Test]
  #[DataProvider('revisionCategories')]
  public function revision_previews_sort_only_metadata_and_measure_candidates_in_bounded_unsorted_queries(string $revisionModel, string $ownerModel, string $ownerKey, string $category): void
  {
    $site = Site::query()->create(['name' => 'Synthetic', 'handle' => 'synthetic', 'is_primary' => true]);
    $owner = $ownerModel::query()->create(['site_id' => $site->id, 'name' => 'Content', 'handle' => 'content', 'title' => 'Content', 'status' => Page::STATUS_PUBLISHED]);
    $table = (new $revisionModel)->getTable();
    $oldDate = now()->subDays(120)->toDateTimeString();
    $rows = [];
    foreach (range(1, 1004) as $id) {
      $rows[] = [
        'id' => $id,
        $ownerKey => $owner->id,
        'site_id' => $site->id,
        'snapshot' => json_encode(['content' => str_repeat('x', $id === 500 ? 3 * 1024 * 1024 : 512)], JSON_THROW_ON_ERROR),
        'created_at' => $oldDate,
        'updated_at' => $oldDate,
      ];
    }
    foreach (array_chunk($rows, 100) as $chunk) {
      DB::table($table)->insert($chunk);
    }
    if ($category === MaintenanceCleanup::PAGE_REVISIONS) {
      PageRevisionCandidate::query()->create(['page_id' => $owner->id, 'page_revision_id' => 1, 'status' => PageRevisionCandidate::STATUS_READY]);
    }
    $otherOwner = $ownerModel::query()->create(['site_id' => $site->id, 'name' => 'Other', 'handle' => 'other', 'title' => 'Other', 'status' => Page::STATUS_PUBLISHED]);
    foreach (range(1, 3) as $index) {
      $revisionModel::query()->create(['site_id' => $site->id, $ownerKey => $otherOwner->id, 'snapshot' => ['recent' => $index]]);
    }
    app(SystemSettings::class)->save([
      SystemSettings::CLEANUP_PAGE_REVISION_DAYS => 90,
      SystemSettings::CLEANUP_KEEP_LATEST_PAGE_REVISIONS => 2,
      SystemSettings::CLEANUP_SHARED_SLOT_REVISION_DAYS => 90,
      SystemSettings::CLEANUP_KEEP_LATEST_SHARED_SLOT_REVISIONS => 2,
    ]);
    $candidateIds = range($category === MaintenanceCleanup::PAGE_REVISIONS ? 2 : 1, 1002);
    $expectedBytes = (int) DB::table($table)->whereIn('id', $candidateIds)->selectRaw('SUM(LENGTH(snapshot)) as bytes')->value('bytes');
    $cleanup = app(MaintenanceCleanup::class);

    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
      $preview = $category === MaintenanceCleanup::PAGE_REVISIONS ? $cleanup->previewPageRevisions() : $cleanup->previewSharedSlotRevisions();
      $queries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'from "'.$table.'"'));
    } finally {
      DB::disableQueryLog();
      DB::flushQueryLog();
    }

    $this->assertSame(count($candidateIds), $preview->candidateCount);
    $this->assertSame($expectedBytes, $preview->candidateBytes);
    $orderedQueries = $queries->filter(fn (array $query) => str_contains($query['query'], 'order by'));
    $this->assertGreaterThan(1, $orderedQueries->count(), 'Exercise the metadata pagination boundary.');
    foreach ($orderedQueries as $query) {
      $this->assertDoesNotMatchRegularExpression('/(?:["`]snapshot["`]|LENGTH\(snapshot\))/', $query['query'], 'Large JSON snapshots must stay outside MySQL filesort rows.');
      $this->assertStringNotContainsString('offset', $query['query']);
    }
    $sizeQueries = $queries->filter(fn (array $query) => str_contains($query['query'], 'LENGTH(snapshot)'));
    $this->assertCount(3, $sizeQueries);
    $measuredIds = [];
    foreach ($sizeQueries as $query) {
      $this->assertStringNotContainsString('order by', $query['query']);
      $this->assertLessThanOrEqual(500, count($query['bindings']));
      array_push($measuredIds, ...$query['bindings']);
    }
    $this->assertEqualsCanonicalizing($candidateIds, $measuredIds);
    $this->assertDatabaseCount($table, 1007);

    $result = $cleanup->run($category);
    $this->assertSame(count($candidateIds), $result->deletedCount);
    $this->assertSame($expectedBytes, $result->deletedBytes);
    $this->assertDatabaseCount($table, 1007 - count($candidateIds));
    foreach ([1003, 1004, 1005, 1006, 1007] as $id) {
      $this->assertDatabaseHas($table, ['id' => $id]);
    }
    if ($category === MaintenanceCleanup::PAGE_REVISIONS) {
      $this->assertDatabaseHas($table, ['id' => 1]);
    }
  }
}
