<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageAssetManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function super_admin_can_manage_page_assets_from_edit_page_screen(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Playground',
            'slug' => 'playground',
            'status' => Page::STATUS_DRAFT,
        ]);

        $response = $this->actingAs(User::factory()->superAdmin()->create())
            ->put(route('admin.pages.update', $page), [
                'site_id' => $site->id,
                'title' => 'Playground',
                'slug' => 'playground',
                'public_shell' => 'default',
                'page_assets' => [
                    [
                        'type' => 'css',
                        'path' => '/site/webblocksui/playground/playground.css',
                        'sort_order' => 0,
                        'is_enabled' => '1',
                    ],
                    [
                        'type' => 'js',
                        'path' => '/site/webblocksui/playground/playground.js',
                        'sort_order' => 1,
                        'is_enabled' => '1',
                        'is_defer' => '1',
                        'is_module' => '1',
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.pages.edit', $page));
        $this->assertDatabaseHas('page_assets', [
            'page_id' => $page->id,
            'type' => 'css',
            'path' => '/site/webblocksui/playground/playground.css',
        ]);
        $this->assertDatabaseHas('page_assets', [
            'page_id' => $page->id,
            'type' => 'js',
            'path' => '/site/webblocksui/playground/playground.js',
            'is_module' => true,
        ]);
    }

    #[Test]
    public function non_super_admin_cannot_manage_page_assets(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Locked',
            'slug' => 'locked',
            'status' => Page::STATUS_DRAFT,
        ]);
        $siteAdmin = User::factory()->siteAdmin()->create();
        $siteAdmin->sites()->sync([$site->id]);

        $this->actingAs($siteAdmin)
            ->from(route('admin.pages.edit', $page))
            ->put(route('admin.pages.update', $page), [
                'site_id' => $site->id,
                'title' => 'Locked',
                'slug' => 'locked',
                'public_shell' => 'default',
                'page_assets' => [[
                    'type' => 'js',
                    'path' => '/site/webblocksui/playground/playground.js',
                ]],
            ])
            ->assertRedirect(route('admin.pages.edit', $page))
            ->assertSessionHasErrors('page_assets');

        $this->assertDatabaseCount('page_assets', 0);
    }

    #[Test]
    public function invalid_page_asset_paths_are_rejected(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Invalid Paths',
            'slug' => 'invalid-paths',
            'status' => Page::STATUS_DRAFT,
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->from(route('admin.pages.edit', $page))
            ->put(route('admin.pages.update', $page), [
                'site_id' => $site->id,
                'title' => 'Invalid Paths',
                'slug' => 'invalid-paths',
                'public_shell' => 'default',
                'page_assets' => [
                    ['type' => 'js', 'path' => 'https://cdn.example.com/file.js'],
                    ['type' => 'css', 'path' => '/site/example/bad.js'],
                    ['type' => 'js', 'path' => '/site/example/file.js?version=1'],
                ],
            ])
            ->assertRedirect(route('admin.pages.edit', $page))
            ->assertSessionHasErrors([
                'page_assets.0.path',
                'page_assets.1.path',
                'page_assets.2.path',
            ]);
    }
}
