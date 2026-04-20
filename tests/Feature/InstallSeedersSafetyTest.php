<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\NavigationItem;
use App\Models\Page;
use Database\Seeders\CoreCatalogSeeder;
use Database\Seeders\ShowcaseInstallSeeder;
use Database\Seeders\StarterInstallSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class InstallSeedersSafetyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function starter_install_seeder_refuses_to_run_on_an_initialized_site(): void
    {
        $this->seed(CoreCatalogSeeder::class);

        $page = Page::query()->create([
            'title' => 'Existing Home',
            'slug' => 'home',
            'status' => 'published',
        ]);

        NavigationItem::query()->create([
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'parent_id' => null,
            'page_id' => $page->id,
            'title' => 'Existing Home',
            'link_type' => NavigationItem::LINK_PAGE,
            'url' => null,
            'target' => null,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
            'is_system' => false,
            'position' => 1,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('StarterInstallSeeder can only be run on a fresh install');

        $this->seed(StarterInstallSeeder::class);
    }

    #[Test]
    public function showcase_install_seeder_refuses_to_run_on_an_initialized_site(): void
    {
        $this->seed(CoreCatalogSeeder::class);

        $page = Page::query()->create([
            'title' => 'Existing Services',
            'slug' => 'services',
            'status' => 'published',
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => null,
            'type' => 'rich-text',
            'block_type_id' => null,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => null,
            'sort_order' => 0,
            'title' => 'Existing Block',
            'subtitle' => null,
            'content' => 'Keep this content',
            'url' => null,
            'asset_id' => null,
            'variant' => null,
            'meta' => null,
            'settings' => null,
            'status' => 'published',
            'is_system' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ShowcaseInstallSeeder can only be run on a fresh install');

        $this->seed(ShowcaseInstallSeeder::class);
    }
}
