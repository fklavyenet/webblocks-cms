<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Database\Seeders\PageLayoutSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageLayoutSlot;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\Pages\PageLayoutManager;
use WebBlocks\Cms\Support\System\SystemSettings;

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
      ['site_id' => $this->site()->id, 'name' => 'Docs Page', 'slug' => $slug, 'path' => '/'.$slug],
    );

    return $page;
  }

  #[Test]
  public function page_layout_seed_is_idempotent_and_creates_default_and_docs(): void
  {
    $this->seedFoundation();
    $this->seed(PageLayoutSeeder::class);

    $this->assertSame(2, PageLayout::query()->count());
    $this->assertDatabaseHas('wbcms_page_layouts', [
      'handle' => 'default',
      'name' => 'Default Layout',
      'is_system' => true,
      'body_class' => 'layout-default',
      'shell_type' => 'default',
    ]);
    $this->assertDatabaseHas('wbcms_page_layouts', [
      'handle' => 'docs',
      'name' => 'Docs Layout',
      'is_system' => true,
      'body_class' => 'layout-docs',
      'shell_type' => 'docs',
    ]);
    $this->assertSame(8, PageLayoutSlot::query()->count());
    $this->assertDatabaseHas('wbcms_page_layout_slots', [
      'slot_name' => 'header',
      'html_element' => 'header',
    ]);
    $this->assertDatabaseHas('wbcms_page_layout_slots', [
      'slot_name' => 'sidebar',
      'html_id' => 'docsSidebar',
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
  public function page_layouts_index_uses_configured_admin_listing_rows_per_page(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    SystemSetting::query()->updateOrCreate(
      ['key' => SystemSettings::ADMIN_LISTING_PER_PAGE],
      ['value' => '12'],
    );

    foreach (range(1, 11) as $index) {
      PageLayout::query()->create([
        'name' => 'Configured Layout '.$index,
        'handle' => 'configured-layout-'.$index,
        'description' => 'Configured layout '.$index,
        'is_active' => true,
        'sort_order' => 100 + $index,
        'body_class' => 'configured-layout-'.$index,
      ]);
    }

    $response = $this->actingAs($user)->get(route('admin.page-layouts.index'));

    $response->assertOk();
    $response->assertSee('aria-current="page">1</span>', false);
    $response->assertSee(e(route('admin.page-layouts.index', ['page' => 2])), false);
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
        'is_active' => '1',
        'sort_order' => '20',
        'body_class' => 'layout-kb docs-surface',
      ]);

    $layout = PageLayout::query()->where('handle', 'knowledge-base')->firstOrFail();

    $createResponse->assertRedirect(route('admin.page-layouts.edit', $layout));
    $this->assertSame('layout-kb docs-surface', $layout->body_class);

    $updateResponse = $this->actingAs($user)
      ->put(route('admin.page-layouts.update', $layout), [
        'name' => 'Knowledge Base Layout V2',
        'handle' => 'knowledge-base-v2',
        'description' => 'Updated docs-like pages.',
        'is_active' => '0',
        'sort_order' => '30',
        'body_class' => 'layout-kb-v2',
      ]);

    $layout->refresh();

    $updateResponse->assertRedirect(route('admin.page-layouts.edit', $layout));
    $this->assertSame('knowledge-base-v2', $layout->handle);
    $this->assertSame('layout-kb-v2', $layout->body_class);
    $this->assertFalse($layout->is_active);
  }

  #[Test]
  public function system_layout_handle_remains_protected(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $layout = PageLayout::query()->where('handle', 'docs')->firstOrFail();

    $this->actingAs($user)
      ->put(route('admin.page-layouts.update', $layout), [
        'name' => 'Docs Layout Renamed',
        'handle' => 'docs-renamed',
        'description' => 'Updated description.',
        'is_active' => '0',
        'sort_order' => '5',
        'body_class' => 'layout-docs custom-docs',
      ])
      ->assertRedirect(route('admin.page-layouts.edit', $layout));

    $layout->refresh();

    $this->assertSame('docs', $layout->handle);
    $this->assertSame('docs', $layout->shell_type);
    $this->assertSame('Docs Layout Renamed', $layout->name);
    $this->assertSame('layout-docs custom-docs', $layout->body_class);
    $this->assertFalse($layout->is_active);
  }

  #[Test]
  public function page_layout_edit_form_hides_technical_json_and_shell_fields_and_shows_managed_fields(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $layout = PageLayout::query()->where('handle', 'docs')->firstOrFail();

    $this->actingAs($user)
      ->get(route('admin.page-layouts.edit', $layout))
      ->assertOk()
      ->assertSee('Layout Settings')
      ->assertSee('Name')
      ->assertSee('Handle')
      ->assertSee('Description')
      ->assertSee('Status')
      ->assertSee('Sort Order')
      ->assertSee('Body Class')
      ->assertSee('Added to the public')
      ->assertSee('layout-default')
      ->assertSee('layout-docs')
      ->assertSee('body.layout-docs')
      ->assertSee('Page Layout Slots')
      ->assertSee('Page Layout Slots define the wrapper for each page region.')
      ->assertSee('Blocks render inside these wrappers.')
      ->assertSee('Use Body Class plus slot ID and classes for layout-specific CSS.')
      ->assertDontSee('Shell Type')
      ->assertDontSee('Slot Schema JSON')
      ->assertDontSee('Wrapper Schema JSON');
  }

  #[Test]
  public function page_layout_slot_form_groups_fields_and_shows_advanced_helper_text(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $layout = PageLayout::query()->where('handle', 'docs')->firstOrFail();
    $slot = $layout->layoutSlots()->orderBy('sort_order')->firstOrFail();

    $this->actingAs($user)
      ->get(route('admin.page-layouts.slots.create', $layout))
      ->assertOk()
      ->assertSee('Add Page Layout Slot: Docs Layout')
      ->assertSee('Slot Identity')
      ->assertSee('Wrapper Markup')
      ->assertSee('Advanced Trusted Layout HTML')
      ->assertSee('Status / Ordering')
      ->assertSee('Contains the before, start, end, and after slot HTML fields')
      ->assertSee('wb-icon-chevron-down', false)
      ->assertSee('Before Slot HTML')
      ->assertSee('Slot Start HTML')
      ->assertSee('Slot End HTML')
      ->assertSee('After Slot HTML')
      ->assertSee('Before Slot HTML renders before the slot wrapper.')
      ->assertSee('Slot Start HTML renders inside the wrapper before blocks.')
      ->assertSee('Slot End HTML renders inside the wrapper after blocks.')
      ->assertSee('After Slot HTML renders after the slot wrapper.')
      ->assertSee('Scripts and unsafe JavaScript are not allowed.')
      ->assertSee('CSS classes must be separated with spaces.')
      ->assertSee('prefer a Navbar block')
      ->assertSee('wb-navbar')
      ->assertSee('wb-sidebar')
      ->assertSee('wb-dashboard-main')
      ->assertSee('wb-stack');

    $this->actingAs($user)
      ->get(route('admin.page-layouts.slots.edit', [$layout, $slot]))
      ->assertOk()
      ->assertSee('Edit Page Layout Slot: Docs Layout')
      ->assertSee('<code>'.$slot->slot_name.'</code>', false)
      ->assertSee('Slot Identity')
      ->assertSee('Wrapper Markup')
      ->assertSee('Advanced Trusted Layout HTML')
      ->assertSee('Status / Ordering')
      ->assertSee('Active');
  }

  #[Test]
  public function page_layout_slot_can_be_added_and_validates_safe_fields(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $layout = PageLayout::query()->create([
      'name' => 'Custom Layout',
      'handle' => 'custom-layout',
      'description' => 'Custom',
      'is_active' => true,
      'sort_order' => 50,
      'body_class' => 'layout-custom',
      'shell_type' => 'default',
    ]);
    $slotType = $this->slotType('promo', 'Promo', 50);

    $this->actingAs($user)
      ->post(route('admin.page-layouts.slots.store', $layout), [
        'slot_type_id' => $slotType->id,
        'slot_name' => 'promo',
        'label' => 'Promo',
        'html_element' => 'section',
        'html_id' => 'promo-slot',
        'html_classes' => 'promo-shell wb-sticky',
        'is_required' => '0',
        'is_active' => '1',
        'sort_order' => '10',
      ])
      ->assertRedirect(route('admin.page-layouts.edit', $layout));

    $this->assertDatabaseHas('wbcms_page_layout_slots', [
      'page_layout_id' => $layout->id,
      'slot_name' => 'promo',
      'html_element' => 'section',
      'html_id' => 'promo-slot',
      'html_classes' => 'promo-shell wb-sticky',
    ]);

    $invalid = $this->actingAs($user)
      ->from(route('admin.page-layouts.slots.create', $layout))
      ->post(route('admin.page-layouts.slots.store', $layout), [
        'slot_type_id' => $slotType->id,
        'slot_name' => 'promo-unsafe',
        'html_element' => 'section',
        'html_id' => 'bad id',
        'html_classes' => 'safe bad@class',
        'before_html' => '<script>alert(1)</script>',
        'is_required' => '0',
        'is_active' => '1',
        'sort_order' => '20',
      ]);

    $invalid->assertRedirect(route('admin.page-layouts.slots.create', $layout));
    $invalid->assertSessionHasErrors(['html_id', 'html_classes', 'before_html']);
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
