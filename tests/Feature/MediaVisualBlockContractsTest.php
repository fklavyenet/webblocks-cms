<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockMedia;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use App\Models\User;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaVisualBlockContractsTest extends TestCase
{
    use RefreshDatabase;

    private function seedFoundation(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);
    }

    private function slotType(): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
    }

    private function page(): Page
    {
        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Media blocks',
            'slug' => 'media-blocks',
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => 'Media blocks', 'slug' => 'media-blocks', 'path' => '/p/media-blocks'],
        );

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
        ]);

        return $page;
    }

    private function media(string $kind, string $filename, string $mimeType, string $path): Media
    {
        return Media::query()->create([
            'disk' => 'public',
            'path' => $path,
            'filename' => $filename,
            'original_name' => $filename,
            'extension' => pathinfo($filename, PATHINFO_EXTENSION),
            'mime_type' => $mimeType,
            'size' => 1024,
            'kind' => $kind,
            'visibility' => 'public',
            'title' => pathinfo($filename, PATHINFO_FILENAME),
        ]);
    }

    private function blockTypeId(string $slug): int
    {
        return (int) BlockType::query()->where('slug', $slug)->value('id');
    }

    private function adminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }

    private function htmlXPath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private function assertLabelsRemainOutsideSelectorCard(DOMXPath $xpath, DOMNode $selectorCard, array $fieldIds): void
    {
        foreach ($fieldIds as $fieldId) {
            $this->assertSame(0, $xpath->query('.//label[@for="'.$fieldId.'"]', $selectorCard)->length);
            $this->assertSame(1, $xpath->query('//label[@for="'.$fieldId.'"]')->length);
        }
    }

    #[Test]
    public function image_gallery_download_file_video_and_audio_forms_are_shipped(): void
    {
        foreach (['card', 'image', 'gallery', 'download', 'file', 'video', 'audio'] as $slug) {
            $this->assertFileExists(resource_path('views/admin/blocks/types/'.$slug.'.blade.php'));
            $this->assertFileExists(resource_path('views/pages/partials/blocks/'.$slug.'.blade.php'));
        }
    }

    #[Test]
    public function card_block_round_trips_shared_media_and_translated_image_fields(): void
    {
        $this->seedFoundation();
        $user = $this->adminUser();

        $page = $this->page();
        $image = $this->media('image', 'service-card.jpg', 'image/jpeg', 'media/images/service-card.jpg');

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'block_type_id' => $this->blockTypeId('card'),
            'sort_order' => 0,
            'title' => 'Service card',
            'subtitle' => 'Service summary',
            'content' => 'Shared image cards should stay source-backed.',
            'action_label' => 'Learn more',
            'card_url' => '/services/design',
            'card_target' => '_blank',
            'card_variant' => 'promo',
            'image_position' => 'bottom',
            'image_align' => 'end',
            'image_aspect' => 'wide',
            'image_alt' => 'Design service illustration',
            'image_caption' => 'Optional card image caption',
            'asset_id' => $image->id,
            'status' => 'published',
        ]);

        $response->assertSessionDoesntHaveErrors();

        $block = Block::query()->where('type', 'card')->firstOrFail();
        $settings = json_decode((string) $block->getRawOriginal('settings'), true);

        $this->assertSame($image->id, $block->media_id);
        $this->assertSame('/services/design', $settings['url']);
        $this->assertSame('_blank', $settings['target']);
        $this->assertSame('promo', $settings['variant']);
        $this->assertSame('bottom', $settings['image_position']);
        $this->assertSame('end', $settings['image_align']);
        $this->assertSame('wide', $settings['image_aspect']);
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $block->id,
            'title' => 'Service card',
            'subtitle' => 'Service summary',
            'content' => 'Shared image cards should stay source-backed.',
            'meta' => 'Learn more',
        ]);
        $this->assertDatabaseHas('block_image_translations', [
            'block_id' => $block->id,
            'caption' => 'Optional card image caption',
            'alt_text' => 'Design service illustration',
        ]);
    }

    #[Test]
    public function image_block_round_trips_media_caption_and_alt_through_translation_rows(): void
    {
        $this->seedFoundation();
        $user = $this->adminUser();

        $page = $this->page();
        $image = $this->media('image', 'hero.jpg', 'image/jpeg', 'media/images/hero.jpg');

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'block_type_id' => $this->blockTypeId('image'),
            'sort_order' => 0,
            'title' => 'Product overview',
            'subtitle' => 'Overview alt text',
            'url' => '/product',
            'asset_id' => $image->id,
            'status' => 'published',
        ]);

        $response->assertSessionDoesntHaveErrors();

        $block = Block::query()->where('type', 'image')->firstOrFail();

        $this->assertSame($image->id, $block->media_id);
        $this->assertSame('/product', $block->url);
        $this->assertNull($block->fresh()->getRawOriginal('title'));
        $this->assertNull($block->fresh()->getRawOriginal('subtitle'));
        $this->assertDatabaseHas('block_image_translations', [
            'block_id' => $block->id,
            'caption' => 'Product overview',
            'alt_text' => 'Overview alt text',
        ]);
    }

    #[Test]
    public function image_edit_form_shows_one_selected_media_summary_without_duplicate_preview_card(): void
    {
        $this->seedFoundation();
        $user = $this->adminUser();

        $page = $this->page();
        $image = $this->media('image', 'hero.jpg', 'image/jpeg', 'media/images/hero.jpg');
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'image',
            'block_type_id' => $this->blockTypeId('image'),
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
            'media_id' => $image->id,
            'url' => '/product',
            'status' => 'published',
            'is_system' => false,
        ]);

        $block->imageTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'caption' => 'Product overview',
            'alt_text' => 'Overview alt text',
        ]);

        $response = $this->actingAs($user)->followingRedirects()->get(route('admin.blocks.edit', $block));

        $response->assertOk();
        $response->assertSee('Replace Image');
        $response->assertSee('Remove');
        $response->assertSee('name="asset_id"', false);
        $response->assertSee('value="'.$image->id.'"', false);
        $response->assertSee($image->title ?: $image->filename, false);
        $response->assertSee($image->kind.' | '.$image->original_name, false);
        $response->assertSee('data-wb-picker-summary', false);
        $response->assertSee('data-wb-picker-selector-card', false);
        $response->assertSee('data-wb-picker-selector-card-title', false);
        $response->assertSee('data-wb-picker-selector-help', false);
        $response->assertSee('data-wb-picker-results-variant="compact-list"', false);
        $response->assertSee('wb-picker-results--compact', false);
        $response->assertSee('wb-picker-asset-row', false);
        $response->assertSee('data-wb-picker-filters-card', false);
        $response->assertSee('data-wb-picker-filters', false);
        $response->assertSee('Search', false);
        $response->assertSee('Folder', false);
        $response->assertSee('Kind', false);
        $response->assertDontSee('Upload to Library');

        $html = $response->getContent();
        $this->assertNotFalse($html);
        $this->assertMatchesRegularExpression('/data-wb-picker-selector-card.*Media Asset.*Choose an internal image asset for this block\./s', $html);
        $this->assertMatchesRegularExpression('/data-wb-picker-summary.*'.preg_quote($image->title ?: $image->filename, '/').'/s', $html);
        $this->assertMatchesRegularExpression('/data-wb-picker-filters-card.*Search.*Folder.*Kind/s', $html);
        $this->assertStringNotContainsString('class="wb-grid wb-grid-3 wb-picker-results"', $html);
        $this->assertStringNotContainsString('data-wb-picker-preview-grid', $html);
        $this->assertStringNotContainsString('data-wb-picker-preview data-wb-picker-preview-id="'.$image->id.'"', $html);
        $this->assertMatchesRegularExpression('/id="wb-overlay-root" class="wb-overlay-root">.*id="asset_id_picker_panel"/s', $html);

        $xpath = $this->htmlXPath($html);
        $selectorCard = $xpath->query('//*[@data-wb-picker-selector-card]')->item(0);

        $this->assertNotNull($selectorCard);
        $this->assertSame(1, $xpath->query('.//*[@data-wb-picker-selector-card-title and normalize-space()="Media Asset"]', $selectorCard)->length);
        $this->assertSame(1, $xpath->query('.//button[@data-wb-picker-open and normalize-space()="Replace Image"]', $selectorCard)->length);
        $this->assertSame(1, $xpath->query('.//button[@data-wb-picker-clear and normalize-space()="Remove"]', $selectorCard)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-wb-picker-summary]', $selectorCard)->length);
        $this->assertSame(0, $xpath->query('.//*[@data-wb-picker-preview]', $selectorCard)->length);
        $this->assertLabelsRemainOutsideSelectorCard($xpath, $selectorCard, ['subtitle', 'url', 'title']);
    }

    #[Test]
    public function single_media_block_edit_forms_use_compact_overlay_picker_without_inline_upload_or_duplicate_preview(): void
    {
        $this->seedFoundation();
        $user = $this->adminUser();

        $page = $this->page();
        $image = $this->media('image', 'card-image.jpg', 'image/jpeg', 'media/images/card-image.jpg');
        $document = $this->media('document', 'guide.pdf', 'application/pdf', 'media/documents/guide.pdf');
        $video = $this->media('video', 'walkthrough.mp4', 'video/mp4', 'media/videos/walkthrough.mp4');
        $audio = $this->media('other', 'theme.mp3', 'audio/mpeg', 'media/audio/theme.mp3');

        $blockMatrix = [
            [
                'type' => 'image',
                'asset' => $image,
                'panelTitle' => 'Choose Image',
                'replaceLabel' => 'Replace Image',
                'accept' => 'image',
                'selectorCardTitle' => 'Media Asset',
                'selectorHelp' => 'Choose an internal image asset for this block.',
                'outsideFieldIds' => ['subtitle', 'url', 'title'],
            ],
            [
                'type' => 'card',
                'asset' => $image,
                'panelTitle' => 'Choose Image',
                'replaceLabel' => 'Replace Image',
                'accept' => 'image',
                'selectorCardTitle' => 'Image',
                'selectorHelp' => 'Selecting media enables the card image. Clearing media removes the image.',
                'outsideFieldIds' => ['image_position', 'title', 'image_alt', 'image_caption'],
            ],
            [
                'type' => 'file',
                'asset' => $document,
                'panelTitle' => 'Choose File',
                'replaceLabel' => 'Replace File',
                'accept' => 'file',
                'selectorCardTitle' => 'File',
                'selectorHelp' => 'Select a Media file for the canonical file source, or leave it empty and use an external file URL.',
                'outsideFieldIds' => ['title', 'url', 'content'],
            ],
            [
                'type' => 'video',
                'asset' => $video,
                'panelTitle' => 'Choose Video',
                'replaceLabel' => 'Replace Video',
                'accept' => 'video',
                'selectorCardTitle' => 'Video',
                'selectorHelp' => 'Select a hosted Media video or leave it empty and use an external video URL.',
                'outsideFieldIds' => ['title', 'url', 'content'],
            ],
            [
                'type' => 'audio',
                'asset' => $audio,
                'panelTitle' => 'Choose Audio',
                'replaceLabel' => 'Replace Audio',
                'accept' => 'audio',
                'selectorCardTitle' => 'Audio',
                'selectorHelp' => 'Select a Media audio file or leave it empty and use an external audio URL.',
                'outsideFieldIds' => ['title', 'url', 'content'],
            ],
            [
                'type' => 'download',
                'asset' => $document,
                'panelTitle' => 'Choose Download File',
                'replaceLabel' => 'Replace Document',
                'accept' => 'file',
                'selectorCardTitle' => 'Download File',
                'selectorHelp' => 'Choose an internal document asset for this download block.',
                'outsideFieldIds' => ['title', 'subtitle', 'variant'],
            ],
        ];

        foreach ($blockMatrix as $config) {
            $block = Block::query()->create([
                'page_id' => $page->id,
                'type' => $config['type'],
                'block_type_id' => $this->blockTypeId($config['type']),
                'source_type' => 'static',
                'slot' => 'main',
                'slot_type_id' => $this->slotType()->id,
                'sort_order' => 0,
                'media_id' => $config['asset']->id,
                'status' => 'published',
                'is_system' => false,
                'title' => ucfirst($config['type']).' title',
                'subtitle' => ucfirst($config['type']).' subtitle',
                'content' => ucfirst($config['type']).' content',
                'meta' => 'Action',
                'url' => 'https://example.com/media-source',
                'settings' => $config['type'] === 'download'
                    ? json_encode(['variant' => 'secondary'], JSON_UNESCAPED_SLASHES)
                    : null,
            ]);

            if (in_array($config['type'], ['image', 'card'], true)) {
                $block->imageTranslations()->create([
                    'locale_id' => Page::defaultLocaleId(),
                    'caption' => ucfirst($config['type']).' caption',
                    'alt_text' => ucfirst($config['type']).' alt',
                ]);
            }

            $response = $this->actingAs($user)->followingRedirects()->get(route('admin.blocks.edit', $block));

            $response->assertOk();
            $response->assertSee($config['replaceLabel']);
            $response->assertSee('data-wb-picker-summary', false);
            $response->assertSee('data-wb-picker-selector-card', false);
            $response->assertSee('data-wb-picker-selector-card-title', false);
            $response->assertSee($config['selectorCardTitle'], false);
            $response->assertSee('data-wb-picker-selector-help', false);
            $response->assertSee($config['selectorHelp'], false);
            $response->assertSee('data-wb-picker-panel-mode="overlay"', false);
            $response->assertSee('data-wb-picker-results-variant="compact-list"', false);
            $response->assertSee('data-wb-picker-filters-card', false);
            $response->assertSee('data-wb-picker-filters', false);
            $response->assertSee('wb-picker-results--compact', false);
            $response->assertSee('Search', false);
            $response->assertSee('Folder', false);
            $response->assertSee('Kind', false);
            $response->assertSee('data-wb-picker-accept="'.$config['accept'].'"', false);
            $response->assertSee($config['panelTitle'], false);
            $response->assertDontSee('Upload to Library');

            $html = $response->getContent();
            $this->assertNotFalse($html);
            $this->assertMatchesRegularExpression('/data-wb-picker-selector-card.*'.preg_quote($config['selectorCardTitle'], '/').'.*'.preg_quote($config['selectorHelp'], '/').'/s', $html);
            $this->assertMatchesRegularExpression('/data-wb-picker-summary.*'.preg_quote($config['asset']->title ?: $config['asset']->filename, '/').'/s', $html);
            $this->assertMatchesRegularExpression('/data-wb-picker-filters-card.*Search.*Folder.*Kind/s', $html);
            $this->assertStringNotContainsString('class="wb-grid wb-grid-3 wb-picker-results"', $html);
            $this->assertStringNotContainsString('data-wb-picker-preview-grid', $html);
            $this->assertStringNotContainsString('data-wb-picker-preview data-wb-picker-preview-id="'.$config['asset']->id.'"', $html);
            $this->assertMatchesRegularExpression('/id="wb-overlay-root" class="wb-overlay-root">.*id="asset_id_picker_panel"/s', $html);

            $xpath = $this->htmlXPath($html);
            $selectorCard = $xpath->query('//*[@data-wb-picker-selector-card]')->item(0);

            $this->assertNotNull($selectorCard);
            $this->assertSame(1, $xpath->query('.//button[@data-wb-picker-open and normalize-space()="'.$config['replaceLabel'].'"]', $selectorCard)->length);
            $this->assertSame(1, $xpath->query('.//button[@data-wb-picker-clear and normalize-space()="Remove"]', $selectorCard)->length);
            $this->assertSame(1, $xpath->query('.//*[@data-wb-picker-summary]', $selectorCard)->length);
            $this->assertSame(1, $xpath->query('.//strong[normalize-space()="'.($config['asset']->title ?: $config['asset']->filename).'"]', $selectorCard)->length);
            $this->assertSame(0, $xpath->query('.//*[@data-wb-picker-preview]', $selectorCard)->length);
            $this->assertLabelsRemainOutsideSelectorCard($xpath, $selectorCard, $config['outsideFieldIds']);
        }
    }

    #[Test]
    public function gallery_picker_is_selection_only_while_still_using_multi_select_compact_results(): void
    {
        $this->seedFoundation();
        $user = $this->adminUser();

        $page = $this->page();
        $first = $this->media('image', 'gallery-picker-a.jpg', 'image/jpeg', 'media/images/gallery-picker-a.jpg');
        $second = $this->media('image', 'gallery-picker-b.jpg', 'image/jpeg', 'media/images/gallery-picker-b.jpg');

        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'gallery',
            'block_type_id' => $this->blockTypeId('gallery'),
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        foreach ([
            ['media_id' => $first->id, 'role' => 'gallery_item', 'position' => 0],
            ['media_id' => $second->id, 'role' => 'gallery_item', 'position' => 1],
        ] as $item) {
            BlockMedia::query()->create(['block_id' => $block->id] + $item);
        }

        $response = $this->actingAs($user)->followingRedirects()->get(route('admin.blocks.edit', $block));

        $response->assertOk();
        $response->assertSee('Add Gallery Items');
        $response->assertSee('Remove All');
        $response->assertSee('Add Selected');
        $response->assertSee('data-wb-picker-mode="multiple"', false);
        $response->assertSee('data-wb-picker-results-variant="compact-list"', false);
        $response->assertSee('wb-picker-results--compact', false);
        $response->assertSee('wb-gallery-picker-dialog', false);
        $response->assertSee('wb-gallery-picker-filters-sticky', false);
        $response->assertDontSee('wb-gallery-picker-layout', false);
        $response->assertDontSee('wb-gallery-picker-results-region', false);
        $response->assertDontSee('data-wb-picker-results-region', false);
        $response->assertDontSee('Upload to Library');
        $response->assertDontSee('data-wb-picker-summary', false);
        $response->assertDontSee('data-wb-picker-preview-grid', false);
        $response->assertDontSee('assets selected');
        $response->assertSee('data-wb-gallery-items-table', false);
        $response->assertSee('data-wb-gallery-item-row', false);

        $html = $response->getContent();
        $this->assertNotFalse($html);
        $this->assertMatchesRegularExpression('/id="wb-overlay-root" class="wb-overlay-root">.*id="gallery_media_ids_picker_panel".*Add Selected/s', $html);
        $this->assertStringNotContainsString('data-wb-picker-upload-submit', $html);
        $this->assertStringNotContainsString('data-wb-picker-preview data-wb-picker-preview-id=', $html);

        $xpath = $this->htmlXPath($html);
        $modalBody = $xpath->query('//*[@id="gallery_media_ids_picker_panel"]//*[contains(concat(" ", normalize-space(@class), " "), " wb-modal-body ")]')->item(0);
        $modalFooter = $xpath->query('//*[@id="gallery_media_ids_picker_panel"]//*[contains(concat(" ", normalize-space(@class), " "), " wb-modal-footer ")]')->item(0);
        $dialog = $xpath->query('//*[@id="gallery_media_ids_picker_panel"]//*[contains(concat(" ", normalize-space(@class), " "), " wb-gallery-picker-dialog ")]')->item(0);
        $filtersCard = $xpath->query('//*[@data-wb-picker-filters-card]')->item(0);
        $pickerGrid = $xpath->query('//*[@data-wb-picker-grid]')->item(0);

        $this->assertNotNull($dialog);
        $this->assertNotNull($modalBody);
        $this->assertNotNull($modalFooter);
        $this->assertNotNull($filtersCard);
        $this->assertNotNull($pickerGrid);
        $this->assertSame('wb-modal-dialog wb-gallery-picker-dialog', $dialog->getAttribute('class'));
        $this->assertSame('wb-card wb-card-muted wb-gallery-picker-filters-sticky', $filtersCard->getAttribute('class'));
        $this->assertSame(0, $xpath->query('.//*[@data-wb-picker-results-region]', $modalBody)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-wb-picker-filters-card]', $modalBody)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-wb-picker-grid]', $modalBody)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-wb-picker-empty]', $modalBody)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-wb-picker-error]', $modalBody)->length);
        $this->assertSame(0, $xpath->query('.//*[@data-wb-picker-filters-card]', $modalFooter)->length);
        $this->assertSame(0, $xpath->query('.//*[@data-wb-picker-grid]', $modalFooter)->length);
        $this->assertSame(1, $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " wb-modal-header ")]', $dialog)->length);
        $this->assertSame(1, $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " wb-modal-body ")]', $dialog)->length);
        $this->assertSame(1, $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " wb-modal-footer ")]', $dialog)->length);
        $this->assertMatchesRegularExpression('/<div class="wb-modal-dialog wb-gallery-picker-dialog">\s*<div class="wb-modal-header">.*<div class="wb-modal-body wb-stack wb-gap-3">.*<div class="wb-modal-footer wb-flex wb-justify-between wb-gap-2">/s', $html);
    }

    #[Test]
    public function gallery_block_stores_ordered_items_shared_settings_and_per_item_locale_copy(): void
    {
        $this->seedFoundation();
        $user = $this->adminUser();

        $page = $this->page();
        $first = $this->media('image', 'one.jpg', 'image/jpeg', 'media/images/one.jpg');
        $second = $this->media('image', 'two.jpg', 'image/jpeg', 'media/images/two.jpg');

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'block_type_id' => $this->blockTypeId('gallery'),
            'sort_order' => 0,
            'gallery_variant' => 'masonry',
            'gallery_columns' => '4',
            'gallery_gap' => 'lg',
            'gallery_aspect_ratio' => '16:9',
            'gallery_captions_mode' => 'below',
            'gallery_overlay_mode' => 'gradient',
            'gallery_lightbox_enabled' => '1',
            'gallery_items' => [
                [
                    'media_id' => $second->id,
                    'sort_order' => 0,
                    'alt_text' => 'Second translated alt',
                    'caption' => 'Second caption',
                    'overlay_title' => 'Second overlay',
                    'overlay_text' => 'Second overlay text',
                ],
                [
                    'media_id' => $first->id,
                    'sort_order' => 1,
                    'alt_text' => 'First translated alt',
                    'caption' => 'First caption',
                    'overlay_title' => 'First overlay',
                    'overlay_text' => 'First overlay text',
                ],
            ],
            'status' => 'published',
        ]);

        $response->assertSessionDoesntHaveErrors();

        $block = Block::query()->where('type', 'gallery')->firstOrFail();

        $this->assertSame([$second->id, $first->id], $block->fresh()->galleryMediaIds());
        $this->assertSame('masonry', $block->galleryVariant());
        $this->assertSame('4', $block->galleryColumns());
        $this->assertSame('lg', $block->galleryGap());
        $this->assertTrue($block->galleryLightboxEnabled());

        $secondRow = BlockMedia::query()->where('block_id', $block->id)->where('media_id', $second->id)->where('role', 'gallery_item')->firstOrFail();

        $this->assertDatabaseHas('block_gallery_item_translations', [
            'block_media_id' => $secondRow->id,
            'locale_id' => Page::defaultLocaleId(),
            'alt_text' => 'Second translated alt',
            'caption' => 'Second caption',
            'overlay_title' => 'Second overlay',
            'overlay_text' => 'Second overlay text',
        ]);
    }

    #[Test]
    public function gallery_block_accepts_legacy_masonary_value_but_saves_canonical_masonry_variant(): void
    {
        $this->seedFoundation();
        $user = $this->adminUser();

        $page = $this->page();
        $image = $this->media('image', 'legacy-masonary.jpg', 'image/jpeg', 'media/images/legacy-masonary.jpg');

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'block_type_id' => $this->blockTypeId('gallery'),
            'sort_order' => 0,
            'gallery_variant' => 'masonary',
            'gallery_items' => [
                [
                    'media_id' => $image->id,
                    'sort_order' => 0,
                ],
            ],
            'status' => 'published',
        ]);

        $response->assertSessionDoesntHaveErrors();

        $block = Block::query()->where('type', 'gallery')->latest('id')->firstOrFail();

        $this->assertSame('masonry', $block->galleryVariant());
        $this->assertSame('masonry', $block->setting('variant'));
    }

    #[Test]
    public function download_file_video_and_audio_blocks_store_their_shared_sources_safely(): void
    {
        $this->seedFoundation();
        $user = $this->adminUser();

        $page = $this->page();
        $document = $this->media('document', 'guide.pdf', 'application/pdf', 'media/documents/guide.pdf');
        $video = $this->media('video', 'intro.mp4', 'video/mp4', 'media/videos/intro.mp4');
        $audio = $this->media('other', 'briefing.mp3', 'audio/mpeg', 'media/audio/briefing.mp3');

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'block_type_id' => $this->blockTypeId('download'),
            'sort_order' => 0,
            'title' => 'Download guide',
            'subtitle' => 'PDF version',
            'asset_id' => $document->id,
            'variant' => 'ghost',
            'status' => 'published',
        ])->assertSessionDoesntHaveErrors();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'block_type_id' => $this->blockTypeId('file'),
            'sort_order' => 1,
            'title' => 'Open matrix',
            'content' => 'Comparison sheet',
            'asset_id' => $document->id,
            'url' => 'https://example.com/fallback.csv',
            'status' => 'published',
        ])->assertSessionDoesntHaveErrors();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'block_type_id' => $this->blockTypeId('video'),
            'sort_order' => 2,
            'title' => 'Watch walkthrough',
            'content' => 'Hosted file first',
            'asset_id' => $video->id,
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'status' => 'published',
        ])->assertSessionDoesntHaveErrors();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'block_type_id' => $this->blockTypeId('audio'),
            'sort_order' => 3,
            'title' => 'Listen briefing',
            'content' => 'Audio summary',
            'asset_id' => $audio->id,
            'url' => 'https://example.com/audio.mp3',
            'status' => 'published',
        ])->assertSessionDoesntHaveErrors();

        $download = Block::query()->where('type', 'download')->firstOrFail();
        $file = Block::query()->where('type', 'file')->firstOrFail();
        $videoBlock = Block::query()->where('type', 'video')->firstOrFail();
        $audioBlock = Block::query()->where('type', 'audio')->firstOrFail();

        $this->assertSame($document->id, $download->media_id);
        $this->assertSame('ghost', $download->variant);
        $this->assertSame($document->id, $file->media_id);
        $this->assertSame('https://example.com/fallback.csv', $file->url);
        $this->assertSame($video->id, $videoBlock->media_id);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $videoBlock->url);
        $this->assertSame($audio->id, $audioBlock->media_id);
        $this->assertSame('https://example.com/audio.mp3', $audioBlock->url);
    }

    #[Test]
    public function gallery_items_keep_their_block_media_order_positions(): void
    {
        $this->seedFoundation();
        $user = $this->adminUser();

        $page = $this->page();
        $first = $this->media('image', 'gallery-a.jpg', 'image/jpeg', 'media/images/gallery-a.jpg');
        $second = $this->media('image', 'gallery-b.jpg', 'image/jpeg', 'media/images/gallery-b.jpg');

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'block_type_id' => $this->blockTypeId('gallery'),
            'sort_order' => 0,
            'gallery_media_ids' => [$first->id, $second->id],
            'status' => 'published',
        ])->assertSessionDoesntHaveErrors();

        $block = Block::query()->where('type', 'gallery')->firstOrFail();

        $positions = BlockMedia::query()
            ->where('block_id', $block->id)
            ->where('role', 'gallery_item')
            ->orderBy('position')
            ->pluck('media_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame([$first->id, $second->id], $positions);
    }

    #[Test]
    public function gallery_locale_only_item_metadata_updates_do_not_overwrite_shared_media_order(): void
    {
        $this->seedFoundation();
        $user = $this->adminUser();
        $page = $this->page();
        $first = $this->media('image', 'gallery-locale-a.jpg', 'image/jpeg', 'media/images/gallery-locale-a.jpg');
        $second = $this->media('image', 'gallery-locale-b.jpg', 'image/jpeg', 'media/images/gallery-locale-b.jpg');

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'block_type_id' => $this->blockTypeId('gallery'),
            'sort_order' => 0,
            'gallery_items' => [
                ['media_id' => $first->id, 'sort_order' => 0, 'alt_text' => 'Default alt a'],
                ['media_id' => $second->id, 'sort_order' => 1, 'alt_text' => 'Default alt b'],
            ],
            'status' => 'published',
        ])->assertSessionDoesntHaveErrors();

        $block = Block::query()->where('type', 'gallery')->firstOrFail();

        Locale::query()->updateOrCreate(
            ['code' => 'de'],
            ['name' => 'German', 'native_name' => 'Deutsch', 'is_enabled' => true, 'is_default' => false],
        );
        $page->site->locales()->syncWithoutDetaching([
            Locale::query()->where('code', 'de')->value('id') => ['is_enabled' => true],
        ]);

        $this->actingAs($user)->put(route('admin.blocks.update', $block), [
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'block_type_id' => $this->blockTypeId('gallery'),
            'sort_order' => 0,
            'locale' => 'de',
            'gallery_items' => [
                ['media_id' => $first->id, 'sort_order' => 0, 'alt_text' => 'Deutsch alt a'],
                ['media_id' => $second->id, 'sort_order' => 1, 'alt_text' => 'Deutsch alt b'],
            ],
            'status' => 'published',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame([$first->id, $second->id], $block->fresh()->galleryMediaIds());
        $this->assertDatabaseHas('block_gallery_item_translations', [
            'block_media_id' => $block->fresh()->galleryItems()->firstWhere('media_id', $first->id)->id,
            'alt_text' => 'Deutsch alt a',
        ]);
    }

    #[Test]
    public function gallery_existing_item_updates_persist_locale_metadata_and_reopen_with_saved_values(): void
    {
        $this->seedFoundation();
        $user = $this->adminUser();

        $page = $this->page();
        $image = $this->media('image', 'persisted-caption.jpg', 'image/jpeg', 'media/images/persisted-caption.jpg');

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'block_type_id' => $this->blockTypeId('gallery'),
            'sort_order' => 0,
            'gallery_items' => [
                [
                    'media_id' => $image->id,
                    'sort_order' => 0,
                    'alt_text' => 'Original alt',
                    'caption' => 'Original caption',
                    'overlay_title' => 'Original overlay title',
                    'overlay_text' => 'Original overlay text',
                ],
            ],
            'status' => 'published',
        ])->assertSessionDoesntHaveErrors();

        $block = Block::query()->where('type', 'gallery')->firstOrFail();

        $this->actingAs($user)->put(route('admin.blocks.update', $block), [
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'block_type_id' => $this->blockTypeId('gallery'),
            'sort_order' => 0,
            'gallery_items' => [
                [
                    'media_id' => $image->id,
                    'sort_order' => 0,
                    'alt_text' => 'Updated alt',
                    'caption' => 'Updated caption from modal',
                    'overlay_title' => 'Updated overlay title',
                    'overlay_text' => 'Updated overlay text',
                ],
            ],
            'status' => 'published',
        ])->assertSessionDoesntHaveErrors();

        $blockMedia = $block->fresh()->galleryItems()->firstWhere('media_id', $image->id);

        $this->assertNotNull($blockMedia);
        $this->assertDatabaseHas('block_gallery_item_translations', [
            'block_media_id' => $blockMedia->id,
            'locale_id' => Page::defaultLocaleId(),
            'alt_text' => 'Updated alt',
            'caption' => 'Updated caption from modal',
            'overlay_title' => 'Updated overlay title',
            'overlay_text' => 'Updated overlay text',
        ]);

        $editResponse = $this->actingAs($user)->followingRedirects()->get(route('admin.blocks.edit', $block));

        $editResponse->assertOk();
        $editResponse->assertSee('name="gallery_items[0][caption]" value="Updated caption from modal"', false);
        $editResponse->assertSee('name="gallery_items[0][alt_text]" value="Updated alt"', false);
        $editResponse->assertSee('name="gallery_items[0][overlay_title]" value="Updated overlay title"', false);
        $editResponse->assertSee('name="gallery_items[0][overlay_text]" value="Updated overlay text"', false);
        $editResponse->assertSee('data-wb-gallery-caption-summary', false);
        $editResponse->assertSee('Updated caption from modal', false);
        $editResponse->assertSee('id="gallery-item-modal-gallery-'.$image->id.'"', false);
        $editResponse->assertSee('value="Updated caption from modal" data-wb-gallery-modal-field="caption"', false);
    }
}
