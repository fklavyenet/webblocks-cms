<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\PageLayout;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\SharedSlot;
use App\Models\Site;
use App\Models\SlotType;
use App\Models\User;
use App\Support\Pages\PageLayoutManager;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Database\Seeders\PageLayoutSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageLayoutManagementTest extends TestCase
{
    use RefreshDatabase;

    private function seedFoundation(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(PageLayoutSeeder::class);
    }

    private function site(): Site
    {
        return Site::query()->where('is_primary', true)->firstOrFail();
    }

    private function slotType(string $slug, string $name, int $sortOrder): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => true],
        );
    }

    private function pageWithShell(string $shell, string $slug = 'docs-page'): Page
    {
        $page = Page::query()->create([
            'site_id' => $this->site()->id,
            'title' => 'Docs Page',
            'slug' => $slug,
            'status' => Page::STATUS_PUBLISHED,
            'settings' => ['public_shell' => $shell],
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $this->site()->id, 'name' => 'Docs Page', 'slug' => $slug, 'path' => '/p/'.$slug],
        );

        return $page;
    }

    #[Test]
    public function page_layout_seed_is_idempotent_and_creates_default_and_docs(): void
    {
        $this->seedFoundation();
        $this->seed(PageLayoutSeeder::class);

        $this->assertSame(2, PageLayout::query()->count());
        $this->assertDatabaseHas('page_layouts', [
            'handle' => 'default',
            'name' => 'Default Layout',
            'is_system' => true,
            'shell_type' => 'default',
        ]);
        $this->assertDatabaseHas('page_layouts', [
            'handle' => 'docs',
            'name' => 'Docs Layout',
            'is_system' => true,
            'shell_type' => 'docs',
        ]);
    }

    #[Test]
    public function system_navigation_shows_page_layouts_after_locales_for_super_admin(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $content = $response->getContent();
        $localesHref = 'href="'.route('admin.locales.index').'"';
        $pageLayoutsHref = 'href="'.route('admin.page-layouts.index').'"';
        $slotTypesHref = 'href="'.route('admin.slot-types.index').'"';

        $response->assertOk();
        $response->assertSee($pageLayoutsHref, false);
        $this->assertTrue(
            strpos($content, $localesHref) < strpos($content, $pageLayoutsHref)
            && strpos($content, $pageLayoutsHref) < strpos($content, $slotTypesHref)
        );
    }

    #[Test]
    public function non_super_admin_access_to_page_layout_management_is_blocked(): void
    {
        $this->seedFoundation();

        $user = User::factory()->siteAdmin()->create();

        $this->actingAs($user)
            ->get(route('admin.page-layouts.index'))
            ->assertForbidden();
    }

    #[Test]
    public function page_layouts_index_renders_seeded_layouts(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get(route('admin.page-layouts.index'))
            ->assertOk()
            ->assertSee('Default Layout')
            ->assertSee('Docs Layout')
            ->assertSee('<code>default</code>', false)
            ->assertSee('<code>docs</code>', false);
    }

    #[Test]
    public function super_admin_can_create_and_edit_custom_page_layouts(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();

        $createResponse = $this->actingAs($user)
            ->post(route('admin.page-layouts.store'), [
                'name' => 'Knowledge Base Layout',
                'handle' => 'knowledge-base',
                'description' => 'Docs-like knowledge base pages.',
                'shell_type' => 'docs',
                'is_active' => '1',
                'sort_order' => '20',
                'slot_schema' => json_encode(['header', 'sidebar', 'main'], JSON_UNESCAPED_SLASHES),
                'wrapper_schema' => json_encode(['mode' => 'docs'], JSON_UNESCAPED_SLASHES),
            ]);

        $layout = PageLayout::query()->where('handle', 'knowledge-base')->firstOrFail();

        $createResponse->assertRedirect(route('admin.page-layouts.edit', $layout));
        $this->assertSame('docs', $layout->shell_type);

        $updateResponse = $this->actingAs($user)
            ->put(route('admin.page-layouts.update', $layout), [
                'name' => 'Knowledge Base Layout V2',
                'handle' => 'knowledge-base-v2',
                'description' => 'Updated docs-like pages.',
                'shell_type' => 'default',
                'is_active' => '0',
                'sort_order' => '30',
                'slot_schema' => json_encode(['header', 'main'], JSON_UNESCAPED_SLASHES),
                'wrapper_schema' => json_encode(['mode' => 'default'], JSON_UNESCAPED_SLASHES),
            ]);

        $layout->refresh();

        $updateResponse->assertRedirect(route('admin.page-layouts.edit', $layout));
        $this->assertSame('knowledge-base-v2', $layout->handle);
        $this->assertSame('default', $layout->shell_type);
        $this->assertFalse($layout->is_active);
    }

    #[Test]
    public function system_layout_handle_and_shell_type_cannot_be_changed(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $layout = PageLayout::query()->where('handle', 'docs')->firstOrFail();

        $this->actingAs($user)
            ->put(route('admin.page-layouts.update', $layout), [
                'name' => 'Docs Layout Renamed',
                'handle' => 'docs-renamed',
                'description' => 'Updated description.',
                'shell_type' => 'default',
                'is_active' => '0',
                'sort_order' => '5',
            ])
            ->assertRedirect(route('admin.page-layouts.edit', $layout));

        $layout->refresh();

        $this->assertSame('docs', $layout->handle);
        $this->assertSame('docs', $layout->shell_type);
        $this->assertSame('Docs Layout Renamed', $layout->name);
        $this->assertFalse($layout->is_active);
    }

    #[Test]
    public function page_edit_screen_populates_page_layout_options_from_page_layout_records(): void
    {
        $this->seedFoundation();

        PageLayout::query()->create([
            'name' => 'Knowledge Base',
            'handle' => 'knowledge-base',
            'description' => 'Custom docs-like layout.',
            'shell_type' => 'docs',
            'is_active' => true,
            'sort_order' => 5,
        ]);

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        $page = $this->pageWithShell('knowledge-base');

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('admin.pages.edit', $page))
            ->assertOk()
            ->assertSee('>Default Layout</option>', false)
            ->assertSee('>Docs Layout</option>', false)
            ->assertSee('>Knowledge Base</option>', false)
            ->assertSee('value="knowledge-base"', false);
    }

    #[Test]
    public function inactive_or_missing_current_layout_is_preserved_in_page_edit_form(): void
    {
        $this->seedFoundation();

        $inactive = PageLayout::query()->create([
            'name' => 'Archived Docs',
            'handle' => 'archived-docs',
            'description' => 'Inactive docs layout.',
            'shell_type' => 'docs',
            'is_active' => false,
            'sort_order' => 10,
        ]);

        $pageWithInactive = $this->pageWithShell($inactive->handle, 'inactive-layout-page');
        $pageWithUnknown = $this->pageWithShell('legacy-shell', 'unknown-layout-page');
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get(route('admin.pages.edit', $pageWithInactive))
            ->assertOk()
            ->assertSee('Archived Docs (inactive)');

        $this->actingAs($user)
            ->get(route('admin.pages.edit', $pageWithUnknown))
            ->assertOk()
            ->assertSee('Current Legacy Layout (legacy-shell)');
    }

    #[Test]
    public function unknown_layout_handles_fall_back_safely_to_default_shell_type(): void
    {
        $this->seedFoundation();

        $page = $this->pageWithShell('legacy-shell');

        $this->assertSame('legacy-shell', $page->publicShellPreset());
        $this->assertSame('default', $page->resolvedPublicShellType());
    }

    #[Test]
    public function shared_slot_compatibility_stays_exact_for_custom_layout_handles(): void
    {
        $this->seedFoundation();

        PageLayout::query()->create([
            'name' => 'Custom Docs',
            'handle' => 'custom-docs',
            'description' => 'Custom docs layout.',
            'shell_type' => 'docs',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $page = $this->pageWithShell('custom-docs');
        $sharedSlot = SharedSlot::query()->create([
            'site_id' => $this->site()->id,
            'name' => 'Docs Header',
            'handle' => 'docs-header',
            'slot_name' => 'header',
            'public_shell' => 'docs',
            'is_active' => true,
        ]);

        $this->assertSame(['public_shell'], $sharedSlot->compatibilityIssuesFor($page, 'header'));
    }

    #[Test]
    public function page_layout_manager_labels_known_handles_and_legacy_handles_safely(): void
    {
        $this->seedFoundation();

        $manager = app(PageLayoutManager::class);

        $this->assertSame('Default Layout', $manager->labelForHandle('default'));
        $this->assertSame('Legacy Layout (legacy-shell)', $manager->labelForHandle('legacy-shell'));
    }
}
