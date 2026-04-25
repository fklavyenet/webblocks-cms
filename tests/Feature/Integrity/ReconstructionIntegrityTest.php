<?php

namespace Tests\Feature\Integrity;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\Site;
use App\Models\SlotType;
use App\Models\User;
use App\Support\Blocks\BlockTranslationResolver;
use App\Support\Blocks\BlockTranslationWriter;
use App\Support\Pages\PageRevisionManager;
use App\Support\Sites\ExportImport\SiteExportManager;
use App\Support\Sites\ExportImport\SiteImportManager;
use App\Support\Sites\ExportImport\SiteImportOptions;
use App\Support\Sites\SiteCloneOptions;
use App\Support\Sites\SiteCloneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsCloneableSite;
use Tests\TestCase;

class ReconstructionIntegrityTest extends TestCase
{
    use BuildsCloneableSite;
    use RefreshDatabase;

    private function defaultSite(): Site
    {
        return Site::query()->where('is_primary', true)->firstOrFail();
    }

    private function defaultLocale(): Locale
    {
        return Locale::query()->where('is_default', true)->firstOrFail();
    }

    private function createLocale(string $code): Locale
    {
        return Locale::query()->create([
            'code' => $code,
            'name' => strtoupper($code),
            'is_default' => false,
            'is_enabled' => true,
        ]);
    }

    private function slotType(): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
    }

    private function sectionType(): BlockType
    {
        return BlockType::query()->updateOrCreate(
            ['slug' => 'section'],
            ['name' => 'Section', 'source_type' => 'static', 'status' => 'published'],
        );
    }

    #[Test]
    public function revision_restore_restores_translation_rows_and_does_not_depend_on_canonical_block_fields(): void
    {
        $site = $this->defaultSite();
        $turkish = $this->createLocale('tr');
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
        $user = User::factory()->siteAdmin()->create();
        $user->sites()->sync([$site->id]);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => Page::STATUS_PUBLISHED,
        ]);
        $page->translations()->create([
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
        ]);

        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $this->sectionType()->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
            'title' => 'Hero',
            'content' => 'Original content',
            'status' => 'published',
            'is_system' => false,
        ]);

        $block->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Hero',
            'content' => 'Original content',
        ]);
        $block->textTranslations()->create([
            'locale_id' => $turkish->id,
            'title' => 'Kahraman',
            'content' => 'Orijinal icerik',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $manager = app(PageRevisionManager::class);
        $revision = $manager->capture($page->fresh(), $user, 'Snapshot');

        $page->translations()->where('locale_id', $turkish->id)->update([
            'name' => 'Degisti',
            'slug' => 'degisti',
            'path' => '/p/degisti',
        ]);
        $block->textTranslations()->where('locale_id', $this->defaultLocale()->id)->update([
            'title' => 'Changed hero',
            'content' => 'Changed content',
        ]);

        $manager->restore($page->fresh(), $revision, $user);

        $restoredPage = $page->fresh(['translations', 'blocks.textTranslations']);
        $restoredBlock = $restoredPage->blocks->firstOrFail();
        $resolvedBlock = app(BlockTranslationResolver::class)->resolve($restoredBlock, 'en');

        $this->assertSame('Hakkinda', $restoredPage->translations->firstWhere('locale_id', $turkish->id)?->name);
        $this->assertNull($restoredBlock->getRawOriginal('title'));
        $this->assertNull($restoredBlock->getRawOriginal('content'));
        $this->assertSame('Hero', $resolvedBlock->title);
        $this->assertSame('Original content', $resolvedBlock->content);
    }

    #[Test]
    public function site_clone_copies_page_and_block_translations_and_enabled_locales_without_using_legacy_columns(): void
    {
        [$sourceSite] = $this->seedCloneableSite();

        $result = app(SiteCloneService::class)->clone(
            $sourceSite->id,
            'cloned-site',
            SiteCloneOptions::fromArray([
                'target_name' => 'Cloned Site',
                'target_handle' => 'cloned-site',
                'with_navigation' => true,
                'with_media' => true,
                'with_translations' => true,
            ]),
        );

        $targetSite = $result->targetSite;
        $aboutPage = Page::query()
            ->where('site_id', $targetSite->id)
            ->whereHas('translations', fn ($query) => $query->where('slug', 'about'))
            ->firstOrFail();
        $columnItem = Block::query()->where('page_id', $aboutPage->id)->where('type', 'column_item')->firstOrFail();

        $this->assertDatabaseHas('page_translations', ['page_id' => $aboutPage->id, 'slug' => 'hakkinda']);
        $this->assertDatabaseHas('block_text_translations', ['block_id' => $columnItem->id, 'title' => 'Hizli kurulum']);
        $this->assertNull($columnItem->getRawOriginal('title'));
        $this->assertNull($columnItem->getRawOriginal('content'));
        $this->assertSame(['en', 'tr'], $targetSite->fresh()->enabledLocales()->orderBy('code')->pluck('code')->all());
    }

    #[Test]
    public function export_and_import_preserve_translations_locale_assignments_and_public_rendering(): void
    {
        Storage::fake('site-transfers');
        Storage::fake('public');
        [$sourceSite] = $this->seedCloneableSite(withFile: true);
        $export = app(SiteExportManager::class)->export($sourceSite, true);

        $import = app(SiteImportManager::class)->inspectUpload(
            new UploadedFile(Storage::disk('site-transfers')->path($export->archive_path), $export->archive_name, 'application/zip', null, true)
        );

        $import = app(SiteImportManager::class)->import($import, SiteImportOptions::fromArray([
            'site_name' => 'Imported Site',
            'site_handle' => 'imported-site',
            'site_domain' => 'imported.example.test',
        ]));

        $site = Site::query()->findOrFail($import->target_site_id);
        $aboutPage = Page::query()
            ->where('site_id', $site->id)
            ->whereHas('translations', fn ($query) => $query->where('slug', 'about'))
            ->firstOrFail();

        $this->assertDatabaseHas('page_translations', ['page_id' => $aboutPage->id, 'slug' => 'hakkinda']);
        $this->assertSame(['en', 'tr'], $site->enabledLocales()->orderBy('code')->pluck('code')->all());
        $this->get('http://imported.example.test/p/about')->assertOk()->assertSee('Fast setup');
        $this->get('http://imported.example.test/tr/p/hakkinda')->assertOk()->assertSee('Hizli kurulum');
    }
}
