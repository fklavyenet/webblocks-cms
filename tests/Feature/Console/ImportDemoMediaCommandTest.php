<?php

namespace Tests\Feature\Console;

use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\SlotType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportDemoMediaCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_imports_curated_demo_media_and_registers_it_in_media_library(): void
    {
        Storage::fake('public');
        $this->seedUser();
        $this->seedDemoPages();
        Config::set('demo_media.items', [$this->demoItem('home-hero-01')]);

        Http::fake([
            'images.unsplash.com/*' => Http::response($this->tinyJpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        Artisan::call('demo:import-media');

        $asset = Asset::query()->where('demo_source_key', 'home-hero-01')->first();

        $this->assertNotNull($asset);
        $this->assertSame('Modern workspace desk', $asset->title);
        $this->assertSame('Modern workspace with laptop and desk', $asset->alt_text);
        $this->assertSame(Asset::KIND_IMAGE, $asset->kind);
        $this->assertDatabaseHas('asset_folders', ['name' => 'Demo Content', 'parent_id' => null]);
        $folder = AssetFolder::query()->where('name', 'Home')->first();
        $this->assertNotNull($folder);
        $this->assertSame($folder->id, $asset->folder_id);
        $this->assertTrue(Storage::disk('public')->exists('assets/demo-content/home/home-hero-01.jpg'));

        $hero = Block::query()->where('title', 'Editorial command center')->first();
        $this->assertNotNull($hero);
        $this->assertSame($asset->id, $hero->asset_id);
    }

    #[Test]
    public function it_is_idempotent_on_rerun(): void
    {
        Storage::fake('public');
        $this->seedUser();
        $this->seedDemoPages();
        Config::set('demo_media.items', [$this->demoItem('home-hero-01')]);

        Http::fake([
            'images.unsplash.com/*' => Http::response($this->tinyJpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        Artisan::call('demo:import-media');
        Artisan::call('demo:import-media');

        $this->assertSame(1, Asset::query()->where('demo_source_key', 'home-hero-01')->count());

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_handles_download_failures_without_creating_assets(): void
    {
        Storage::fake('public');
        $this->seedUser();
        $this->seedDemoPages();
        Config::set('demo_media.items', [$this->demoItem('home-hero-01')]);

        Http::fake([
            'images.unsplash.com/*' => Http::response('unavailable', 503),
        ]);

        $exitCode = Artisan::call('demo:import-media');

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseMissing('assets', ['demo_source_key' => 'home-hero-01']);
        $this->assertFalse(Storage::disk('public')->exists('assets/demo-content/home/home-hero-01.jpg'));
    }

    private function seedUser(): User
    {
        return User::factory()->create([
            'email' => 'admin@example.com',
        ]);
    }

    private function seedDemoPages(): void
    {
        $slotType = SlotType::query()->firstOrCreate(
            ['slug' => 'main'],
            [
                'name' => 'Main',
                'status' => 'published',
                'sort_order' => 1,
                'is_system' => true,
            ]
        );

        $imageType = BlockType::query()->firstOrCreate(
            ['slug' => 'image'],
            [
                'name' => 'Image',
                'source_type' => 'static',
                'status' => 'published',
                'sort_order' => 1,
                'is_system' => false,
            ]
        );

        $sliderType = BlockType::query()->firstOrCreate(
            ['slug' => 'slider'],
            [
                'name' => 'Slider',
                'source_type' => 'static',
                'status' => 'published',
                'sort_order' => 2,
                'is_system' => false,
            ]
        );

        $productCardType = BlockType::query()->firstOrCreate(
            ['slug' => 'product-card'],
            [
                'name' => 'Product Card',
                'source_type' => 'static',
                'status' => 'published',
                'sort_order' => 3,
                'is_system' => false,
            ]
        );

        $galleryType = BlockType::query()->firstOrCreate(
            ['slug' => 'gallery'],
            [
                'name' => 'Gallery',
                'source_type' => 'static',
                'status' => 'published',
                'sort_order' => 4,
                'is_system' => false,
            ]
        );

        $pages = [
            'home' => [
                ['type' => $imageType, 'title' => 'Editorial command center'],
                ['type' => $sliderType, 'title' => 'A quick tour of the working environment'],
            ],
            'about' => [
                ['type' => $imageType, 'title' => 'Implementation workshop'],
            ],
            'services' => [
                ['type' => $productCardType, 'title' => 'Implementation Ops Sprint'],
            ],
            'service-implementation-ops' => [
                ['type' => $imageType, 'title' => 'Workshop visual'],
            ],
            'blog-launching-a-governed-content-platform' => [
                ['type' => $imageType, 'title' => 'Governance models'],
            ],
            'contact' => [],
            'case-studies' => [
                ['type' => $galleryType, 'title' => 'Selected project visuals'],
            ],
        ];

        foreach ($pages as $slug => $blocks) {
            $page = Page::query()->create([
                'title' => ucfirst(str_replace('-', ' ', $slug)),
                'slug' => $slug,
                'page_type' => 'page',
                'status' => 'published',
            ]);

            PageSlot::query()->create([
                'page_id' => $page->id,
                'slot_type_id' => $slotType->id,
                'sort_order' => 0,
            ]);

            foreach ($blocks as $index => $definition) {
                Block::query()->create([
                    'page_id' => $page->id,
                    'parent_id' => null,
                    'type' => $definition['type']->slug,
                    'block_type_id' => $definition['type']->id,
                    'source_type' => 'static',
                    'slot' => 'main',
                    'slot_type_id' => $slotType->id,
                    'sort_order' => $index,
                    'title' => $definition['title'],
                    'status' => 'published',
                    'is_system' => false,
                ]);
            }
        }
    }

    private function demoItem(string $key): array
    {
        return match ($key) {
            'home-hero-01' => [
                'key' => 'home-hero-01',
                'topic' => 'home',
                'title' => 'Modern workspace desk',
                'folder' => 'Demo Content/Home',
                'source_url' => 'https://images.unsplash.com/photo-1492724441997-5dc865305da7',
                'alt' => 'Modern workspace with laptop and desk',
            ],
        };
    }

    private function tinyJpeg(): string
    {
        return base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBAQEA8PEA8PDw8QDw8PDw8QDxAPFREWFhURFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGhAQGi0lHyUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAQMBIgACEQEDEQH/xAAXAAEBAQEAAAAAAAAAAAAAAAAAAQID/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEAMQAAAB6A//xAAXEAADAQAAAAAAAAAAAAAAAAAAAREC/9oACAEBAAEFAiP/xAAVEQEBAAAAAAAAAAAAAAAAAAABAP/aAAgBAwEBPwFH/8QAFBEBAAAAAAAAAAAAAAAAAAAAEP/aAAgBAgEBPwEf/8QAFBABAAAAAAAAAAAAAAAAAAAAEP/aAAgBAQAGPwJf/8QAFBABAAAAAAAAAAAAAAAAAAAAEP/aAAgBAQABPyFf/9k=') ?: '';
    }
}
