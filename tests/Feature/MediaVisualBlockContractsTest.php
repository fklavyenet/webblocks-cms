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
}
