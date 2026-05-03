<?php

namespace Tests\Feature\Integrity;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageRevision;
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

    private function headerType(): BlockType
    {
        return BlockType::query()->updateOrCreate(
            ['slug' => 'header'],
            ['name' => 'Header', 'source_type' => 'static', 'status' => 'published'],
        );
    }

    private function plainTextType(): BlockType
    {
        return BlockType::query()->updateOrCreate(
            ['slug' => 'plain_text'],
            ['name' => 'Plain Text', 'source_type' => 'static', 'status' => 'published'],
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
            'type' => 'header',
            'block_type_id' => $this->headerType()->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
            'variant' => 'h1',
            'status' => 'published',
            'is_system' => false,
        ]);

        $block->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Hero',
        ]);
        $block->textTranslations()->create([
            'locale_id' => $turkish->id,
            'title' => 'Kahraman',
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
        ]);

        $manager->restore($page->fresh(), $revision, $user);

        $restoredPage = $page->fresh(['translations', 'blocks.textTranslations']);
        $restoredBlock = $restoredPage->blocks->firstOrFail();
        $resolvedBlock = app(BlockTranslationResolver::class)->resolve($restoredBlock, 'en');

        $this->assertSame('Hakkinda', $restoredPage->translations->firstWhere('locale_id', $turkish->id)?->name);
        $this->assertNull($restoredBlock->getRawOriginal('title'));
        $this->assertNull($restoredBlock->getRawOriginal('content'));
        $this->assertSame('Hero', $resolvedBlock->title);
        $this->assertNull($resolvedBlock->content);
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
        $header = Block::query()->where('page_id', $aboutPage->id)->where('type', 'header')->firstOrFail();

        $this->assertDatabaseHas('page_translations', ['page_id' => $aboutPage->id, 'slug' => 'hakkinda']);
        $this->assertDatabaseHas('block_text_translations', ['block_id' => $header->id, 'title' => 'Hakkinda']);
        $this->assertNull($header->getRawOriginal('title'));
        $this->assertSame(['en', 'tr'], $targetSite->fresh()->enabledLocales()->orderBy('code')->pluck('code')->all());
    }

    #[Test]
    public function export_and_import_preserve_translations_locale_assignments_and_public_rendering(): void
    {
        Storage::fake('site-exports');
        Storage::fake('site-transfers');
        Storage::fake('public');
        [$sourceSite] = $this->seedCloneableSite(withFile: true);
        $export = app(SiteExportManager::class)->export($sourceSite, true);

        $import = app(SiteImportManager::class)->inspectUpload(
            new UploadedFile(Storage::disk('site-exports')->path($export->archive_path), $export->archive_name, 'application/zip', null, true)
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
        $this->get('http://imported.example.test/p/about')->assertOk()->assertSee('English paragraph content');
        $this->get('http://imported.example.test/tr/p/hakkinda')->assertOk()->assertSee('Turkce paragraf icerigi');
    }

    #[Test]
    public function revision_restore_can_normalize_legacy_snapshot_blocks_without_translation_rows(): void
    {
        $site = $this->defaultSite();
        $user = User::factory()->siteAdmin()->create();
        $user->sites()->sync([$site->id]);
        $slotType = $this->slotType();
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => Page::STATUS_PUBLISHED,
        ]);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
        ]);

        $revision = PageRevision::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'created_by' => $user->id,
            'label' => 'Legacy snapshot',
            'snapshot' => [
                'schema_version' => 1,
                'page' => [
                    'page_type' => 'default',
                    'page_type_id' => null,
                    'layout_id' => null,
                    'status' => Page::STATUS_PUBLISHED,
                    'published_at' => null,
                    'review_requested_at' => null,
                ],
                'translations' => [[
                    'locale_id' => $this->defaultLocale()->id,
                    'name' => 'About',
                    'slug' => 'about',
                    'path' => '/p/about',
                ]],
                'slots' => [[
                    'slot_type_id' => $slotType->id,
                    'sort_order' => 0,
                ]],
                'blocks' => [[
                    'snapshot_id' => 100,
                    'parent_snapshot_id' => null,
                    'type' => 'header',
                    'block_type_id' => $this->headerType()->id,
                    'source_type' => 'static',
                    'slot' => 'main',
                    'slot_type_id' => $slotType->id,
                    'sort_order' => 0,
                    'title' => 'Legacy hero',
                    'subtitle' => null,
                    'content' => null,
                    'url' => null,
                    'asset_id' => null,
                    'variant' => 'h2',
                    'meta' => null,
                    'settings' => null,
                    'status' => 'published',
                    'is_system' => false,
                    'block_assets' => [],
                    'text_translations' => [],
                    'button_translations' => [],
                    'image_translations' => [],
                    'contact_form_translations' => [],
                ]],
            ],
        ]);

        app(PageRevisionManager::class)->restore($page->fresh(), $revision, $user);

        $restoredBlock = $page->fresh()->blocks()->with('textTranslations')->firstOrFail();

        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $restoredBlock->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Legacy hero',
            'content' => null,
        ]);
        $this->assertNull($restoredBlock->getRawOriginal('title'));
        $this->assertNull($restoredBlock->getRawOriginal('content'));
        $this->get('/p/about')->assertOk()->assertSee('<h2>Legacy hero</h2>', false);
    }

    #[Test]
    public function export_import_can_normalize_packages_missing_block_translation_arrays(): void
    {
        Storage::fake('site-exports');
        Storage::fake('site-transfers');
        Storage::fake('public');
        [$sourceSite] = $this->seedCloneableSite(withFile: true);
        $export = app(SiteExportManager::class)->export($sourceSite, true);

        $archivePath = Storage::disk('site-exports')->path($export->archive_path);
        $archive = new \ZipArchive;
        $archive->open($archivePath);

        foreach ([
            'data/block_text_translations.json',
            'data/block_button_translations.json',
            'data/block_image_translations.json',
            'data/block_contact_form_translations.json',
        ] as $file) {
            $archive->addFromString($file, json_encode([], JSON_PRETTY_PRINT));
        }

        $blocks = json_decode((string) $archive->getFromName('data/blocks.json'), true);
        $blocks = collect($blocks)->map(function (array $block) {
            if ($block['type'] === 'plain_text') {
                $block['title'] = null;
                $block['content'] = 'English paragraph content';
            }

            if ($block['type'] === 'header') {
                $block['title'] = 'About';
            }

            return $block;
        })->all();
        $archive->addFromString('data/blocks.json', json_encode($blocks, JSON_PRETTY_PRINT));

        $archive->close();

        $import = app(SiteImportManager::class)->inspectUpload(
            new UploadedFile($archivePath, $export->archive_name, 'application/zip', null, true)
        );

        $import = app(SiteImportManager::class)->import($import, SiteImportOptions::fromArray([
            'site_name' => 'Legacy Compatible Import',
            'site_handle' => 'legacy-compatible-import',
            'site_domain' => 'legacy-compatible.example.test',
        ]));

        $site = Site::query()->findOrFail($import->target_site_id);
        $aboutPage = Page::query()
            ->where('site_id', $site->id)
            ->whereHas('translations', fn ($query) => $query->where('slug', 'about'))
            ->firstOrFail();

        $plainText = Block::query()->where('page_id', $aboutPage->id)->where('type', 'plain_text')->firstOrFail();

        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $plainText->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => null,
            'content' => 'English paragraph content',
        ]);
        $this->assertNull($plainText->fresh()->getRawOriginal('title'));
        $this->assertNull($plainText->fresh()->getRawOriginal('content'));
        $this->get('http://legacy-compatible.example.test/p/about')->assertOk()->assertSee('English paragraph content');
    }
}
