<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockMedia;
use App\Models\BlockType;
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
        foreach (['image', 'gallery', 'download', 'file', 'video', 'audio'] as $slug) {
            $this->assertFileExists(resource_path('views/admin/blocks/types/'.$slug.'.blade.php'));
            $this->assertFileExists(resource_path('views/pages/partials/blocks/'.$slug.'.blade.php'));
        }
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
    public function gallery_block_round_trips_translated_copy_and_ordered_block_media(): void
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
            'title' => 'Release gallery',
            'subtitle' => 'Selected views',
            'gallery_media_ids' => [$second->id, $first->id],
            'status' => 'published',
        ]);

        $response->assertSessionDoesntHaveErrors();

        $block = Block::query()->where('type', 'gallery')->firstOrFail();

        $this->assertNull($block->fresh()->getRawOriginal('title'));
        $this->assertNull($block->fresh()->getRawOriginal('subtitle'));
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $block->id,
            'title' => 'Release gallery',
            'subtitle' => 'Selected views',
        ]);
        $this->assertSame([$second->id, $first->id], $block->fresh()->galleryMediaIds());
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
}
