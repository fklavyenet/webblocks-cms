<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\Site;
use App\Models\SiteExport;
use App\Support\Pages\PublicPagePresenter;
use App\Support\Sites\ExportImport\SiteExportManager;
use App\Support\Sites\ExportImport\SiteImportManager;
use App\Support\Sites\ExportImport\SiteImportOptions;
use Carbon\CarbonImmutable;
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
        Storage::fake('site-exports');
        Storage::fake('backups');
        [$site] = $this->seedCloneableSite();

        $siteExport = app(SiteExportManager::class)->export($site, false);

        $this->assertSame('completed', $siteExport->status);
        $this->assertNotNull($siteExport->archive_path);
        Storage::disk('site-exports')->assertExists($siteExport->archive_path);
        $this->assertSame('site-exports', $siteExport->archive_disk);
        $this->assertStringNotContainsString('/', (string) $siteExport->archive_path);
        Storage::disk('backups')->assertMissing($siteExport->archive_path);
    }

    #[Test]
    public function new_exports_use_flat_archive_paths(): void
    {
        Storage::fake('site-exports');
        [$site] = $this->seedCloneableSite();

        $siteExport = app(SiteExportManager::class)->export($site, false);

        $this->assertNotNull($siteExport->archive_path);
        $this->assertMatchesRegularExpression('#^webblocks-cms-site-export-[a-z0-9-]+-\d{4}-\d{2}-\d{2}-\d{6}\.zip$#', $siteExport->archive_path);
        $this->assertStringNotContainsString('/', $siteExport->archive_path);
        $this->assertStringNotContainsString('site-transfers', $siteExport->archive_path);
    }

    #[Test]
    public function export_filename_does_not_use_a_random_prefix(): void
    {
        Storage::fake('site-exports');
        [$site] = $this->seedCloneableSite();

        $siteExport = app(SiteExportManager::class)->export($site, false);

        $this->assertSame($siteExport->archive_name, $siteExport->archive_path);
        $this->assertStringStartsWith('webblocks-cms-site-export-', $siteExport->archive_path);
        $this->assertStringNotContainsString('/', $siteExport->archive_path);
        $this->assertMatchesRegularExpression('#^webblocks-cms-site-export-'.$site->handle.'-\d{4}-\d{2}-\d{2}-\d{6}\.zip$#', $siteExport->archive_path);
    }

    #[Test]
    public function same_day_exports_still_use_unique_flat_filenames(): void
    {
        Storage::fake('site-exports');
        [$site] = $this->seedCloneableSite();
        $firstTimestamp = CarbonImmutable::parse('2026-05-03 07:38:50');
        $secondTimestamp = $firstTimestamp->addSecond();

        $this->travelTo($firstTimestamp);
        $firstExport = app(SiteExportManager::class)->export($site, false);

        $this->travelTo($secondTimestamp);
        $secondExport = app(SiteExportManager::class)->export($site, false);

        $this->travelBack();

        $this->assertStringNotContainsString('/', (string) $firstExport->archive_path);
        $this->assertStringNotContainsString('/', (string) $secondExport->archive_path);
        $this->assertNotSame($firstExport->archive_path, $secondExport->archive_path);
    }

    #[Test]
    public function export_manifest_contains_expected_metadata(): void
    {
        Storage::fake('site-exports');
        [$site] = $this->seedCloneableSite();

        $siteExport = app(SiteExportManager::class)->export($site, false);
        $archive = new ZipArchive;
        $archive->open(Storage::disk('site-exports')->path($siteExport->archive_path));
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
        Storage::fake('site-exports');
        Storage::fake('public');
        [$site] = $this->seedCloneableSite(withFile: true);

        $siteExport = app(SiteExportManager::class)->export($site, false);
        $archive = new ZipArchive;
        $archive->open(Storage::disk('site-exports')->path($siteExport->archive_path));

        $this->assertFalse($archive->locateName('files/public/media/images/hero.jpg'));
        $archive->close();
    }

    #[Test]
    public function export_includes_media_files_when_selected(): void
    {
        Storage::fake('site-exports');
        Storage::fake('public');
        [$site] = $this->seedCloneableSite(withFile: true);

        $siteExport = app(SiteExportManager::class)->export($site, true);
        $archive = new ZipArchive;
        $archive->open(Storage::disk('site-exports')->path($siteExport->archive_path));

        $this->assertNotFalse($archive->locateName('files/public/media/images/hero.jpg'));
        $archive->close();
    }

    #[Test]
    public function can_import_package_into_a_new_site(): void
    {
        Storage::fake('site-exports');
        Storage::fake('site-transfers');
        Storage::fake('public');
        [$site] = $this->seedCloneableSite(withFile: true);
        $siteExport = app(SiteExportManager::class)->export($site, true);

        $siteImport = app(SiteImportManager::class)->inspectUpload(
            new UploadedFile(Storage::disk('site-exports')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true)
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
        Storage::fake('site-exports');
        Storage::fake('site-transfers');
        Storage::fake('public');
        [$site] = $this->seedCloneableSite(withFile: true);
        $siteExport = app(SiteExportManager::class)->export($site, true);

        $siteImport = app(SiteImportManager::class)->inspectUpload(
            new UploadedFile(Storage::disk('site-exports')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true)
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

        $header = Block::query()->where('page_id', $aboutPage->id)->where('type', 'header')->firstOrFail();
        $plainText = Block::query()->where('page_id', $aboutPage->id)->where('type', 'plain_text')->firstOrFail();
        $this->assertDatabaseHas('block_text_translations', ['block_id' => $header->id, 'title' => 'Hakkinda']);
        $this->assertNull($header->getRawOriginal('title'));
        $this->assertNull($plainText->getRawOriginal('content'));

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
        $presentedBlock = $mainSlot['blocks']->firstWhere('type', 'plain_text');
        $this->assertSame('English paragraph content', $presentedBlock->content);
        $this->assertSame('main', $mainSlot['wrapper']['element']);
        $this->assertSame('default', $mainSlot['wrapper']['preset']);
        Storage::disk('public')->assertExists($imageBlock->asset->path);
    }

    #[Test]
    public function handle_collision_is_resolved_safely(): void
    {
        Storage::fake('site-exports');
        Storage::fake('site-transfers');
        Storage::fake('public');
        [$site] = $this->seedCloneableSite(withFile: true);
        Site::query()->create(['name' => 'Imported UI Docs', 'handle' => 'marketing', 'domain' => null, 'is_primary' => false]);
        $siteExport = app(SiteExportManager::class)->export($site, true);

        $siteImport = app(SiteImportManager::class)->inspectUpload(
            new UploadedFile(Storage::disk('site-exports')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true)
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
        Storage::fake('site-exports');
        Storage::fake('site-transfers');
        Storage::fake('public');
        [$site] = $this->seedCloneableSite(withFile: true);
        Site::query()->create(['name' => 'Existing', 'handle' => 'existing', 'domain' => 'existing.example.test', 'is_primary' => false]);
        $siteExport = app(SiteExportManager::class)->export($site, true);

        $siteImport = app(SiteImportManager::class)->inspectUpload(
            new UploadedFile(Storage::disk('site-exports')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true)
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

    #[Test]
    public function import_strips_legacy_slot_wrapper_settings_from_page_slots(): void
    {
        Storage::fake('site-exports');
        Storage::fake('site-transfers');
        [$site] = $this->seedCloneableSite();
        $siteExport = app(SiteExportManager::class)->export($site, false);

        $archivePath = Storage::disk('site-exports')->path($siteExport->archive_path);
        $archive = new ZipArchive;
        $archive->open($archivePath);
        $pageSlots = json_decode((string) $archive->getFromName('data/page_slots.json'), true);
        $pageSlots[0]['settings'] = [
            'wrapper_element' => 'section',
            'wrapper_preset' => 'docs-main',
            'custom' => 'keep-me',
        ];
        $tempPath = Storage::disk('site-transfers')->path('site-export-slot-wrapper-cleanup.zip');
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
        $rewritten = new ZipArchive;
        $rewritten->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $entryName = $archive->getNameIndex($i);
            $contents = $archive->getFromIndex($i);
            if ($entryName === 'data/page_slots.json') {
                $contents = json_encode($pageSlots, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
            $rewritten->addFromString($entryName, (string) $contents);
        }
        $rewritten->close();
        $archive->close();

        $siteImport = app(SiteImportManager::class)->inspectUpload(
            new UploadedFile($tempPath, 'site-export-slot-wrapper-cleanup.zip', 'application/zip', null, true)
        );

        $siteImport = app(SiteImportManager::class)->import($siteImport, SiteImportOptions::fromArray([
            'site_name' => 'Imported UI Docs',
            'site_handle' => 'imported-ui-docs-clean',
        ]));

        $importedSite = Site::query()->findOrFail($siteImport->target_site_id);
        $importedSlot = PageSlot::query()
            ->whereHas('page', fn ($query) => $query->where('site_id', $importedSite->id))
            ->orderBy('id')
            ->firstOrFail();

        $this->assertSame(['custom' => 'keep-me'], $importedSlot->settings);

        @unlink($tempPath);
    }

    #[Test]
    public function export_delete_removes_the_exact_flat_archive_file(): void
    {
        Storage::fake('site-exports');
        Storage::fake('backups');
        [$site] = $this->seedCloneableSite();

        $archivePath = 'sl4r2si1-webblocks-cms-site-export-default-2026-05-03-130508.zip';
        Storage::disk('site-exports')->put($archivePath, 'flat export');

        $siteExport = SiteExport::query()->create([
            'site_id' => $site->id,
            'status' => SiteExport::STATUS_COMPLETED,
            'archive_disk' => 'site-exports',
            'archive_path' => $archivePath,
            'archive_name' => 'webblocks-cms-site-export-default-2026-05-03-130508.zip',
            'archive_size_bytes' => strlen('flat export'),
        ]);

        $response = app(SiteExportManager::class)->downloadResponse($siteExport);

        $this->assertSame(Storage::disk('site-exports')->path($archivePath), $response->getFile()->getPathname());

        app(SiteExportManager::class)->delete($siteExport->fresh());

        Storage::disk('site-exports')->assertMissing($archivePath);
        Storage::disk('backups')->assertMissing($archivePath);
        $this->assertDatabaseMissing('site_exports', ['id' => $siteExport->id]);
    }

    #[Test]
    public function site_import_uploads_remain_separate_from_backup_upload_storage(): void
    {
        Storage::fake('site-exports');
        Storage::fake('site-transfers');
        Storage::fake('backups');
        Storage::fake('public');
        [$site] = $this->seedCloneableSite(withFile: true);

        $siteExport = app(SiteExportManager::class)->export($site, true);

        $siteImport = app(SiteImportManager::class)->inspectUpload(
            new UploadedFile(Storage::disk('site-exports')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true)
        );

        $this->assertNotNull($siteImport->archive_path);
        $this->assertStringContainsString('/', $siteImport->archive_path);
        Storage::disk('site-transfers')->assertExists($siteImport->archive_path);
        $this->assertCount(0, Storage::disk('backups')->allFiles());
    }
}
