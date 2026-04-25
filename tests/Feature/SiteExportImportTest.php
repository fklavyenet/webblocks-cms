<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Locale;
use App\Models\Page;
use App\Models\Site;
use App\Support\Pages\PublicPagePresenter;
use App\Support\Sites\ExportImport\SiteExportManager;
use App\Support\Sites\ExportImport\SiteImportManager;
use App\Support\Sites\ExportImport\SiteImportOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsCloneableSite;
use Tests\TestCase;
use ZipArchive;

class SiteExportImportTest extends TestCase
{
    use BuildsCloneableSite;
    use RefreshDatabase;

    #[Test]
    public function can_export_a_site_package_successfully(): void
    {
        Storage::fake('site-transfers');
        [$site] = $this->seedCloneableSite();

        $siteExport = app(SiteExportManager::class)->export($site, false);

        $this->assertSame('completed', $siteExport->status);
        $this->assertNotNull($siteExport->archive_path);
        Storage::disk('site-transfers')->assertExists($siteExport->archive_path);
    }

    #[Test]
    public function export_manifest_contains_expected_metadata(): void
    {
        Storage::fake('site-transfers');
        [$site] = $this->seedCloneableSite();

        $siteExport = app(SiteExportManager::class)->export($site, false);
        $archive = new ZipArchive;
        $archive->open(Storage::disk('site-transfers')->path($siteExport->archive_path));
        $manifest = json_decode((string) $archive->getFromName('manifest.json'), true);
        $archive->close();

        $this->assertSame('WebBlocks CMS', $manifest['product']);
        $this->assertSame(1, $manifest['format_version']);
        $this->assertSame($site->handle, $manifest['source_site_handle']);
        $this->assertFalse($manifest['includes_media']);
    }

    #[Test]
    public function export_excludes_media_files_when_media_not_selected(): void
    {
        Storage::fake('site-transfers');
        Storage::fake('public');
        [$site] = $this->seedCloneableSite(withFile: true);

        $siteExport = app(SiteExportManager::class)->export($site, false);
        $archive = new ZipArchive;
        $archive->open(Storage::disk('site-transfers')->path($siteExport->archive_path));

        $this->assertFalse($archive->locateName('files/public/media/images/hero.jpg'));
        $archive->close();
    }

    #[Test]
    public function export_includes_media_files_when_selected(): void
    {
        Storage::fake('site-transfers');
        Storage::fake('public');
        [$site] = $this->seedCloneableSite(withFile: true);

        $siteExport = app(SiteExportManager::class)->export($site, true);
        $archive = new ZipArchive;
        $archive->open(Storage::disk('site-transfers')->path($siteExport->archive_path));

        $this->assertNotFalse($archive->locateName('files/public/media/images/hero.jpg'));
        $archive->close();
    }

    #[Test]
    public function can_import_package_into_a_new_site(): void
    {
        Storage::fake('site-transfers');
        Storage::fake('public');
        [$site] = $this->seedCloneableSite(withFile: true);
        $siteExport = app(SiteExportManager::class)->export($site, true);

        $siteImport = app(SiteImportManager::class)->inspectUpload(
            new UploadedFile(Storage::disk('site-transfers')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true)
        );

        $siteImport = app(SiteImportManager::class)->import($siteImport, SiteImportOptions::fromArray([
            'site_name' => 'Imported UI Docs',
            'site_handle' => 'imported-ui-docs',
            'site_domain' => null,
        ]));

        $this->assertSame('completed', $siteImport->status);
        $this->assertDatabaseHas('sites', ['id' => $siteImport->target_site_id, 'handle' => 'imported-ui-docs']);
    }

    #[Test]
    public function imported_pages_belong_to_new_site_and_translations_and_blocks_are_preserved(): void
    {
        Storage::fake('site-transfers');
        Storage::fake('public');
        [$site] = $this->seedCloneableSite(withFile: true);
        $siteExport = app(SiteExportManager::class)->export($site, true);

        $siteImport = app(SiteImportManager::class)->inspectUpload(
            new UploadedFile(Storage::disk('site-transfers')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true)
        );

        $siteImport = app(SiteImportManager::class)->import($siteImport, SiteImportOptions::fromArray([
            'site_name' => 'Imported UI Docs',
            'site_handle' => 'imported-ui-docs',
        ]));

        $importedSite = Site::query()->findOrFail($siteImport->target_site_id);
        $aboutPage = Page::query()
            ->where('site_id', $importedSite->id)
            ->whereHas('translations', fn ($query) => $query
                ->where('locale_id', Locale::query()->where('is_default', true)->value('id'))
                ->where('slug', 'about'))
            ->firstOrFail();

        $this->assertDatabaseHas('page_translations', ['page_id' => $aboutPage->id, 'slug' => 'hakkinda']);

        $columnItem = Block::query()->where('page_id', $aboutPage->id)->where('type', 'column_item')->firstOrFail();
        $this->assertDatabaseHas('block_text_translations', ['block_id' => $columnItem->id, 'title' => 'Hizli kurulum']);
        $this->assertNull($columnItem->getRawOriginal('title'));
        $this->assertNull($columnItem->getRawOriginal('content'));

        $imageBlock = Block::query()->where('page_id', $aboutPage->id)->where('type', 'image')->firstOrFail();
        $this->assertNotNull($imageBlock->asset_id);
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
        $columnsBlock = $mainSlot['blocks']->firstWhere('type', 'columns');
        $presentedChild = $columnsBlock->children->firstWhere('type', 'column_item');
        $this->assertSame('Fast setup', $presentedChild->title);
        $this->assertSame('English child content', $presentedChild->content);
        Storage::disk('public')->assertExists($imageBlock->asset->path);
    }

    #[Test]
    public function handle_collision_is_resolved_safely(): void
    {
        Storage::fake('site-transfers');
        Storage::fake('public');
        [$site] = $this->seedCloneableSite(withFile: true);
        Site::query()->create(['name' => 'Imported UI Docs', 'handle' => 'marketing', 'domain' => null, 'is_primary' => false]);
        $siteExport = app(SiteExportManager::class)->export($site, true);

        $siteImport = app(SiteImportManager::class)->inspectUpload(
            new UploadedFile(Storage::disk('site-transfers')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true)
        );

        $siteImport = app(SiteImportManager::class)->import($siteImport, SiteImportOptions::fromArray([
            'site_name' => 'Marketing Imported',
            'site_handle' => 'marketing',
        ]));

        $this->assertNotSame('marketing', $siteImport->targetSite->handle);
        $this->assertStringStartsWith('marketing-imported', $siteImport->targetSite->handle);
    }

    #[Test]
    public function domain_collision_does_not_overwrite_existing_site_domain(): void
    {
        Storage::fake('site-transfers');
        Storage::fake('public');
        [$site] = $this->seedCloneableSite(withFile: true);
        Site::query()->create(['name' => 'Existing', 'handle' => 'existing', 'domain' => 'existing.example.test', 'is_primary' => false]);
        $siteExport = app(SiteExportManager::class)->export($site, true);

        $siteImport = app(SiteImportManager::class)->inspectUpload(
            new UploadedFile(Storage::disk('site-transfers')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true)
        );

        $this->expectExceptionMessage('Selected site domain already exists locally');

        app(SiteImportManager::class)->import($siteImport, SiteImportOptions::fromArray([
            'site_name' => 'Imported',
            'site_handle' => 'imported-ui-docs',
            'site_domain' => 'existing.example.test',
        ]));
    }

    #[Test]
    public function invalid_manifest_fails_clearly(): void
    {
        Storage::fake('site-transfers');
        $path = Storage::disk('site-transfers')->path('broken.zip');
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $archive->addFromString('manifest.json', json_encode(['product' => 'Wrong Product'], JSON_PRETTY_PRINT));

        foreach ([
            'data/site.json',
            'data/locales.json',
            'data/site_locales.json',
            'data/pages.json',
            'data/page_translations.json',
            'data/page_slots.json',
            'data/blocks.json',
            'data/block_assets.json',
            'data/block_text_translations.json',
            'data/block_button_translations.json',
            'data/block_image_translations.json',
            'data/block_contact_form_translations.json',
            'data/navigation_items.json',
            'data/asset_folders.json',
            'data/assets.json',
        ] as $file) {
            $archive->addFromString($file, json_encode([], JSON_PRETTY_PRINT));
        }

        $archive->close();

        $this->expectExceptionMessage('Import package product is not supported');

        app(SiteImportManager::class)->inspectUpload(new UploadedFile($path, 'broken.zip', 'application/zip', null, true));
    }

    #[Test]
    public function dangerous_archive_paths_are_rejected(): void
    {
        Storage::fake('site-transfers');
        $path = Storage::disk('site-transfers')->path('dangerous.zip');
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $archive->addFromString('../manifest.json', '{}');
        $archive->close();

        $this->expectExceptionMessage('Archive entry path is invalid');

        app(SiteImportManager::class)->inspectUpload(new UploadedFile($path, 'dangerous.zip', 'application/zip', null, true));
    }
}
