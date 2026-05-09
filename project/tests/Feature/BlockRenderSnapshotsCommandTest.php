<?php

namespace Project\Tests\Feature;

use App\Models\BlockType;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Site;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlockRenderSnapshotsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('project/block-render-snapshots'));

        parent::tearDown();
    }

    #[Test]
    public function snapshot_command_generates_review_files_without_persisting_temporary_render_context(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);

        $siteCount = Site::query()->count();
        $pageCount = Page::query()->count();
        $navigationCount = NavigationItem::query()->count();

        $this->artisan('project:block-render-snapshots')->assertExitCode(0);

        $snapshotRoot = storage_path('project/block-render-snapshots');
        $manifestPath = $snapshotRoot.'/manifest.json';
        $indexPath = $snapshotRoot.'/index.html';
        $headerSnapshotPath = $snapshotRoot.'/published-blocks/header.html';

        $this->assertFileExists($manifestPath);
        $this->assertFileExists($indexPath);
        $this->assertFileExists($headerSnapshotPath);

        $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(BlockType::query()->where('status', 'published')->count(), count($manifest['blocks'] ?? []));
        $this->assertStringContainsString('Current rendered HTML', File::get($headerSnapshotPath));
        $this->assertStringContainsString('data-wb-public-block-type="header"', File::get($headerSnapshotPath));

        $this->assertSame($siteCount, Site::query()->count());
        $this->assertSame($pageCount, Page::query()->count());
        $this->assertSame($navigationCount, NavigationItem::query()->count());
    }
}
