<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\File;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Support\Media\MediaTransformService;
use WebBlocks\Cms\Support\System\MaintenanceCleanup;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Tests\TestCase;

class MaintenanceCleanupTest extends TestCase
{
  private string $assetRoot;

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
}
