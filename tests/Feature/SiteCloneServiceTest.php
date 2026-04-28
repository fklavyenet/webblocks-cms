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
use App\Support\Pages\PublicPagePresenter;
use App\Support\Sites\SiteCloneOptions;
use App\Support\Sites\SiteCloneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsCloneableSite;
use Tests\TestCase;

class SiteCloneServiceTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCloneableSite;

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

        $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');
        $aboutPage = Page::query()
            ->where('site_id', $targetSite->id)
            ->whereHas('translations', fn ($query) => $query
                ->where('locale_id', $defaultLocaleId)
                ->where('slug', 'about'))
            ->firstOrFail();
        $this->assertDatabaseHas('page_translations', [
            'page_id' => $aboutPage->id,
            'slug' => 'hakkinda',
        ]);

        $header = Block::query()->where('page_id', $aboutPage->id)->where('type', 'header')->firstOrFail();
        $plainText = Block::query()->where('page_id', $aboutPage->id)->where('type', 'plain_text')->firstOrFail();
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $header->id,
            'title' => 'Hakkinda',
        ]);
        $this->assertNull($header->getRawOriginal('title'));
        $this->assertNull($plainText->getRawOriginal('content'));

        $imageBlock = Block::query()->where('page_id', $aboutPage->id)->where('type', 'image')->firstOrFail();
        $this->assertSame($heroAsset->id, $imageBlock->asset_id);
        $this->assertNull($imageBlock->getRawOriginal('title'));
        $this->assertNull($imageBlock->getRawOriginal('subtitle'));
        $presented = app(PublicPagePresenter::class)->present($aboutPage->fresh([
            'site',
            'translations',
            'slots.slotType',
            'blocks.blockType',
            'blocks.children.blockType',
            'blocks.children.textTranslations',
            'blocks.textTranslations',
            'blocks.imageTranslations',
            'blocks.blockAssets.asset',
        ]));
        $mainSlot = collect($presented['slots'])->firstWhere('slug', 'main');
        $presentedBlock = $mainSlot['blocks']->firstWhere('type', 'plain_text');
        $this->assertSame('English paragraph content', $presentedBlock->content);
        $this->assertDatabaseHas('navigation_items', [
            'site_id' => $targetSite->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'title' => 'About',
            'page_id' => $aboutPage->id,
        ]);

        $sourceAbout = Page::query()
            ->where('site_id', $sourceSite->id)
            ->whereHas('translations', fn ($query) => $query
                ->where('locale_id', $defaultLocaleId)
                ->where('slug', 'about'))
            ->firstOrFail();
        $this->assertSame(1, Block::query()->where('page_id', $sourceAbout->id)->where('type', 'header')->count());
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
        $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');
        $aboutPage = Page::query()
            ->where('site_id', $targetSite->id)
            ->whereHas('translations', fn ($query) => $query
                ->where('locale_id', $defaultLocaleId)
                ->where('slug', 'about'))
            ->firstOrFail();
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

        $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');
        $this->assertSame(0, Page::query()
            ->where('site_id', $targetSite->id)
            ->whereHas('translations', fn ($query) => $query
                ->where('locale_id', $defaultLocaleId)
                ->where('slug', 'existing'))
            ->count());
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
        $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');
        $aboutPage = Page::query()
            ->where('site_id', $targetSite->id)
            ->whereHas('translations', fn ($query) => $query
                ->where('locale_id', $defaultLocaleId)
                ->where('slug', 'about'))
            ->firstOrFail();

        $this->assertSame(0, NavigationItem::query()->where('site_id', $targetSite->id)->count());
        $this->assertSame(0, PageTranslation::query()->where('page_id', $aboutPage->id)->where('slug', 'hakkinda')->count());
        $this->assertSame(0, Block::query()->where('page_id', $aboutPage->id)->where('type', 'image')->whereNotNull('asset_id')->count());
    }
}
