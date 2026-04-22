<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use App\Support\Sites\SiteCloneOptions;
use App\Support\Sites\SiteCloneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SiteCloneServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_clones_site_content_translations_navigation_and_media_references_into_a_new_target_site(): void
    {
        [$sourceSite, $heroAsset] = $this->seedCloneableSite();

        $result = app(SiteCloneService::class)->clone(
            $sourceSite->id,
            'ui-docs',
            SiteCloneOptions::fromArray([
                'target_name' => 'UI Docs',
                'target_handle' => 'ui-docs',
                'target_domain' => 'ui.docs.webblocksui.com.ddev.site',
                'with_navigation' => true,
                'with_media' => true,
                'with_translations' => true,
            ]),
        );

        $targetSite = $result->targetSite;

        $this->assertNotNull($targetSite);
        $this->assertSame('ui-docs', $targetSite->handle);
        $this->assertSame('ui.docs.webblocksui.com.ddev.site', $targetSite->domain);
        $this->assertNotSame($sourceSite->id, $targetSite->id);
        $this->assertSame(2, $result->count('pages_cloned'));

        $aboutPage = Page::query()->where('site_id', $targetSite->id)->where('slug', 'about')->firstOrFail();
        $this->assertDatabaseHas('page_translations', [
            'page_id' => $aboutPage->id,
            'slug' => 'hakkinda',
        ]);

        $columns = Block::query()->where('page_id', $aboutPage->id)->where('type', 'columns')->firstOrFail();
        $columnItem = Block::query()->where('page_id', $aboutPage->id)->where('parent_id', $columns->id)->where('type', 'column_item')->firstOrFail();
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $columnItem->id,
            'title' => 'Hizli kurulum',
        ]);

        $imageBlock = Block::query()->where('page_id', $aboutPage->id)->where('type', 'image')->firstOrFail();
        $this->assertSame($heroAsset->id, $imageBlock->asset_id);
        $this->assertDatabaseHas('navigation_items', [
            'site_id' => $targetSite->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'title' => 'About',
            'page_id' => $aboutPage->id,
        ]);

        $sourceAbout = Page::query()->where('site_id', $sourceSite->id)->where('slug', 'about')->firstOrFail();
        $this->assertSame(1, Block::query()->where('page_id', $sourceAbout->id)->where('type', 'columns')->count());
        $this->assertSame(2, Page::query()->where('site_id', $sourceSite->id)->count());
    }

    #[Test]
    public function it_can_copy_media_files_and_duplicate_asset_records_when_requested(): void
    {
        Storage::fake('public');

        [$sourceSite, $heroAsset] = $this->seedCloneableSite(withFile: true);

        $result = app(SiteCloneService::class)->clone(
            $sourceSite->id,
            'ui-docs-files',
            SiteCloneOptions::fromArray([
                'target_name' => 'UI Docs Files',
                'target_handle' => 'ui-docs-files',
                'copy_media_files' => true,
                'with_navigation' => true,
                'with_media' => true,
                'with_translations' => true,
            ]),
        );

        $targetSite = $result->targetSite;
        $aboutPage = Page::query()->where('site_id', $targetSite->id)->where('slug', 'about')->firstOrFail();
        $imageBlock = Block::query()->where('page_id', $aboutPage->id)->where('type', 'image')->firstOrFail();
        $clonedAsset = Asset::query()->findOrFail($imageBlock->asset_id);

        $this->assertNotSame($heroAsset->id, $clonedAsset->id);
        $this->assertNotSame($heroAsset->path, $clonedAsset->path);
        Storage::disk('public')->assertExists($heroAsset->path);
        Storage::disk('public')->assertExists($clonedAsset->path);
        $this->assertGreaterThan(0, $result->count('files_copied'));
    }

    #[Test]
    public function it_refuses_to_overwrite_existing_target_content_without_explicit_overwrite_flag(): void
    {
        [$sourceSite] = $this->seedCloneableSite();
        $targetSite = Site::query()->create([
            'name' => 'Target',
            'handle' => 'target',
            'domain' => 'target.example.test',
            'is_primary' => false,
        ]);

        $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
        $targetSite->locales()->syncWithoutDetaching([$defaultLocale->id => ['is_enabled' => true]]);

        Page::query()->create([
            'site_id' => $targetSite->id,
            'title' => 'Existing',
            'slug' => 'existing',
            'status' => 'published',
        ]);

        $this->expectExceptionMessage('Target site already has content');

        app(SiteCloneService::class)->clone(
            $sourceSite->id,
            $targetSite->id,
            SiteCloneOptions::fromArray([]),
        );
    }

    #[Test]
    public function it_can_overwrite_target_content_safely(): void
    {
        [$sourceSite] = $this->seedCloneableSite();
        $targetSite = Site::query()->create([
            'name' => 'Target',
            'handle' => 'target',
            'domain' => 'target.example.test',
            'is_primary' => false,
        ]);

        $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
        $targetSite->locales()->syncWithoutDetaching([$defaultLocale->id => ['is_enabled' => true]]);

        $oldPage = Page::query()->create([
            'site_id' => $targetSite->id,
            'title' => 'Existing',
            'slug' => 'existing',
            'status' => 'published',
        ]);

        NavigationItem::query()->create([
            'site_id' => $targetSite->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'title' => 'Existing',
            'link_type' => NavigationItem::LINK_PAGE,
            'page_id' => $oldPage->id,
            'position' => 1,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
        ]);

        $result = app(SiteCloneService::class)->clone(
            $sourceSite->id,
            $targetSite->id,
            SiteCloneOptions::fromArray([
                'overwrite_target' => true,
            ]),
        );

        $this->assertSame(0, Page::query()->where('site_id', $targetSite->id)->where('slug', 'existing')->count());
        $this->assertSame(2, Page::query()->where('site_id', $targetSite->id)->count());
        $this->assertSame(2, $result->count('pages_cloned'));
    }

    #[Test]
    public function dry_run_returns_summary_without_writing(): void
    {
        [$sourceSite] = $this->seedCloneableSite();

        $result = app(SiteCloneService::class)->clone(
            $sourceSite->id,
            'ui-docs-preview',
            SiteCloneOptions::fromArray([
                'dry_run' => true,
                'target_handle' => 'ui-docs-preview',
                'target_name' => 'UI Docs Preview',
            ]),
        );

        $this->assertTrue($result->dryRun);
        $this->assertSame(0, Site::query()->where('handle', 'ui-docs-preview')->count());
        $this->assertSame(2, $result->count('pages_cloned'));
    }

    #[Test]
    public function it_can_skip_navigation_media_and_non_default_translations(): void
    {
        [$sourceSite] = $this->seedCloneableSite();

        $result = app(SiteCloneService::class)->clone(
            $sourceSite->id,
            'lean-clone',
            SiteCloneOptions::fromArray([
                'target_name' => 'Lean Clone',
                'target_handle' => 'lean-clone',
                'with_navigation' => false,
                'with_media' => false,
                'with_translations' => false,
            ]),
        );

        $targetSite = $result->targetSite;
        $aboutPage = Page::query()->where('site_id', $targetSite->id)->where('slug', 'about')->firstOrFail();

        $this->assertSame(0, NavigationItem::query()->where('site_id', $targetSite->id)->count());
        $this->assertSame(0, PageTranslation::query()->where('page_id', $aboutPage->id)->where('slug', 'hakkinda')->count());
        $this->assertSame(0, Block::query()->where('page_id', $aboutPage->id)->where('type', 'image')->whereNotNull('asset_id')->count());
    }

    private function seedCloneableSite(bool $withFile = false): array
    {
        if ($withFile) {
            Storage::fake('public');
        }

        $sourceSite = Site::query()->create([
            'name' => 'WebBlocks UI',
            'handle' => 'webblocks-ui',
            'domain' => 'webblocksui.com',
            'is_primary' => false,
        ]);

        $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $sourceSite->locales()->sync([
            $defaultLocale->id => ['is_enabled' => true],
            $turkish->id => ['is_enabled' => true],
        ]);

        $mainSlotType = SlotType::query()->firstOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );

        $columnsType = BlockType::query()->firstOrCreate(
            ['slug' => 'columns'],
            ['name' => 'Columns', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
        $columnItemType = BlockType::query()->firstOrCreate(
            ['slug' => 'column_item'],
            ['name' => 'Column Item', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 2, 'is_system' => true],
        );
        $imageType = BlockType::query()->firstOrCreate(
            ['slug' => 'image'],
            ['name' => 'Image', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 3, 'is_system' => false],
        );

        $homePage = Page::query()->create([
            'site_id' => $sourceSite->id,
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'published',
        ]);

        $aboutPage = Page::query()->create([
            'site_id' => $sourceSite->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        PageTranslation::query()->create([
            'page_id' => $aboutPage->id,
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);

        foreach ([$homePage, $aboutPage] as $index => $page) {
            PageSlot::query()->create([
                'page_id' => $page->id,
                'slot_type_id' => $mainSlotType->id,
                'sort_order' => $index,
            ]);
        }

        $heroAssetPath = 'media/images/hero.jpg';

        if ($withFile) {
            Storage::disk('public')->put($heroAssetPath, 'hero-image');
        }

        $heroAsset = Asset::query()->create([
            'disk' => 'public',
            'path' => $heroAssetPath,
            'filename' => 'hero.jpg',
            'original_name' => 'hero.jpg',
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'kind' => Asset::KIND_IMAGE,
            'visibility' => 'public',
            'title' => 'Hero',
        ]);

        $columns = Block::query()->create([
            'page_id' => $aboutPage->id,
            'type' => 'columns',
            'block_type_id' => $columnsType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 0,
            'title' => 'Starter features',
            'content' => 'English features',
            'status' => 'published',
            'is_system' => true,
        ]);

        $columnItem = Block::query()->create([
            'page_id' => $aboutPage->id,
            'parent_id' => $columns->id,
            'type' => 'column_item',
            'block_type_id' => $columnItemType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 0,
            'title' => 'Fast setup',
            'content' => 'English child content',
            'status' => 'published',
            'is_system' => false,
        ]);

        $columnItem->textTranslations()->create([
            'locale_id' => $defaultLocale->id,
            'title' => 'Fast setup',
            'content' => 'English child content',
        ]);
        $columnItem->textTranslations()->create([
            'locale_id' => $turkish->id,
            'title' => 'Hizli kurulum',
            'content' => 'Turkce alt icerik',
        ]);

        $imageBlock = Block::query()->create([
            'page_id' => $aboutPage->id,
            'type' => 'image',
            'block_type_id' => $imageType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 1,
            'title' => 'Hero image',
            'subtitle' => 'Hero alt',
            'asset_id' => $heroAsset->id,
            'status' => 'published',
            'is_system' => false,
        ]);

        $imageBlock->imageTranslations()->create([
            'locale_id' => $defaultLocale->id,
            'caption' => 'Hero image',
            'alt_text' => 'Hero alt',
        ]);
        $imageBlock->imageTranslations()->create([
            'locale_id' => $turkish->id,
            'caption' => 'Kahraman gorseli',
            'alt_text' => 'Kahraman alternatif',
        ]);

        NavigationItem::query()->create([
            'site_id' => $sourceSite->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'title' => 'About',
            'link_type' => NavigationItem::LINK_PAGE,
            'page_id' => $aboutPage->id,
            'position' => 1,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
        ]);

        return [$sourceSite, $heroAsset];
    }
}
