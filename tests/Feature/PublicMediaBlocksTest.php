<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\Site;
use App\Models\SlotType;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicMediaBlocksTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function video_block_renders_video_tag(): void
    {
        $page = $this->pageWithMainSlot();
        $asset = $this->asset('video', 'intro.mp4', 'video/mp4', 'media/videos/intro.mp4');

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'video',
            'block_type_id' => $this->blockType('video', 'Video', 1)->id,
            'asset_id' => $asset->id,
            'source_type' => 'asset',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Intro video',
            'content' => 'Watch the short overview.',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<video controls preload="metadata">', false);
        $response->assertSee('<source src="'.$asset->url().'">', false);
        $response->assertSee('Intro video');
    }

    #[Test]
    public function audio_block_renders_audio_tag(): void
    {
        $page = $this->pageWithMainSlot();
        $asset = $this->asset('other', 'theme.mp3', 'audio/mpeg', 'media/audio/theme.mp3');

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'audio',
            'block_type_id' => $this->blockType('audio', 'Audio', 2)->id,
            'asset_id' => $asset->id,
            'source_type' => 'asset',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Theme audio',
            'content' => 'Listen to the demo track.',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<audio controls preload="metadata">', false);
        $response->assertSee('<source src="'.$asset->url().'">', false);
        $response->assertSee('Theme audio');
    }

    #[Test]
    public function file_block_renders_download_button(): void
    {
        $page = $this->pageWithMainSlot();
        $asset = $this->asset('document', 'guide.pdf', 'application/pdf', 'media/documents/guide.pdf');

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'file',
            'block_type_id' => $this->blockType('file', 'File', 3)->id,
            'asset_id' => $asset->id,
            'source_type' => 'asset',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Release notes',
            'content' => 'Download the PDF.',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee($asset->url(), false);
        $response->assertSee('wb-btn wb-btn-secondary', false);
        $response->assertSee('Download');
        $response->assertSee('guide.pdf');
    }

    #[Test]
    public function map_block_renders_safe_link(): void
    {
        $page = $this->pageWithMainSlot();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'map',
            'block_type_id' => $this->blockType('map', 'Map', 4)->id,
            'source_type' => 'embed',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Head office',
            'content' => 'Istanbul Office',
            'url' => 'javascript:alert(1)',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('Open map');
        $response->assertSee('https://maps.google.com/?q=Istanbul%20Office', false);
        $response->assertDontSee('javascript:alert(1)', false);
    }

    #[Test]
    public function slider_not_promoted(): void
    {
        $page = $this->pageWithMainSlot();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'slider',
            'block_type_id' => $this->blockType('slider', 'Slider', 5)->id,
            'source_type' => 'asset',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Legacy slider',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('wb-slider', false);
        $response->assertSee('data-wb-slider', false);
        $response->assertDontSee('<video', false);
        $response->assertDontSee('<audio', false);
    }

    private function pageWithMainSlot(): Page
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $site = Site::query()->firstOrFail();

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
        ]);

        return $page;
    }

    private function asset(string $kind, string $filename, string $mimeType, string $path): Asset
    {
        return Asset::query()->create([
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

    private function mainSlotType(): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
    }

    private function blockType(string $slug, string $name, int $sortOrder): BlockType
    {
        return BlockType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'source_type' => 'static', 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => false],
        );
    }
}
