<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageRevisionCandidate;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Media\MediaTransformService;
use WebBlocks\Cms\Support\System\MaintenanceCleanup;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Tests\TestCase;

class MaintenanceCleanupTest extends TestCase
{
  use RefreshDatabase;

  private string $assetRoot;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->assetRoot = storage_path('app/cms/site-assets');
    File::deleteDirectory($this->assetRoot);
  }

  protected function tearDown(): void
  {
    File::deleteDirectory($this->assetRoot);
    parent::tearDown();
  }

  #[Test]
  public function cleanup_view_compiles(): void
  {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/system/cleanup.blade.php');
    $compiled = app('blade.compiler')->compileString($source);

    $this->assertStringContainsString('admin.system.cleanup.update', $compiled);
    $this->assertStringContainsString('admin.system.cleanup.run', $compiled);
  }

  #[Test]
  public function asset_cleanup_requires_both_age_and_minimum_revision_protection(): void
  {
    $directory = $this->assetRoot.'/1/revisions/css';
    File::ensureDirectoryExists($directory);

    foreach ([1, 2, 3, 4] as $index) {
      $path = $directory.'/2026010100000'.$index.'-'.str_repeat((string) $index, 64).'.css';
      File::put($path, str_repeat('x', $index));
      touch($path, now()->subDays(120)->addMinutes($index)->getTimestamp());
    }

    $settings = Mockery::mock(SystemSettings::class);
    $settings->shouldReceive('maintenanceCleanupSettings')->andReturn([
      'asset_revision_days' => 90,
      'keep_latest_asset_revisions' => 2,
      'temporary_workspace_hours' => 24,
    ]);
    $cleanup = new MaintenanceCleanup($settings, Mockery::mock(MediaTransformService::class));

    $preview = $cleanup->previewAssetRevisions();
    $this->assertSame(2, $preview->candidateCount);

    $result = $cleanup->run(MaintenanceCleanup::ASSET_REVISIONS);
    $this->assertSame(2, $result->deletedCount);
    $this->assertCount(2, File::files($directory));
  }

  #[Test]
  public function page_revision_cleanup_keeps_the_latest_revisions_and_active_restore_source(): void
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Page',
      'slug' => 'page',
      'page_type' => Page::TYPE_DEFAULT,
      'status' => Page::STATUS_PUBLISHED,
    ]);
    $revisions = collect(range(1, 4))->map(function (int $index) use ($page, $site): PageRevision {
      $revision = PageRevision::query()->create([
        'page_id' => $page->id,
        'site_id' => $site->id,
        'snapshot' => ['index' => $index, 'content' => str_repeat('x', 100)],
      ]);
      $revision->timestamps = false;
      $revision->forceFill([
        'created_at' => now()->subDays(120)->addMinutes($index),
        'updated_at' => now()->subDays(120)->addMinutes($index),
      ])->saveQuietly();

      return $revision->fresh();
    });
    PageRevisionCandidate::query()->create([
      'page_id' => $page->id,
      'page_revision_id' => $revisions->first()->id,
      'status' => PageRevisionCandidate::STATUS_READY,
    ]);

    $cleanup = new MaintenanceCleanup($this->cleanupSettings(), Mockery::mock(MediaTransformService::class));

    $preview = $cleanup->previewPageRevisions();
    $this->assertSame(1, $preview->candidateCount);
    $this->assertGreaterThan(0, $preview->candidateBytes);

    $result = $cleanup->run(MaintenanceCleanup::PAGE_REVISIONS);
    $this->assertSame(1, $result->deletedCount);
    $this->assertDatabaseCount('wbcms_page_revisions', 3);
    $this->assertDatabaseHas('wbcms_page_revisions', ['id' => $revisions->first()->id]);
  }

  private function cleanupSettings(): SystemSettings
  {
    $settings = Mockery::mock(SystemSettings::class);
    $settings->shouldReceive('maintenanceCleanupSettings')->andReturn([
      'asset_revision_days' => 90,
      'keep_latest_asset_revisions' => 2,
      'temporary_workspace_hours' => 24,
      'page_revision_days' => 90,
      'keep_latest_page_revisions' => 2,
      'shared_slot_revision_days' => 90,
      'keep_latest_shared_slot_revisions' => 2,
    ]);

    return $settings;
  }
}
