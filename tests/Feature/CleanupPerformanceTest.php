<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevisionCandidate;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Media\MediaTransformService;
use WebBlocks\Cms\Support\Revisions\RevisionStorageSchema;
use WebBlocks\Cms\Support\System\MaintenanceCleanup;
use WebBlocks\Cms\Support\System\MaintenanceCleanupResult;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Tests\TestCase;

class CleanupPerformanceTest extends TestCase
{
  use RefreshDatabase;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function page(): Page
  {
    $site = Site::query()->create(['name' => 'Synthetic', 'handle' => 'synthetic', 'is_primary' => true]);
    app(SystemSettings::class)->save([SystemSettings::CLEANUP_PAGE_REVISION_DAYS => 90, SystemSettings::CLEANUP_KEEP_LATEST_PAGE_REVISIONS => 2]);

    return Page::query()->create(['site_id' => $site->id, 'title' => 'Synthetic', 'status' => Page::STATUS_DRAFT]);
  }

  #[Test]
  public function stored_size_previews_and_nullable_timestamps_cross_batch_boundaries_without_offsets(): void
  {
    $page = $this->page();
    $snapshot = json_encode(['content' => 'Synthetic']);
    $rows = [];
    foreach (range(1, 605) as $id) {
      $rows[] = ['id' => $id, 'page_id' => $page->id, 'site_id' => $page->site_id, 'snapshot' => $snapshot, 'created_at' => $id > 603 ? now()->subDays(120)->toDateTimeString() : null, 'updated_at' => null];
    }
    foreach (array_chunk($rows, 100) as $chunk) {
      DB::table('wbcms_page_revisions')->insert($chunk);
    }
    RevisionStorageSchema::ensure();
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
      $preview = app(MaintenanceCleanup::class)->previewPageRevisions();
      $overview = app(MaintenanceCleanup::class)->overview();
      $queries = DB::getQueryLog();
    } finally {
      DB::disableQueryLog();
      DB::flushQueryLog();
    }
    $this->assertSame(605, $overview['page_revisions']);
    $this->assertSame(605 * strlen($snapshot), $overview['page_revision_bytes']);
    $this->assertSame($overview['page_revision_bytes'], $overview['site_growth'][0]['page_revision_bytes']);
    $this->assertSame(603, $preview->candidateCount);
    $this->assertSame(603 * strlen($snapshot), $preview->candidateBytes);
    foreach ($queries as $query) {
      $this->assertStringNotContainsString('offset', $query['query']);
      $this->assertStringNotContainsString('LENGTH(snapshot)', $query['query']);
    }
    $result = app(MaintenanceCleanup::class)->run(MaintenanceCleanup::PAGE_REVISIONS);
    $this->assertSame(603, $result->deletedCount);
    $this->assertSame($preview->candidateBytes, $result->deletedBytes);
    $this->assertDatabaseCount('wbcms_page_revisions', 2);
    $this->assertDatabaseHas('wbcms_page_revisions', ['id' => 604]);
    $this->assertDatabaseHas('wbcms_page_revisions', ['id' => 605]);
  }

  #[Test]
  public function cleanup_rechecks_a_restore_source_protected_after_the_candidate_scan(): void
  {
    $page = $this->page();
    foreach (range(1, 5) as $id) {
      DB::table('wbcms_page_revisions')->insert(['id' => $id, 'page_id' => $page->id, 'site_id' => $page->site_id, 'snapshot' => '{}', 'snapshot_bytes' => 2, 'created_at' => now()->subDays(120)->toDateTimeString()]);
    }
    $injected = false;
    DB::listen(function ($query) use (&$injected, $page): void {
      if (! $injected && str_contains($query->sql, 'select "id" from "wbcms_pages"') && str_contains($query->sql, 'order by')) {
        $injected = true;
        PageRevisionCandidate::query()->create(['page_id' => $page->id, 'page_revision_id' => 1, 'status' => PageRevisionCandidate::STATUS_READY]);
      }
    });
    $result = app(MaintenanceCleanup::class)->run(MaintenanceCleanup::PAGE_REVISIONS);
    $this->assertTrue($injected);
    $this->assertSame(2, $result->deletedCount);
    $this->assertSame(4, $result->deletedBytes);
    $this->assertDatabaseHas('wbcms_page_revisions', ['id' => 1]);
    $this->assertDatabaseCount('wbcms_page_revisions', 3);
  }

  #[Test]
  public function site_growth_uses_grouped_totals_instead_of_queries_per_site(): void
  {
    $this->page();
    $cleanup = app(MaintenanceCleanup::class);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $first = $cleanup->overview();
    $oneSiteQueries = count(DB::getQueryLog());
    foreach (range(2, 8) as $id) {
      Site::query()->create(['name' => 'Synthetic '.$id, 'handle' => 'synthetic-'.$id]);
    }
    DB::flushQueryLog();
    $all = $cleanup->overview();
    $eightSiteQueries = count(DB::getQueryLog());
    DB::disableQueryLog();
    DB::flushQueryLog();
    $this->assertCount(1, $first['site_growth']);
    $this->assertCount(8, $all['site_growth']);
    $this->assertSame($oneSiteQueries, $eightSiteQueries);
    $this->assertSame(1, $all['site_growth'][0]['pages']);
    $this->assertSame(0, $all['site_growth'][7]['pages']);
  }

  #[Test]
  public function media_scans_preserve_originals_and_current_variants(): void
  {
    Storage::fake('public');
    $media = Media::query()->create(['disk' => 'public', 'path' => 'media/original.png', 'filename' => 'original.png', 'original_name' => 'original.png', 'kind' => Media::KIND_IMAGE, 'size' => 8, 'extension' => 'png', 'mime_type' => 'image/png']);
    $definition = array_values(config('media_transforms.variants'))[0];
    $active = substr(app(MediaTransformService::class)->fingerprint($media, $definition), 0, 16);
    $root = 'media/transforms/'.$media->id;
    Storage::disk('public')->put($media->path, 'original');
    Storage::disk('public')->put($root.'/'.$active.'/keep.png', 'keep');
    Storage::disk('public')->put($root.'/obsolete/one.png', 'old');
    Storage::disk('public')->put($root.'/obsolete/nested/two.png', 'oldtwo');
    $cleanup = app(MaintenanceCleanup::class);
    $preview = $cleanup->previewMediaVariants();
    $this->assertSame(1, $preview->candidateCount);
    $this->assertSame(9, $preview->candidateBytes);
    $result = $cleanup->run(MaintenanceCleanup::MEDIA_VARIANTS);
    $this->assertSame(1, $result->deletedCount);
    Storage::disk('public')->assertExists($media->path);
    Storage::disk('public')->assertExists($root.'/'.$active.'/keep.png');
    Storage::disk('public')->assertMissing($root.'/obsolete/one.png');
  }

  #[Test]
  public function cli_dry_run_previews_only_the_selected_category(): void
  {
    $cleanup = Mockery::mock(MaintenanceCleanup::class);
    $cleanup->shouldReceive('preview')->once()->with(MaintenanceCleanup::PAGE_REVISIONS)->andReturn(new MaintenanceCleanupResult(3, 42));
    $cleanup->shouldNotReceive('overview');
    $cleanup->shouldNotReceive('run');
    $this->app->instance(MaintenanceCleanup::class, $cleanup);
    $this->artisan('system:cleanup', ['category' => 'page-revisions', '--dry-run' => true])
      ->expectsOutput('candidates=3 bytes=42 deleted=0 freed=0 failures=0')->assertSuccessful();
  }
}
