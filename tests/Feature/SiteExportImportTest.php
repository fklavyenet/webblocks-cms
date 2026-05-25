<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsCloneableSite;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageAsset;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteExport;
use WebBlocks\Cms\Models\SiteImport;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteExportManager;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteImportManager;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteImportOptions;
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
    [$site] = $this->seedCloneableSite(withFile: true);

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
  public function export_includes_shared_slot_metadata_and_handle_based_page_slot_references_without_hidden_source_pages(): void
  {
    Storage::fake('site-exports');
    [$site, , $sharedSlot] = $this->seedCloneableSite();

    $siteExport = app(SiteExportManager::class)->export($site, false);
    $archive = new ZipArchive;
    $archive->open(Storage::disk('site-exports')->path($siteExport->archive_path));
    $pages = json_decode((string) $archive->getFromName('data/pages.json'), true);
    $pageSlots = json_decode((string) $archive->getFromName('data/page_slots.json'), true);
    $sharedSlots = json_decode((string) $archive->getFromName('data/shared_slots.json'), true);
    $archive->close();

    $this->assertCount(1, $sharedSlots);
    $this->assertSame($sharedSlot->handle, $sharedSlots[0]['handle']);
    $this->assertSame('header', $sharedSlots[0]['slot_name']);
    $this->assertTrue($sharedSlots[0]['is_active']);
    $this->assertCount(2, $pages);
    $this->assertFalse(collect($pages)->contains(fn (array $page) => $page['page_type'] === Page::TYPE_SHARED_SLOT_SOURCE));

    $sharedPageSlot = collect($pageSlots)->first(fn (array $slot) => ($slot['shared_slot_handle'] ?? null) === $sharedSlot->handle);
    $disabledSlot = collect($pageSlots)->first(fn (array $slot) => ($slot['source_type'] ?? null) === PageSlot::SOURCE_TYPE_DISABLED);

    $this->assertNotNull($sharedPageSlot);
    $this->assertSame(PageSlot::SOURCE_TYPE_SHARED_SLOT, $sharedPageSlot['source_type']);
    $this->assertSame($sharedSlot->handle, $sharedPageSlot['shared_slot_handle']);
    $this->assertNotNull($disabledSlot);
    $this->assertNull($disabledSlot['shared_slot_handle']);
  }

  #[Test]
  public function export_includes_page_public_shell_in_portable_page_payload(): void
  {
    Storage::fake('site-exports');
    [$site] = $this->seedCloneableSite();
    $aboutPage = Page::query()->where('site_id', $site->id)->whereHas('translations', fn ($query) => $query->where('slug', 'about'))->firstOrFail();
    $aboutPage->update(['settings' => ['public_shell' => 'docs']]);

    $siteExport = app(SiteExportManager::class)->export($site, false);
    $archive = new ZipArchive;
    $archive->open(Storage::disk('site-exports')->path($siteExport->archive_path));
    $pages = json_decode((string) $archive->getFromName('data/pages.json'), true);
    $archive->close();

    $exportedPage = collect($pages)->firstWhere('slug', 'about');

    $this->assertSame('docs', $exportedPage['public_shell'] ?? null);
    $this->assertSame('docs', data_get($exportedPage, 'settings.public_shell'));
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
  public function export_includes_site_level_public_override_assets_when_files_are_selected(): void
  {
    Storage::fake('site-transfers');
    [$site] = $this->seedCloneableSite();

    $this->putTrackedPublicSiteFile('site/webblocks-ui/css/site.css', 'body { color: teal; }');
    $this->putTrackedPublicSiteFile('site/webblocks-ui/js/site.js', 'window.webblocksSiteLoaded = true;');

    $siteExport = app(SiteExportManager::class)->export($site, true);
    $archive = new ZipArchive;
    $archive->open(Storage::disk($siteExport->archive_disk)->path($siteExport->archive_path));
    $sitePublicAssets = json_decode((string) $archive->getFromName('data/site_public_assets.json'), true);

    $this->assertCount(2, $sitePublicAssets);
    $this->assertNotFalse($archive->locateName('files/public/site/webblocks-ui/css/site.css'));
    $this->assertNotFalse($archive->locateName('files/public/site/webblocks-ui/js/site.js'));
    $this->assertSame(2, $siteExport->summary_json['site_public_assets'] ?? null);

    $archive->close();
  }

  #[Test]
  public function export_skips_missing_site_level_public_override_assets_cleanly(): void
  {
    Storage::fake('site-transfers');
    [$site] = $this->seedCloneableSite();

    $this->putTrackedPublicSiteFile('site/webblocks-ui/css/site.css', 'body { color: teal; }');

    $siteExport = app(SiteExportManager::class)->export($site, true);
    $archive = new ZipArchive;
    $archive->open(Storage::disk($siteExport->archive_disk)->path($siteExport->archive_path));
    $sitePublicAssets = json_decode((string) $archive->getFromName('data/site_public_assets.json'), true);

    $this->assertCount(1, $sitePublicAssets);
    $this->assertSame('/site/webblocks-ui/css/site.css', $sitePublicAssets[0]['path']);
    $this->assertNotFalse($archive->locateName('files/public/site/webblocks-ui/css/site.css'));
    $this->assertFalse($archive->locateName('files/public/site/webblocks-ui/js/site.js'));

    $archive->close();
  }

  #[Test]
  public function import_restores_site_level_public_override_assets_under_target_site_handle(): void
  {
    Storage::fake('site-transfers');
    [$site] = $this->seedCloneableSite();

    $this->putTrackedPublicSiteFile('site/webblocks-ui/css/site.css', 'body { color: teal; }');
    $this->putTrackedPublicSiteFile('site/webblocks-ui/js/site.js', 'window.webblocksSiteLoaded = true;');

    $siteExport = app(SiteExportManager::class)->export($site, true);
    $siteImport = app(SiteImportManager::class)->inspectUpload(
      new UploadedFile(Storage::disk($siteExport->archive_disk)->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true)
    );

    $siteImport = app(SiteImportManager::class)->import($siteImport, SiteImportOptions::fromArray([
      'site_name' => 'Imported With Site Assets',
      'site_handle' => 'imported-site-assets',
      'site_domain' => 'imported-site-assets.example.test',
    ]));

    $importedCssPath = public_path('site/imported-site-assets/css/site.css');
    $importedJsPath = public_path('site/imported-site-assets/js/site.js');

    $this->assertFileExists($importedCssPath);
    $this->assertFileExists($importedJsPath);
    $this->assertSame('body { color: teal; }', (string) file_get_contents($importedCssPath));
    $this->assertSame('window.webblocksSiteLoaded = true;', (string) file_get_contents($importedJsPath));

    $response = $this->get('http://imported-site-assets.example.test/p/about');

    $response->assertOk();
    $response->assertSee('/site/imported-site-assets/css/site.css', false);
    $response->assertSee('/site/imported-site-assets/js/site.js', false);
    $response->assertDontSee('/site/webblocks-ui/css/site.css', false);
    $response->assertDontSee('/site/webblocks-ui/js/site.js', false);

    @unlink($importedCssPath);
    @unlink($importedJsPath);
    @rmdir(public_path('site/imported-site-assets/css'));
    @rmdir(public_path('site/imported-site-assets/js'));
    @rmdir(public_path('site/imported-site-assets'));
  }

  #[Test]
  public function import_ignores_non_canonical_site_public_asset_payload_rows(): void
  {
    Storage::fake('site-transfers');
    [$site] = $this->seedCloneableSite();

    $siteExport = app(SiteExportManager::class)->export($site, true);
    $archivePath = Storage::disk($siteExport->archive_disk)->path($siteExport->archive_path);
    $archive = new ZipArchive;
    $archive->open($archivePath);

    $rewrittenPath = Storage::disk('site-transfers')->path('site-public-asset-invalid-row.zip');
    $rewritten = new ZipArchive;
    $rewritten->open($rewrittenPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    for ($index = 0; $index < $archive->numFiles; $index++) {
      $entryName = $archive->getNameIndex($index);
      $contents = $archive->getFromIndex($index);

      if ($entryName === 'data/site_public_assets.json') {
        $contents = json_encode([[
          'type' => 'css',
          'path' => '/site/webblocks-ui/pages/about/page.css',
          'relative_path' => 'site/webblocks-ui/pages/about/page.css',
        ]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      }

      $rewritten->addFromString((string) $entryName, (string) $contents);
    }

    $rewritten->addFromString('files/public/site/webblocks-ui/pages/about/page.css', 'body { color: red; }');
    $rewritten->close();
    $archive->close();

    $siteImport = app(SiteImportManager::class)->inspectUpload(
      new UploadedFile($rewrittenPath, 'site-public-asset-invalid-row.zip', 'application/zip', null, true)
    );

    app(SiteImportManager::class)->import($siteImport, SiteImportOptions::fromArray([
      'site_name' => 'Imported Invalid Site Asset Row',
      'site_handle' => 'invalid-site-asset-row',
    ]));

    $this->assertFileDoesNotExist(public_path('site/invalid-site-asset-row/pages/about/page.css'));
    $this->assertFileDoesNotExist(public_path('site/invalid-site-asset-row/css/site.css'));
  }

  #[Test]
  public function export_and_import_include_page_asset_rows_and_optional_public_site_files(): void
  {
    Storage::fake('site-exports');
    Storage::fake('site-transfers');
    [$site] = $this->seedCloneableSite();
    $page = Page::query()->where('site_id', $site->id)->whereHas('translations', fn ($query) => $query->where('slug', 'about'))->firstOrFail();
    $legacyDirectoryExisted = is_dir(public_path('site/webblocks-ui/about'));

    $this->putTrackedPublicSiteFile('site/webblocks-ui/pages/about/page.css', 'body { color: red; }');

    PageAsset::query()->create([
      'page_id' => $page->id,
      'type' => 'css',
      'path' => '/site/webblocks-ui/pages/about/page.css',
      'load_position' => 'head',
      'is_enabled' => true,
      'sort_order' => 0,
    ]);

    $siteExport = app(SiteExportManager::class)->export($site, true);
    $archive = new ZipArchive;
    $archive->open(Storage::disk('site-exports')->path($siteExport->archive_path));
    $pageAssets = json_decode((string) $archive->getFromName('data/page_assets.json'), true);
    $this->assertCount(1, $pageAssets);
    $this->assertNotFalse($archive->locateName('files/public/site/webblocks-ui/pages/about/page.css'));
    $archive->close();

    $siteImport = app(SiteImportManager::class)->inspectUpload(
      new UploadedFile(Storage::disk('site-exports')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true)
    );

    $siteImport = app(SiteImportManager::class)->import($siteImport, SiteImportOptions::fromArray([
      'site_name' => 'Imported With Page Assets',
      'site_handle' => 'imported-page-assets',
    ]));

    $importedSite = Site::query()->findOrFail($siteImport->target_site_id);
    $importedPage = Page::query()->where('site_id', $importedSite->id)->whereHas('translations', fn ($query) => $query->where('slug', 'about'))->firstOrFail();

    $this->assertDatabaseHas('page_assets', [
      'page_id' => $importedPage->id,
      'path' => '/site/webblocks-ui/pages/about/page.css',
    ]);
    $this->assertFileExists(public_path('site/webblocks-ui/pages/about/page.css'));

    if (! $legacyDirectoryExisted) {
      $this->assertDirectoryDoesNotExist(public_path('site/webblocks-ui/about'));
    }
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
    [$site, , $sourceSharedSlot] = $this->seedCloneableSite(withFile: true);
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

    $this->assertNull($aboutPage->created_by_user_id);
    $this->assertNull($aboutPage->updated_by_user_id);

    $this->assertDatabaseHas('page_translations', ['page_id' => $aboutPage->id, 'slug' => 'hakkinda']);
    $this->assertDatabaseHas('page_translations', [
      'page_id' => $aboutPage->id,
      'slug' => 'about',
      'seo_title' => 'About SEO',
      'og_title' => 'About OG',
    ]);
    $this->assertDatabaseHas('page_translations', [
      'page_id' => $aboutPage->id,
      'slug' => 'hakkinda',
      'seo_title' => 'Hakkinda SEO',
      'og_title' => 'Hakkinda OG',
    ]);

    $header = Block::query()->where('page_id', $aboutPage->id)->where('type', 'header')->firstOrFail();
    $plainText = Block::query()->where('page_id', $aboutPage->id)->where('type', 'plain_text')->firstOrFail();
    $this->assertDatabaseHas('block_text_translations', ['block_id' => $header->id, 'title' => 'Hakkinda']);
    $this->assertNull($header->getRawOriginal('title'));
    $this->assertNull($plainText->getRawOriginal('content'));

    $imageBlock = Block::query()->where('page_id', $aboutPage->id)->where('type', 'image')->firstOrFail();
    $this->assertNotNull($imageBlock->asset_id);
    $this->assertNull($imageBlock->getRawOriginal('title'));
    $this->assertNull($imageBlock->getRawOriginal('subtitle'));
    $headerSlot = PageSlot::query()->where('page_id', $aboutPage->id)->where('source_type', PageSlot::SOURCE_TYPE_SHARED_SLOT)->firstOrFail();
    $importedSharedSlot = SharedSlot::query()->where('site_id', $importedSite->id)->where('handle', $sourceSharedSlot->handle)->firstOrFail();

    $this->assertSame($importedSharedSlot->id, $headerSlot->shared_slot_id);
    $this->assertNotSame($sourceSharedSlot->id, $importedSharedSlot->id);
    $this->assertSame($importedSite->id, $importedSharedSlot->site_id);
    $this->assertNull($importedSharedSlot->created_by_user_id);
    $this->assertNull($importedSharedSlot->updated_by_user_id);
    $this->assertSame(1, Page::query()->where('site_id', $importedSite->id)->where('page_type', Page::TYPE_SHARED_SLOT_SOURCE)->count());
    $presented = app(PublicPagePresenter::class)->present($aboutPage->fresh([
      'site',
      'translations',
      'slots.slotType',
      'slots.sharedSlot.slotBlocks.block',
      'blocks.blockType',
      'blocks.children.blockType',
      'blocks.children.textTranslations',
      'blocks.textTranslations',
      'blocks.imageTranslations',
      'blocks.blockAssets.asset',
    ]));
    $headerPresented = collect($presented['slots'])->firstWhere('slug', 'header');
    $mainSlot = collect($presented['slots'])->firstWhere('slug', 'main');
    $presentedBlock = $mainSlot['blocks']->firstWhere('type', 'plain_text');
    $sidebarSlot = collect($presented['slots'])->firstWhere('slug', 'sidebar');
    $headerSerialized = json_encode($headerPresented['blocks']->toArray());

    $this->assertIsString($headerSerialized);
    $this->assertStringContainsString('Shared About Header', $headerSerialized);
    $this->assertSame('English paragraph content', $presentedBlock->content);
    $this->assertSame('main', $mainSlot['wrapper']['element']);
    $this->assertSame('default', $mainSlot['wrapper']['preset']);
    $this->assertCount(0, $sidebarSlot['blocks']);
    Storage::disk('public')->assertExists($imageBlock->asset->path);
  }

  #[Test]
  public function export_and_import_preserve_gallery_item_translation_rows(): void
  {
    Storage::fake('site-exports');
    Storage::fake('site-transfers');
    Storage::fake('public');

    [$site, $heroAsset] = $this->seedCloneableSite(withFile: true);
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
    $turkish = Locale::query()->where('code', 'tr')->firstOrFail();
    $aboutPage = Page::query()
      ->where('site_id', $site->id)
      ->whereHas('translations', fn ($query) => $query
        ->where('locale_id', $defaultLocale->id)
        ->where('slug', 'about'))
      ->firstOrFail();

    [, $sourceGalleryItem] = $this->addGalleryBlockToPage($aboutPage, $heroAsset, $defaultLocale, $turkish);

    $siteExport = app(SiteExportManager::class)->export($site, true);
    $archive = new ZipArchive;
    $archive->open(Storage::disk('site-exports')->path($siteExport->archive_path));
    $galleryItemTranslations = json_decode((string) $archive->getFromName('data/block_gallery_item_translations.json'), true);
    $archive->close();

    $this->assertCount(2, $galleryItemTranslations);
    $this->assertTrue(collect($galleryItemTranslations)->contains(fn (array $translation) => (int) ($translation['block_media_id'] ?? 0) === $sourceGalleryItem->id));

    $siteImport = app(SiteImportManager::class)->inspectUpload(
      new UploadedFile(Storage::disk('site-exports')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true)
    );

    $siteImport = app(SiteImportManager::class)->import($siteImport, SiteImportOptions::fromArray([
      'site_name' => 'Imported Gallery Site',
      'site_handle' => 'imported-gallery-site',
    ]));

    $importedSite = Site::query()->findOrFail($siteImport->target_site_id);
    $importedAbout = Page::query()
      ->where('site_id', $importedSite->id)
      ->whereHas('translations', fn ($query) => $query
        ->where('locale_id', $defaultLocale->id)
        ->where('slug', 'about'))
      ->firstOrFail();
    $importedGallery = Block::query()->where('page_id', $importedAbout->id)->where('type', 'gallery')->firstOrFail();
    $importedGalleryItem = $importedGallery->blockAssets()->where('role', 'gallery_item')->firstOrFail();

    $this->assertNotSame($sourceGalleryItem->id, $importedGalleryItem->id);
    $this->assertDatabaseHas('block_gallery_item_translations', [
      'block_media_id' => $importedGalleryItem->id,
      'locale_id' => $defaultLocale->id,
      'caption' => 'Gallery caption',
    ]);
    $this->assertDatabaseHas('block_gallery_item_translations', [
      'block_media_id' => $importedGalleryItem->id,
      'locale_id' => $turkish->id,
      'caption' => 'Galeri aciklamasi',
      'overlay_title' => 'Galeri katman basligi',
    ]);
  }

  #[Test]
  public function export_and_import_include_site_variables(): void
  {
    Storage::fake('site-exports');
    Storage::fake('site-transfers');
    [$site] = $this->seedCloneableSite();
    $site->siteVariables()->create([
      'key' => 'support_email',
      'label' => 'Support Email',
      'value' => 'support@example.test',
      'sort_order' => 1,
      'is_enabled' => true,
    ]);

    $siteExport = app(SiteExportManager::class)->export($site, false);
    $archive = new ZipArchive;
    $archive->open(Storage::disk('site-exports')->path($siteExport->archive_path));
    $siteVariables = json_decode((string) $archive->getFromName('data/site_variables.json'), true);
    $archive->close();

    $this->assertCount(1, $siteVariables);
    $this->assertSame('support_email', $siteVariables[0]['key']);

    $siteImport = app(SiteImportManager::class)->inspectUpload(
      new UploadedFile(Storage::disk('site-exports')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true)
    );

    $siteImport = app(SiteImportManager::class)->import($siteImport, SiteImportOptions::fromArray([
      'site_name' => 'Imported Variables',
      'site_handle' => 'imported-variables',
    ]));

    $importedSite = Site::query()->findOrFail($siteImport->target_site_id);
    $this->assertDatabaseHas('site_variables', [
      'site_id' => $importedSite->id,
      'key' => 'support_email',
      'value' => 'support@example.test',
    ]);
  }

  #[Test]
  public function export_and_import_preserve_navigation_item_icons_for_sidebar_navigation_rendering(): void
  {
    Storage::fake('site-exports');
    Storage::fake('site-transfers');
    [$site] = $this->seedCloneableSite();

    $sourceItem = NavigationItem::query()
      ->where('site_id', $site->id)
      ->where('title', 'About')
      ->firstOrFail();

    $sourceItem->update(['icon' => 'rocket']);

    $siteExport = app(SiteExportManager::class)->export($site, false);
    $archive = new ZipArchive;
    $archive->open(Storage::disk('site-exports')->path($siteExport->archive_path));
    $navigationItems = json_decode((string) $archive->getFromName('data/navigation_items.json'), true);
    $archive->close();

    $exportedItem = collect($navigationItems)->firstWhere('title', 'About');

    $this->assertSame('rocket', $exportedItem['icon'] ?? null);

    $siteImport = app(SiteImportManager::class)->inspectUpload(
      new UploadedFile(Storage::disk('site-exports')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true)
    );

    $siteImport = app(SiteImportManager::class)->import($siteImport, SiteImportOptions::fromArray([
      'site_name' => 'Imported Navigation Icons',
      'site_handle' => 'imported-navigation-icons',
    ]));

    $importedSite = Site::query()->findOrFail($siteImport->target_site_id);
    $importedItem = NavigationItem::query()
      ->where('site_id', $importedSite->id)
      ->where('title', 'About')
      ->firstOrFail();

    $this->assertSame('rocket', $importedItem->icon);
    $this->assertSame('rocket', $importedItem->sidebarIcon());

    $rendered = view('pages.partials.blocks.sidebar-navigation-menu-item', [
      'item' => $importedItem->load('children'),
      'nested' => false,
      'showIcons' => true,
      'isNavigationItemActive' => fn (NavigationItem $item): bool => (int) $item->id === (int) $importedItem->id,
    ])->render();

    $this->assertStringContainsString('class="wb-icon wb-icon-rocket wb-sidebar-icon"', $rendered);
  }

  #[Test]
  public function import_restores_docs_public_shell_and_keeps_compatible_docs_shared_slots_compatible(): void
  {
    Storage::fake('site-exports');
    Storage::fake('site-transfers');

    $site = Site::query()->create([
      'name' => 'Docs Site',
      'handle' => 'docs-site',
      'domain' => 'docs-site.example.test',
      'is_primary' => false,
    ]);

    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
    $site->locales()->sync([$defaultLocale->id => ['is_enabled' => true]]);

    $headerSlotType = SlotType::query()->firstOrCreate(
      ['slug' => 'header'],
      ['name' => 'Header', 'status' => 'published', 'sort_order' => 0, 'is_system' => true],
    );
    $sidebarSlotType = SlotType::query()->firstOrCreate(
      ['slug' => 'sidebar'],
      ['name' => 'Sidebar', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
    );

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Docs',
      'slug' => 'docs',
      'status' => Page::STATUS_PUBLISHED,
      'settings' => ['public_shell' => 'docs'],
    ]);

    $headerSharedSlot = SharedSlot::query()->create([
      'site_id' => $site->id,
      'name' => 'Docs Header',
      'handle' => 'docs-header',
      'slot_name' => 'header',
      'public_shell' => 'docs',
      'is_active' => true,
    ]);
    $sidebarSharedSlot = SharedSlot::query()->create([
      'site_id' => $site->id,
      'name' => 'Docs Sidebar',
      'handle' => 'docs-sidebar',
      'slot_name' => 'sidebar',
      'public_shell' => 'docs',
      'is_active' => true,
    ]);

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $headerSlotType->id,
      'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
      'shared_slot_id' => $headerSharedSlot->id,
      'sort_order' => 0,
    ]);
    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $sidebarSlotType->id,
      'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
      'shared_slot_id' => $sidebarSharedSlot->id,
      'sort_order' => 1,
    ]);

    $siteExport = app(SiteExportManager::class)->export($site, false);
    $siteImport = app(SiteImportManager::class)->inspectUpload(
      new UploadedFile(Storage::disk('site-exports')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true)
    );

    $siteImport = app(SiteImportManager::class)->import($siteImport, SiteImportOptions::fromArray([
      'site_name' => 'Imported Docs Site',
      'site_handle' => 'imported-docs-site',
    ]));

    $importedSite = Site::query()->findOrFail($siteImport->target_site_id);
    $importedPage = Page::query()->where('site_id', $importedSite->id)->whereHas('translations', fn ($query) => $query->where('slug', 'docs'))->firstOrFail();
    $importedSlots = PageSlot::query()->where('page_id', $importedPage->id)->with('sharedSlot')->orderBy('sort_order')->get();

    $this->assertSame('docs', $importedPage->publicShellPreset());
    $this->assertSame([], $importedSlots[0]->sharedSlotCompatibilityIssues($importedSlots[0]->sharedSlot));
    $this->assertSame([], $importedSlots[1]->sharedSlotCompatibilityIssues($importedSlots[1]->sharedSlot));
    $this->assertNull($importedSlots[0]->sharedSlotWarning());
    $this->assertNull($importedSlots[1]->sharedSlotWarning());
  }

  #[Test]
  public function legacy_import_without_page_public_shell_still_succeeds_and_falls_back_to_default(): void
  {
    Storage::fake('site-exports');
    Storage::fake('site-transfers');
    [$site] = $this->seedCloneableSite();
    $aboutPage = Page::query()->where('site_id', $site->id)->whereHas('translations', fn ($query) => $query->where('slug', 'about'))->firstOrFail();
    $aboutPage->update(['settings' => ['public_shell' => 'docs']]);

    $siteExport = app(SiteExportManager::class)->export($site, false);
    $archivePath = Storage::disk('site-exports')->path($siteExport->archive_path);
    $archive = new ZipArchive;
    $archive->open($archivePath);
    $pages = json_decode((string) $archive->getFromName('data/pages.json'), true);
    $pages = collect($pages)->map(function (array $page): array {
      unset($page['public_shell'], $page['settings']);

      return $page;
    })->all();
    $archive->addFromString('data/pages.json', json_encode($pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    $archive->close();

    $siteImport = app(SiteImportManager::class)->inspectUpload(
      new UploadedFile($archivePath, $siteExport->archive_name, 'application/zip', null, true)
    );

    $siteImport = app(SiteImportManager::class)->import($siteImport, SiteImportOptions::fromArray([
      'site_name' => 'Imported Legacy Site',
      'site_handle' => 'imported-legacy-site',
    ]));

    $importedSite = Site::query()->findOrFail($siteImport->target_site_id);
    $importedPage = Page::query()->where('site_id', $importedSite->id)->whereHas('translations', fn ($query) => $query->where('slug', 'about'))->firstOrFail();

    $this->assertSame('default', $importedPage->publicShellPreset());
  }

  #[Test]
  public function import_updates_existing_same_handle_shared_slot_deterministically(): void
  {
    Storage::fake('site-exports');
    Storage::fake('site-transfers');
    Storage::fake('public');
    [$site, , $sourceSharedSlot] = $this->seedCloneableSite(withFile: true);
    $siteExport = app(SiteExportManager::class)->export($site, true);

    $siteImport = app(SiteImportManager::class)->inspectUpload(
      new UploadedFile(Storage::disk('site-exports')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true)
    );

    $siteImport = app(SiteImportManager::class)->import($siteImport, SiteImportOptions::fromArray([
      'site_name' => 'Imported UI Docs',
      'site_handle' => 'imported-ui-docs-collision',
    ]));

    $importedSite = Site::query()->findOrFail($siteImport->target_site_id);
    $sharedSlot = SharedSlot::query()->where('site_id', $importedSite->id)->where('handle', $sourceSharedSlot->handle)->firstOrFail();

    $sharedSlot->update(['name' => 'Before Reimport', 'is_active' => false]);

    $secondImport = app(SiteImportManager::class)->inspectUpload(
      new UploadedFile(Storage::disk('site-exports')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true)
    );

    $secondImport = app(SiteImportManager::class)->import($secondImport, SiteImportOptions::fromArray([
      'site_name' => 'Imported UI Docs Again',
      'site_handle' => 'imported-ui-docs-collision-2',
    ]));

    $secondImportedSite = Site::query()->findOrFail($secondImport->target_site_id);
    $secondSharedSlot = SharedSlot::query()->where('site_id', $secondImportedSite->id)->where('handle', $sourceSharedSlot->handle)->firstOrFail();

    $this->assertSame($sourceSharedSlot->name, $secondSharedSlot->name);
    $this->assertTrue($secondSharedSlot->is_active);
  }

  #[Test]
  public function imported_hidden_shared_slot_source_pages_are_not_publicly_routable(): void
  {
    Storage::fake('site-exports');
    Storage::fake('site-transfers');
    Storage::fake('public');
    [$site, , $sourceSharedSlot] = $this->seedCloneableSite(withFile: true);
    $siteExport = app(SiteExportManager::class)->export($site, true);

    $siteImport = app(SiteImportManager::class)->inspectUpload(
      new UploadedFile(Storage::disk('site-exports')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true)
    );

    $siteImport = app(SiteImportManager::class)->import($siteImport, SiteImportOptions::fromArray([
      'site_name' => 'Imported UI Docs Hidden',
      'site_handle' => 'imported-ui-docs-hidden',
    ]));

    $importedSite = Site::query()->findOrFail($siteImport->target_site_id);
    $importedSharedSlot = SharedSlot::query()->where('site_id', $importedSite->id)->where('handle', $sourceSharedSlot->handle)->firstOrFail();
    $sourcePage = Page::query()->where('site_id', $importedSite->id)->where('page_type', Page::TYPE_SHARED_SLOT_SOURCE)->firstOrFail();

    $sourcePage->update(['status' => Page::STATUS_PUBLISHED]);

    $this->withHeader('Host', $importedSite->domain ?? 'localhost')
      ->get('/p/'.$sourcePage->slug)
      ->assertNotFound();

    $this->assertSame($importedSharedSlot->id, (int) data_get($sourcePage->settings, 'shared_slot_id'));
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
      ->whereHas('page', fn ($query) => $query
        ->where('site_id', $importedSite->id)
        ->where('page_type', '!=', Page::TYPE_SHARED_SLOT_SOURCE))
      ->get()
      ->first(fn (PageSlot $slot) => data_get($slot->settings, 'custom') === 'keep-me');

    $this->assertNotNull($importedSlot);
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
  public function site_transfers_index_renders_bulk_delete_selection_and_modals_without_browser_confirm(): void
  {
    Storage::fake('site-exports');
    Storage::fake('site-transfers');
    [$site] = $this->seedCloneableSite();
    $user = User::factory()->superAdmin()->create();
    $siteExport = $this->createSiteExportRecord($site, 'render-export.zip');
    $siteImport = $this->createSiteImportRecord('render-import.zip');

    $response = $this->actingAs($user)->get(route('admin.site-transfers.exports.index'));

    $response->assertOk();
    $response->assertSee('data-wb-admin-bulk-listing', false);
    $response->assertSee('id="select_all_visible_site_exports"', false);
    $response->assertSee('id="select_all_visible_site_imports"', false);
    $response->assertSee('id="bulk-delete-site-exports-modal"', false);
    $response->assertSee('id="bulk-delete-site-imports-modal"', false);
    $response->assertSee('data-wb-admin-bulk-input-name="site_export_ids[]"', false);
    $response->assertSee('data-wb-admin-bulk-input-name="site_import_ids[]"', false);
    $response->assertSee('data-wb-target="#delete-site-export-'.$siteExport->id.'-modal"', false);
    $response->assertSee('data-wb-target="#delete-site-import-'.$siteImport->id.'-modal"', false);
    $response->assertDontSee('confirm(', false);
  }

  #[Test]
  public function site_export_bulk_delete_requires_system_access(): void
  {
    Storage::fake('site-exports');
    [$site] = $this->seedCloneableSite();
    $siteExport = $this->createSiteExportRecord($site, 'blocked-export.zip');
    $editor = User::factory()->editor()->create();
    $editor->sites()->sync([$site->id]);

    $this->delete(route('admin.site-transfers.exports.bulk-destroy'), [
      'site_export_ids' => [$siteExport->id],
    ])->assertRedirect(route('login'));

    $this->actingAs($editor)->delete(route('admin.site-transfers.exports.bulk-destroy'), [
      'site_export_ids' => [$siteExport->id],
    ])->assertForbidden();

    $this->assertDatabaseHas('site_exports', ['id' => $siteExport->id]);
    Storage::disk('site-exports')->assertExists('blocked-export.zip');
  }

  #[Test]
  public function super_admin_can_bulk_delete_selected_site_exports_and_archive_files(): void
  {
    Storage::fake('site-exports');
    [$site] = $this->seedCloneableSite();
    $user = User::factory()->superAdmin()->create();
    $first = $this->createSiteExportRecord($site, 'first-export.zip');
    $second = $this->createSiteExportRecord($site, 'second-export.zip');

    $response = $this->actingAs($user)->delete(route('admin.site-transfers.exports.bulk-destroy'), [
      'site_export_ids' => [$first->id, $second->id],
    ]);

    $response->assertRedirect(route('admin.site-transfers.exports.index'));
    $response->assertSessionHas('status', '2 selected site exports deleted.');
    $this->assertDatabaseMissing('site_exports', ['id' => $first->id]);
    $this->assertDatabaseMissing('site_exports', ['id' => $second->id]);
    Storage::disk('site-exports')->assertMissing('first-export.zip');
    Storage::disk('site-exports')->assertMissing('second-export.zip');
  }

  #[Test]
  public function site_export_bulk_delete_rejects_missing_or_invalid_ids_safely(): void
  {
    Storage::fake('site-exports');
    [$site] = $this->seedCloneableSite();
    $user = User::factory()->superAdmin()->create();
    $siteExport = $this->createSiteExportRecord($site, 'invalid-export.zip');

    $this->actingAs($user)->delete(route('admin.site-transfers.exports.bulk-destroy'), [
      'site_export_ids' => [],
    ])->assertSessionHasErrors('site_export_ids');

    $this->actingAs($user)->delete(route('admin.site-transfers.exports.bulk-destroy'), [
      'site_export_ids' => [$siteExport->id, 999999],
    ])->assertSessionHasErrors('site_export_ids.1');

    $this->assertDatabaseHas('site_exports', ['id' => $siteExport->id]);
    Storage::disk('site-exports')->assertExists('invalid-export.zip');
  }

  #[Test]
  public function site_export_bulk_delete_reports_partial_failures(): void
  {
    Storage::fake('site-exports');
    [$site] = $this->seedCloneableSite();
    $user = User::factory()->superAdmin()->create();
    $safe = $this->createSiteExportRecord($site, 'safe-export.zip');
    $unsafe = SiteExport::query()->create([
      'site_id' => $site->id,
      'status' => SiteExport::STATUS_COMPLETED,
      'archive_disk' => 'site-exports',
      'archive_path' => '../unsafe-export.zip',
      'archive_name' => 'unsafe-export.zip',
      'archive_size_bytes' => 1,
    ]);

    $response = $this->actingAs($user)->delete(route('admin.site-transfers.exports.bulk-destroy'), [
      'site_export_ids' => [$safe->id, $unsafe->id],
    ]);

    $response->assertRedirect(route('admin.site-transfers.exports.index'));
    $response->assertSessionHas('status', '1 selected site export deleted. 1 could not be deleted.');
    $response->assertSessionHasErrors('site_exports');
    $this->assertDatabaseMissing('site_exports', ['id' => $safe->id]);
    $this->assertDatabaseHas('site_exports', ['id' => $unsafe->id]);
  }

  #[Test]
  public function site_import_bulk_delete_requires_system_access(): void
  {
    Storage::fake('site-transfers');
    [$site] = $this->seedCloneableSite();
    $siteImport = $this->createSiteImportRecord('blocked-import.zip');
    $editor = User::factory()->editor()->create();
    $editor->sites()->sync([$site->id]);

    $this->delete(route('admin.site-transfers.imports.bulk-destroy'), [
      'site_import_ids' => [$siteImport->id],
    ])->assertRedirect(route('login'));

    $this->actingAs($editor)->delete(route('admin.site-transfers.imports.bulk-destroy'), [
      'site_import_ids' => [$siteImport->id],
    ])->assertForbidden();

    $this->assertDatabaseHas('site_imports', ['id' => $siteImport->id]);
    Storage::disk('site-transfers')->assertExists('blocked-import.zip');
  }

  #[Test]
  public function super_admin_can_bulk_delete_selected_site_imports_and_archive_files(): void
  {
    Storage::fake('site-transfers');
    $user = User::factory()->superAdmin()->create();
    $first = $this->createSiteImportRecord('first-import.zip');
    $second = $this->createSiteImportRecord('second-import.zip');

    $response = $this->actingAs($user)->delete(route('admin.site-transfers.imports.bulk-destroy'), [
      'site_import_ids' => [$first->id, $second->id],
    ]);

    $response->assertRedirect(route('admin.site-transfers.exports.index'));
    $response->assertSessionHas('status', '2 selected site imports deleted.');
    $this->assertDatabaseMissing('site_imports', ['id' => $first->id]);
    $this->assertDatabaseMissing('site_imports', ['id' => $second->id]);
    Storage::disk('site-transfers')->assertMissing('first-import.zip');
    Storage::disk('site-transfers')->assertMissing('second-import.zip');
  }

  #[Test]
  public function site_import_bulk_delete_rejects_missing_or_invalid_ids_safely(): void
  {
    Storage::fake('site-transfers');
    $user = User::factory()->superAdmin()->create();
    $siteImport = $this->createSiteImportRecord('invalid-import.zip');

    $this->actingAs($user)->delete(route('admin.site-transfers.imports.bulk-destroy'), [
      'site_import_ids' => [],
    ])->assertSessionHasErrors('site_import_ids');

    $this->actingAs($user)->delete(route('admin.site-transfers.imports.bulk-destroy'), [
      'site_import_ids' => [$siteImport->id, 999999],
    ])->assertSessionHasErrors('site_import_ids.1');

    $this->assertDatabaseHas('site_imports', ['id' => $siteImport->id]);
    Storage::disk('site-transfers')->assertExists('invalid-import.zip');
  }

  #[Test]
  public function site_import_bulk_delete_reports_partial_failures(): void
  {
    Storage::fake('site-transfers');
    $user = User::factory()->superAdmin()->create();
    $safe = $this->createSiteImportRecord('safe-import.zip');
    $unsafe = SiteImport::query()->create([
      'status' => SiteImport::STATUS_VALIDATED,
      'source_archive_name' => 'unsafe-import.zip',
      'archive_disk' => 'site-transfers',
      'archive_path' => '../unsafe-import.zip',
    ]);

    $response = $this->actingAs($user)->delete(route('admin.site-transfers.imports.bulk-destroy'), [
      'site_import_ids' => [$safe->id, $unsafe->id],
    ]);

    $response->assertRedirect(route('admin.site-transfers.exports.index'));
    $response->assertSessionHas('status', '1 selected site import deleted. 1 could not be deleted.');
    $response->assertSessionHasErrors('site_imports');
    $this->assertDatabaseMissing('site_imports', ['id' => $safe->id]);
    $this->assertDatabaseHas('site_imports', ['id' => $unsafe->id]);
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

  private function createSiteExportRecord(Site $site, string $archivePath): SiteExport
  {
    Storage::disk('site-exports')->put($archivePath, 'export contents');

    return SiteExport::query()->create([
      'site_id' => $site->id,
      'status' => SiteExport::STATUS_COMPLETED,
      'archive_disk' => 'site-exports',
      'archive_path' => $archivePath,
      'archive_name' => $archivePath,
      'archive_size_bytes' => strlen('export contents'),
    ]);
  }

  private function createSiteImportRecord(string $archivePath): SiteImport
  {
    Storage::disk('site-transfers')->put($archivePath, 'import contents');

    return SiteImport::query()->create([
      'status' => SiteImport::STATUS_VALIDATED,
      'source_archive_name' => $archivePath,
      'archive_disk' => 'site-transfers',
      'archive_path' => $archivePath,
    ]);
  }
}
