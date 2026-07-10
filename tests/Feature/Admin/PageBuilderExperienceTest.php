<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Database\Seeders\IconCatalogSeeder;
use Database\Seeders\PageLayoutSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Media as Asset;
use WebBlocks\Cms\Models\MediaFolder;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\Pages\PageLayoutSlotComparison;
use WebBlocks\Cms\Support\System\SystemSettings;

class PageBuilderExperienceTest extends TestCase
{
  use RefreshDatabase;

  private function seedFoundation(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $this->seed(PageLayoutSeeder::class);
    $this->seed(BlockTypeSeeder::class);
  }

  private function slotType(string $slug, string $name, int $sortOrder): SlotType
  {
    return SlotType::query()->updateOrCreate(
      ['slug' => $slug],
      ['name' => $name, 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => true],
    );
  }

  private function defaultSite(): Site
  {
    return Site::query()->where('is_primary', true)->firstOrFail();
  }

  private function defaultLocale(): Locale
  {
    return Locale::query()->where('is_default', true)->firstOrFail();
  }

  private function assertTextTranslation(Block $block, int $localeId, array $expected): void
  {
    $this->assertDatabaseHas('wbcms_block_text_translations', ['block_id' => $block->id, 'locale_id' => $localeId] + $expected);
  }

  private function pageWithSlot(SlotType $slotType, string $title = 'About', string $slug = 'about'): array
  {
    $site = $this->defaultSite();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => $title,
      'slug' => $slug,
      'status' => 'published',
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $this->defaultLocale()->id],
      ['site_id' => $site->id, 'name' => $title, 'slug' => $slug, 'path' => '/'.$slug],
    );

    $pageSlot = PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
    ]);

    return [$page, $pageSlot];
  }

  private function activeSharedSlotForPage(Page $page, string $name, string $handle, ?string $slotName = null, ?string $publicShell = null, bool $isActive = true): SharedSlot
  {
    return SharedSlot::query()->create([
      'site_id' => $page->site_id,
      'name' => $name,
      'handle' => $handle,
      'slot_name' => $slotName,
      'public_shell' => $publicShell,
      'is_active' => $isActive,
    ]);
  }

  #[Test]
  public function edit_page_renders_separate_page_settings_slots_and_translations_sections(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    $main = $this->slotType('main', 'Main', 2);
    $sidebar = $this->slotType('sidebar', 'Sidebar', 3);
    $site = $this->defaultSite();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'About',
      'slug' => 'about',
      'status' => 'draft',
    ]);

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $header->id,
      'sort_order' => 0,
    ]);

    $pageSlot = PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'sort_order' => 1,
    ]);
    $pageReturnUrl = route('admin.pages.index', ['site' => $page->site_id]);

    $editResponse = $this->actingAs($user)->get(route('admin.pages.edit', $page));
    $content = $editResponse->getContent();

    $editResponse->assertOk();
    $editResponse->assertSee('Page Management');
    $editResponse->assertSee('Overview');
    $editResponse->assertSee('Settings');
    $editResponse->assertSee('Assets');
    $editResponse->assertSee('Layout Slots');
    $editResponse->assertSee('Slots');
    $editResponse->assertSee('Translations');
    $editResponse->assertSee('Add Slot');
    $editResponse->assertDontSee('Site Context');
    $editResponse->assertDontSee('Select slot');
    $editResponse->assertDontSee('name="slot_type_id" class="wb-select"', false);
    $editResponse->assertSee('data-wb-toggle="dropdown"', false);
    $editResponse->assertSee('class="wb-dropdown-menu" id="page-slot-add-menu-'.$page->id.'"', false);
    $editResponse->assertSee('<button type="submit" class="wb-dropdown-item">Sidebar</button>', false);
    $editResponse->assertDontSee('<button type="submit" class="wb-dropdown-item">Header</button>', false);
    $editResponse->assertDontSee('<button type="submit" class="wb-dropdown-item">Main</button>', false);
    $editResponse->assertSee('No page assets yet.');
    $editResponse->assertDontSee('name="page_assets[', false);
    $editResponse->assertSee('name="slot_type_id" value="'.$sidebar->id.'"', false);
    $editResponse->assertSee('<th>Actions</th>', false);
    $editResponse->assertDontSee('<th class="wb-text-end">Actions</th>', false);
    $editResponse->assertSee('<div class="wb-action-group">', false);
    $editResponse->assertDontSee('<td class="wb-text-end">', false);
    $editResponse->assertSee('name="public_shell"', false);
    $editResponse->assertSee('href="'.route('admin.pages.slots.blocks', ['page' => $page, 'slot' => $pageSlot, 'return_url' => $pageReturnUrl]).'"', false);
    $editResponse->assertSee('action="'.route('admin.pages.update', $page).'"', false);
    $editResponse->assertSee('action="'.route('admin.pages.slots.store', $page).'"', false);
    $editResponse->assertSee('action="'.route('admin.pages.slots.move-up', [$page, $page->slots()->firstOrFail()]).'"', false);
    $editResponse->assertSee('modal=delete-page-slot', false);
    $editResponse->assertSee('slot='.$pageSlot->id, false);
    $editResponse->assertSee('aria-haspopup="dialog"', false);
    $editResponse->assertDontSee('action="'.route('admin.pages.slots.destroy', [$page, $pageSlot]).'"', false);
    $editResponse->assertDontSee('name="slots[', false);
    $this->assertNotFalse($content);
    $this->assertFalse(str_contains($content, 'data-wb-slot-builder'));
    $this->assertFalse(str_contains($content, 'Site Context'));
    $pageSettingsPosition = strpos($content, 'Page Management');
    $slotsPosition = strpos($content, '<strong>Slots</strong>');
    $translationsPosition = strpos($content, '<strong>Translations</strong>');
    $this->assertNotFalse($pageSettingsPosition);
    $this->assertNotFalse($slotsPosition);
    $this->assertNotFalse($translationsPosition);
    $this->assertTrue($pageSettingsPosition < $slotsPosition);
    $this->assertTrue($slotsPosition < $translationsPosition);
    $this->assertTrue(strpos($content, '</form>') < strpos($content, '<strong>Slots</strong>'));

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($content);
    libxml_clear_errors();

    $xpath = new DOMXPath($document);
    $pageManagementCard = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " wb-card ")][.//div[contains(concat(" ", normalize-space(@class), " "), " wb-card-header ")]/strong[normalize-space()="Page Management"]]')->item(0);

    $this->assertNotNull($pageManagementCard);
    $this->assertSame(1, $xpath->query('.//button[normalize-space()="Save Changes"]', $pageManagementCard)->length);
    $this->assertSame(1, $xpath->query('.//a[normalize-space()="Cancel"]', $pageManagementCard)->length);
    $this->assertSame(1, $xpath->query('.//div[@id="page-management-overview-panel"]//strong[normalize-space()="Overview"]', $pageManagementCard)->length);
    $this->assertSame(1, $xpath->query('.//div[@id="page-management-settings-panel"]//strong[normalize-space()="Settings"]', $pageManagementCard)->length);
    $this->assertSame(1, $xpath->query('.//div[@id="page-management-assets-panel"]//strong[normalize-space()="Page Assets"]', $pageManagementCard)->length);
    $this->assertSame(1, $xpath->query('.//div[@id="page-management-layout-slots-panel"]//strong[normalize-space()="Page Layout Slots"]', $pageManagementCard)->length);
    $this->assertSame(0, $xpath->query('.//div[@id="page-management-assets-panel"]//button[normalize-space()="Save Changes"]', $pageManagementCard)->length);
    $this->assertSame(0, $xpath->query('.//div[@id="page-management-assets-panel"]//a[normalize-space()="Cancel"]', $pageManagementCard)->length);

    $slotsCard = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " wb-card ")][.//strong[normalize-space()="Slots"]]')->item(0);
    $this->assertNotNull($slotsCard);
    $this->assertSame(0, $xpath->query('.//strong[normalize-space()="Page Layout Slots"]', $slotsCard)->length);
  }

  #[Test]
  public function create_and_edit_page_forms_do_not_render_the_site_context_field(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page] = $this->pageWithSlot($main);
    Site::query()->create([
      'name' => 'Other Site',
      'handle' => 'other-site',
      'domain' => 'other.example.test',
      'is_primary' => false,
    ]);

    $this->actingAs($user)
      ->get(route('admin.pages.create'))
      ->assertOk()
      ->assertSee('Site')
      ->assertSee('name="site_id"', false)
      ->assertDontSee('Site Context');

    $this->actingAs($user)
      ->get(route('admin.pages.edit', $page))
      ->assertOk()
      ->assertSee('Site')
      ->assertDontSee('<select id="site_id" name="site_id"', false)
      ->assertSee('type="hidden" name="site_id" value="'.$page->site_id.'"', false)
      ->assertSee('Existing pages cannot be moved between sites from this form.')
      ->assertSee('Duplicate page')
      ->assertSee('Move to another site')
      ->assertDontSee('Site Context');
  }

  #[Test]
  public function edit_page_hides_move_action_for_editors_and_site_admins_without_another_accessible_site(): void
  {
    $this->seedFoundation();

    $main = $this->slotType('main', 'Main', 1);
    [$page] = $this->pageWithSlot($main);

    $editor = User::factory()->editor()->create();
    $editor->sites()->sync([$page->site_id]);

    $siteAdmin = User::factory()->siteAdmin()->create();
    $siteAdmin->sites()->sync([$page->site_id]);

    $this->actingAs($editor)
      ->get(route('admin.pages.edit', $page))
      ->assertOk()
      ->assertSee('Duplicate page')
      ->assertDontSee('Move to another site');

    $this->actingAs($siteAdmin)
      ->get(route('admin.pages.edit', $page))
      ->assertOk()
      ->assertSee('Duplicate page')
      ->assertDontSee('Move to another site');
  }

  #[Test]
  public function edit_page_shows_duplicate_action_for_site_admin_when_another_accessible_site_exists(): void
  {
    $this->seedFoundation();

    $main = $this->slotType('main', 'Main', 1);
    [$page] = $this->pageWithSlot($main);
    $otherSite = Site::query()->create([
      'name' => 'Other Site',
      'handle' => 'other-site',
      'domain' => 'other.example.test',
      'is_primary' => false,
    ]);
    $otherSite->locales()->syncWithoutDetaching([$this->defaultLocale()->id => ['is_enabled' => true]]);

    $siteAdmin = User::factory()->siteAdmin()->create();
    $siteAdmin->sites()->sync([$page->site_id, $otherSite->id]);

    $this->actingAs($siteAdmin)
      ->get(route('admin.pages.edit', $page))
      ->assertOk()
      ->assertSee('Duplicate page')
      ->assertSee('Move to another site');
  }

  #[Test]
  public function create_page_still_allows_setting_the_site_and_edit_update_keeps_existing_site(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $otherSite = Site::query()->create([
      'name' => 'Other Site',
      'handle' => 'other-site',
      'domain' => 'other.example.test',
      'is_primary' => false,
    ]);
    $otherSite->locales()->syncWithoutDetaching([$this->defaultLocale()->id => ['is_enabled' => true]]);

    $create = $this->actingAs($user)->post(route('admin.pages.store'), [
      'site_id' => $otherSite->id,
      'title' => 'Campaign',
      'slug' => 'campaign',
      'public_shell' => 'default',
    ]);

    $page = Page::query()->whereHas('translations', fn ($query) => $query->where('slug', 'campaign'))->firstOrFail();

    $create->assertRedirect(route('admin.pages.edit', $page));
    $this->assertSame($otherSite->id, $page->site_id);
    $this->assertDatabaseHas('wbcms_page_translations', [
      'page_id' => $page->id,
      'site_id' => $otherSite->id,
      'slug' => 'campaign',
    ]);

    $update = $this->actingAs($user)->put(route('admin.pages.update', $page), [
      'site_id' => $otherSite->id,
      'title' => 'Campaign Updated',
      'slug' => 'campaign-updated',
      'public_shell' => 'docs',
    ]);

    $update->assertRedirect(route('admin.pages.edit', $page));
    $this->assertSame($otherSite->id, $page->fresh()->site_id);
    $this->assertDatabaseHas('wbcms_page_translations', [
      'page_id' => $page->id,
      'site_id' => $otherSite->id,
      'slug' => 'campaign-updated',
    ]);
  }

  #[Test]
  public function forged_existing_page_site_change_is_rejected_without_moving_the_page(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page] = $this->pageWithSlot($main);

    $otherSite = Site::query()->create([
      'name' => 'Other Site',
      'handle' => 'other-site',
      'domain' => 'other.example.test',
      'is_primary' => false,
    ]);
    $otherSite->locales()->syncWithoutDetaching([$this->defaultLocale()->id => ['is_enabled' => true]]);

    $response = $this->actingAs($user)
      ->from(route('admin.pages.edit', $page))
      ->put(route('admin.pages.update', $page), [
        'site_id' => $otherSite->id,
        'title' => 'About Updated',
        'slug' => 'about-updated',
        'public_shell' => 'default',
      ]);

    $response->assertRedirect(route('admin.pages.edit', $page));
    $response->assertSessionHasErrors('site_id');
    $response->assertSessionHasErrors([
      'site_id' => 'Existing pages cannot be moved between sites from the Edit Page screen.',
    ]);
    $this->assertSame($this->defaultSite()->id, $page->fresh()->site_id);
    $this->assertDatabaseHas('wbcms_page_translations', [
      'page_id' => $page->id,
      'site_id' => $this->defaultSite()->id,
      'slug' => 'about',
    ]);
  }

  #[Test]
  public function add_slot_dropdown_only_lists_slot_types_that_are_not_already_on_the_page(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    $main = $this->slotType('main', 'Main', 2);
    $sidebar = $this->slotType('sidebar', 'Sidebar', 3);
    $footer = $this->slotType('footer', 'Footer', 4);
    [$page] = $this->pageWithSlot($header);

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'sort_order' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('admin.pages.edit', $page));

    $response->assertOk();
    $response->assertSee('<button type="submit" class="wb-dropdown-item">Sidebar</button>', false);
    $response->assertSee('<button type="submit" class="wb-dropdown-item">Footer</button>', false);
    $response->assertDontSee('<button type="submit" class="wb-dropdown-item">Header</button>', false);
    $response->assertDontSee('<button type="submit" class="wb-dropdown-item">Main</button>', false);
    $response->assertSee('action="'.route('admin.pages.slots.store', $page).'"', false);
    $response->assertSee('name="slot_type_id" value="'.$sidebar->id.'"', false);
    $response->assertSee('name="slot_type_id" value="'.$footer->id.'"', false);
  }

  #[Test]
  public function add_slot_dropdown_disables_when_no_slot_types_remain(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    $main = $this->slotType('main', 'Main', 2);
    $sidebar = $this->slotType('sidebar', 'Sidebar', 3);
    $footer = $this->slotType('footer', 'Footer', 4);
    [$page] = $this->pageWithSlot($header);

    foreach ([$main, $sidebar, $footer] as $index => $slotType) {
      PageSlot::query()->create([
        'page_id' => $page->id,
        'slot_type_id' => $slotType->id,
        'sort_order' => $index + 1,
      ]);
    }

    $response = $this->actingAs($user)->get(route('admin.pages.edit', $page));

    $response->assertOk();
    $response->assertSee('data-wb-toggle="dropdown"', false);
    $response->assertSee('disabled', false);
    $response->assertSee('No slots available');
  }

  #[Test]
  public function edit_page_still_loads_when_shared_slot_tables_are_not_available(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page] = $this->pageWithSlot($main);

    Schema::dropIfExists('wbcms_shared_slot_blocks');
    Schema::dropIfExists('wbcms_shared_slots');
    if (Schema::hasColumn('wbcms_page_slots', 'shared_slot_id')) {
      Schema::table('wbcms_page_slots', function ($table): void {
        $table->dropForeign(['shared_slot_id']);
        $table->dropColumn('shared_slot_id');
      });
    }
    if (Schema::hasColumn('wbcms_page_slots', 'source_type')) {
      Schema::table('wbcms_page_slots', function ($table): void {
        $table->dropColumn('source_type');
      });
    }

    $response = $this->actingAs($user)->get(route('admin.pages.edit', $page));

    $response->assertOk();
    $response->assertSee('Page Management');
    $response->assertSee('Slots');
    $response->assertDontSee('name="source_type"', false);
    $response->assertDontSee('name="shared_slot_id"', false);
  }

  #[Test]
  public function page_edit_screen_exposes_public_shell_selector_and_persists_safe_value(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page] = $this->pageWithSlot($main);

    $this->actingAs($user)
      ->get(route('admin.pages.edit', $page))
      ->assertOk()
      ->assertSee('Page Layout')
      ->assertDontSee('Public Shell')
      ->assertSee('name="public_shell"', false)
      ->assertSee('value="docs"', false)
      ->assertSee('>Default Layout</option>', false)
      ->assertSee('>Docs Layout</option>', false);

    $response = $this->actingAs($user)->put(route('admin.pages.update', $page), [
      'site_id' => $page->site_id,
      'title' => 'About',
      'slug' => 'about',
      'public_shell' => 'docs',
    ]);

    $response->assertRedirect(route('admin.pages.edit', $page));
    $this->assertSame('docs', $page->fresh()->publicShellPreset());
  }

  #[Test]
  public function page_create_uses_managed_layout_slots_as_default_slots(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $site = $this->defaultSite();
    $header = $this->slotType('header', 'Header', 1);
    $main = $this->slotType('main', 'Main', 2);
    $sidebar = $this->slotType('sidebar', 'Sidebar', 3);
    $footer = $this->slotType('footer', 'Footer', 4);

    $response = $this->actingAs($user)->post(route('admin.pages.store'), [
      'site_id' => $site->id,
      'title' => 'Managed Layout Page',
      'slug' => 'managed-layout-page',
      'public_shell' => 'docs',
    ]);

    $page = Page::query()->whereHas('translations', fn ($query) => $query->where('slug', 'managed-layout-page'))->firstOrFail();

    $response->assertRedirect(route('admin.pages.edit', $page));
    $this->assertSame(['header', 'sidebar', 'main', 'footer'], $page->slots()->with('slotType')->orderBy('sort_order')->get()->pluck('slotType.slug')->all());
    $this->assertDatabaseHas('wbcms_page_slots', ['page_id' => $page->id, 'slot_type_id' => $header->id]);
    $this->assertDatabaseHas('wbcms_page_slots', ['page_id' => $page->id, 'slot_type_id' => $main->id]);
    $this->assertDatabaseHas('wbcms_page_slots', ['page_id' => $page->id, 'slot_type_id' => $sidebar->id]);
    $this->assertDatabaseHas('wbcms_page_slots', ['page_id' => $page->id, 'slot_type_id' => $footer->id]);
  }

  #[Test]
  public function page_layout_slot_comparison_identifies_present_missing_extra_disabled_and_shared_slots(): void
  {
    $this->seedFoundation();

    $header = $this->slotType('header', 'Header', 1);
    $main = $this->slotType('main', 'Main', 2);
    $sidebar = $this->slotType('sidebar', 'Sidebar', 3);
    $footer = $this->slotType('footer', 'Footer', 4);
    $promo = $this->slotType('promo', 'Promo', 5);
    $page = Page::query()->create([
      'site_id' => $this->defaultSite()->id,
      'title' => 'Layout Compare',
      'slug' => 'layout-compare',
      'status' => 'draft',
      'settings' => ['public_shell' => 'docs'],
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $this->defaultLocale()->id],
      ['site_id' => $page->site_id, 'name' => 'Layout Compare', 'slug' => 'layout-compare', 'path' => '/layout-compare'],
    );

    $sharedSlot = SharedSlot::query()->create([
      'site_id' => $page->site_id,
      'name' => 'Docs Sidebar Shared',
      'handle' => 'docs-sidebar-shared',
      'slot_name' => 'sidebar',
      'public_shell' => 'docs',
      'is_active' => true,
    ]);

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $header->id,
      'source_type' => PageSlot::SOURCE_TYPE_PAGE,
      'sort_order' => 0,
    ]);
    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $sidebar->id,
      'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
      'shared_slot_id' => $sharedSlot->id,
      'sort_order' => 1,
    ]);
    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $promo->id,
      'source_type' => PageSlot::SOURCE_TYPE_DISABLED,
      'sort_order' => 2,
    ]);

    $comparison = app(PageLayoutSlotComparison::class)->compare($page->fresh(['slots.slotType', 'slots.sharedSlot']));

    $this->assertSame(4, $comparison['layout_slot_count']);
    $this->assertSame(3, $comparison['page_slot_count']);
    $this->assertSame(2, $comparison['present_count']);
    $this->assertSame(2, $comparison['missing_count']);
    $this->assertSame(1, $comparison['extra_count']);
    $this->assertSame(1, $comparison['disabled_count']);
    $this->assertSame(1, $comparison['shared_slot_count']);
    $this->assertSame(['main', 'footer'], $comparison['missing_slots']->pluck('layout_slot_name')->all());
    $this->assertSame(['promo'], $comparison['extra_slots']->pluck('page_slot_name')->all());
  }

  #[Test]
  public function edit_page_shows_layout_slot_summary_and_missing_slots_action_only_when_needed(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    $sidebar = $this->slotType('sidebar', 'Sidebar', 2);
    $page = Page::query()->create([
      'site_id' => $this->defaultSite()->id,
      'title' => 'Docs Draft',
      'slug' => 'docs-draft',
      'status' => 'draft',
      'settings' => ['public_shell' => 'docs'],
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $this->defaultLocale()->id],
      ['site_id' => $page->site_id, 'name' => 'Docs Draft', 'slug' => 'docs-draft', 'path' => '/docs-draft'],
    );

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $header->id,
      'sort_order' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('admin.pages.edit', $page));

    $response->assertOk();
    $response->assertSee('Page Layout Slots');
    $response->assertSee('Add Missing Layout Slots');
    $response->assertSee('Missing on this page');
    $response->assertSee('Extra Page Slots are kept for safety');

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($response->getContent());
    libxml_clear_errors();

    $xpath = new DOMXPath($document);
    $layoutSlotsPanel = $xpath->query('//div[@id="page-management-layout-slots-panel"]')->item(0);
    $slotsCard = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " wb-card ")][.//strong[normalize-space()="Slots"]]')->item(0);

    $this->assertNotNull($layoutSlotsPanel);
    $this->assertNotNull($slotsCard);
    $this->assertSame(1, $xpath->query('.//strong[normalize-space()="Page Layout Slots"]', $layoutSlotsPanel)->length);
    $this->assertSame(1, $xpath->query('.//button[normalize-space()="Add Missing Layout Slots"]', $layoutSlotsPanel)->length);
    $this->assertSame(0, $xpath->query('.//strong[normalize-space()="Page Layout Slots"]', $slotsCard)->length);

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $sidebar->id,
      'sort_order' => 1,
    ]);
    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $this->slotType('main', 'Main', 3)->id,
      'sort_order' => 2,
    ]);
    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $this->slotType('footer', 'Footer', 4)->id,
      'sort_order' => 3,
    ]);

    $completeResponse = $this->actingAs($user)->get(route('admin.pages.edit', $page->fresh()));

    $completeResponse->assertOk();
    $completeResponse->assertSee('This page already has all slots defined by the selected Page Layout.');
    $completeResponse->assertDontSee('Add Missing Layout Slots');
  }

  #[Test]
  public function sync_layout_slots_adds_only_missing_slots_and_preserves_existing_slot_state(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $site = $this->defaultSite();
    $header = $this->slotType('header', 'Header', 1);
    $sidebar = $this->slotType('sidebar', 'Sidebar', 2);
    $promo = $this->slotType('promo', 'Promo', 5);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Sync Me',
      'slug' => 'sync-me',
      'status' => 'draft',
      'settings' => ['public_shell' => 'docs'],
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $this->defaultLocale()->id],
      ['site_id' => $site->id, 'name' => 'Sync Me', 'slug' => 'sync-me', 'path' => '/sync-me'],
    );

    $sharedSlot = SharedSlot::query()->create([
      'site_id' => $site->id,
      'name' => 'Sidebar Shared',
      'handle' => 'sidebar-shared',
      'slot_name' => 'sidebar',
      'public_shell' => 'docs',
      'is_active' => true,
    ]);

    $headerSlot = PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $header->id,
      'source_type' => PageSlot::SOURCE_TYPE_DISABLED,
      'sort_order' => 0,
    ]);
    $sidebarSlot = PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $sidebar->id,
      'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
      'shared_slot_id' => $sharedSlot->id,
      'sort_order' => 1,
    ]);
    $extraSlot = PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $promo->id,
      'source_type' => PageSlot::SOURCE_TYPE_PAGE,
      'sort_order' => 2,
    ]);

    $block = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $sectionType->id,
      'type' => 'section',
      'source_type' => 'static',
      'slot' => 'promo',
      'slot_type_id' => $promo->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->post(route('admin.pages.layout-slots.sync', $page), [
      'return_url' => route('admin.pages.index', ['site' => $site->id]),
    ]);

    $response->assertRedirect(route('admin.pages.edit', ['page' => $page, 'tab' => 'layout-slots', 'return_url' => route('admin.pages.index', ['site' => $site->id])]));
    $response->assertSessionHas('status', 'Added 2 missing Page Layout slots.');
    $this->assertSame(['header', 'sidebar', 'promo', 'main', 'footer'], $page->fresh()->slots()->with('slotType')->orderBy('sort_order')->get()->pluck('slotType.slug')->all());
    $this->assertDatabaseHas('wbcms_page_slots', ['id' => $headerSlot->id, 'source_type' => PageSlot::SOURCE_TYPE_DISABLED]);
    $this->assertDatabaseHas('wbcms_page_slots', ['id' => $sidebarSlot->id, 'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT, 'shared_slot_id' => $sharedSlot->id]);
    $this->assertDatabaseHas('wbcms_page_slots', ['id' => $extraSlot->id]);
    $this->assertDatabaseHas('wbcms_blocks', ['id' => $block->id, 'page_id' => $page->id]);
  }

  #[Test]
  public function sync_layout_slots_reports_noop_when_all_layout_slots_exist(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $site = $this->defaultSite();

    $response = $this->actingAs($user)->post(route('admin.pages.store'), [
      'site_id' => $site->id,
      'title' => 'Already Synced',
      'slug' => 'already-synced',
      'public_shell' => 'docs',
    ]);

    $page = Page::query()->whereHas('translations', fn ($query) => $query->where('slug', 'already-synced'))->firstOrFail();

    $response->assertRedirect(route('admin.pages.edit', $page));

    $noopResponse = $this->actingAs($user)->post(route('admin.pages.layout-slots.sync', $page));

    $noopResponse->assertRedirect(route('admin.pages.edit', ['page' => $page, 'tab' => 'layout-slots']));
    $noopResponse->assertSessionHas('status', 'This page already has all slots defined by the selected Page Layout.');
  }

  #[Test]
  public function changing_page_layout_on_normal_save_does_not_automatically_mutate_slots(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    $main = $this->slotType('main', 'Main', 2);
    $page = Page::query()->create([
      'site_id' => $this->defaultSite()->id,
      'title' => 'Layout Switch',
      'slug' => 'layout-switch',
      'status' => 'draft',
      'settings' => ['public_shell' => 'default'],
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $this->defaultLocale()->id],
      ['site_id' => $page->site_id, 'name' => 'Layout Switch', 'slug' => 'layout-switch', 'path' => '/layout-switch'],
    );

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $header->id,
      'sort_order' => 0,
    ]);
    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'sort_order' => 1,
    ]);

    $response = $this->actingAs($user)->put(route('admin.pages.update', $page), [
      'site_id' => $page->site_id,
      'title' => 'Layout Switch',
      'slug' => 'layout-switch',
      'public_shell' => 'docs',
    ]);

    $response->assertRedirect(route('admin.pages.edit', $page));
    $this->assertSame(['header', 'main'], $page->fresh()->slots()->with('slotType')->orderBy('sort_order')->get()->pluck('slotType.slug')->all());

    $editResponse = $this->actingAs($user)->get(route('admin.pages.edit', $page->fresh()));
    $editResponse->assertOk();
    $editResponse->assertSee('Missing: 2');
    $editResponse->assertSee('Extra: 0');
    $editResponse->assertSee('Add Missing Layout Slots');
  }

  #[Test]
  public function sync_layout_slots_uses_existing_page_edit_authorization_rules(): void
  {
    $this->seedFoundation();

    $editor = User::factory()->editor()->create();
    $page = Page::query()->create([
      'site_id' => $this->defaultSite()->id,
      'title' => 'Published Docs',
      'slug' => 'published-docs',
      'status' => Page::STATUS_PUBLISHED,
      'settings' => ['public_shell' => 'docs'],
    ]);
    $editor->sites()->sync([$page->site_id]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $this->defaultLocale()->id],
      ['site_id' => $page->site_id, 'name' => 'Published Docs', 'slug' => 'published-docs', 'path' => '/published-docs'],
    );

    $this->actingAs($editor)
      ->post(route('admin.pages.layout-slots.sync', $page))
      ->assertForbidden();
  }

  #[Test]
  public function page_edit_screen_preserves_unknown_layout_handle_in_selector(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page] = $this->pageWithSlot($main);
    $page->update(['settings' => ['public_shell' => 'legacy-shell']]);

    $this->actingAs($user)
      ->get(route('admin.pages.edit', $page))
      ->assertOk()
      ->assertSee('Current Legacy Layout (legacy-shell)');
  }

  #[Test]
  public function page_edit_screen_preserves_inactive_layout_handle_in_selector(): void
  {
    $this->seedFoundation();

    $inactiveLayout = PageLayout::query()->create([
      'name' => 'Archived Docs',
      'handle' => 'archived-docs',
      'description' => 'Inactive docs layout.',
      'shell_type' => 'docs',
      'is_active' => false,
      'sort_order' => 10,
    ]);
    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page] = $this->pageWithSlot($main);
    $page->update(['settings' => ['public_shell' => $inactiveLayout->handle]]);

    $this->actingAs($user)
      ->get(route('admin.pages.edit', $page))
      ->assertOk()
      ->assertSee('Archived Docs (inactive)');
  }

  #[Test]
  public function page_settings_update_does_not_change_slots_when_slot_inputs_are_submitted(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    $main = $this->slotType('main', 'Main', 2);
    [$page] = $this->pageWithSlot($header);

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'sort_order' => 1,
    ]);

    $response = $this->actingAs($user)->put(route('admin.pages.update', $page), [
      'site_id' => $page->site_id,
      'title' => 'About Updated',
      'slug' => 'about-updated',
      'public_shell' => 'default',
      'slots' => [
        ['slot_type_id' => $main->id],
      ],
    ]);

    $response->assertRedirect(route('admin.pages.edit', $page));
    $this->assertSame('About Updated', $page->fresh()->title);
    $this->assertSame(['header', 'main'], $page->fresh()->slots()->with('slotType')->orderBy('sort_order')->get()->pluck('slotType.slug')->all());
  }

  #[Test]
  public function slot_can_be_added_with_a_dedicated_endpoint_and_duplicate_names_are_rejected(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    $main = $this->slotType('main', 'Main', 2);
    [$page] = $this->pageWithSlot($header);

    $response = $this->actingAs($user)->post(route('admin.pages.slots.store', $page), [
      'slot_type_id' => $main->id,
    ]);

    $response->assertRedirect(route('admin.pages.edit', $page));
    $this->assertDatabaseHas('wbcms_page_slots', [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'sort_order' => 1,
    ]);

    $duplicate = $this->actingAs($user)
      ->from(route('admin.pages.edit', $page))
      ->post(route('admin.pages.slots.store', $page), [
        'slot_type_id' => $main->id,
      ]);

    $duplicate->assertRedirect(route('admin.pages.edit', $page));
    $duplicate->assertSessionHasErrors('slot_type_id');
  }

  #[Test]
  public function slot_can_be_deleted_with_a_dedicated_endpoint_and_other_pages_slots_are_not_found(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    [$otherPage, $otherSlot] = $this->pageWithSlot($main, 'Docs', 'docs');

    $modalResponse = $this->actingAs($user)->get(route('admin.pages.edit', [
      'page' => $page,
      'modal' => 'delete-page-slot',
      'slot' => $pageSlot->id,
    ]));

    $modalResponse->assertOk();
    $modalResponse->assertSee('Delete Page Slot');
    $modalResponse->assertSee('class="wb-modal wb-modal-lg is-open"', false);
    $modalResponse->assertSee('action="'.route('admin.pages.slots.destroy', [$page, $pageSlot]).'"', false);
    $modalResponse->assertSee('name="confirm_delete_slot" value="1"', false);

    $response = $this->actingAs($user)->delete(route('admin.pages.slots.destroy', [$page, $pageSlot]));

    $response->assertRedirect(route('admin.pages.edit', $page));
    $response->assertSessionHasErrors('slot');
    $this->assertDatabaseHas('wbcms_page_slots', ['id' => $pageSlot->id]);

    $response = $this->actingAs($user)->delete(route('admin.pages.slots.destroy', [$page, $pageSlot]), [
      'confirm_delete_slot' => '1',
    ]);

    $response->assertRedirect(route('admin.pages.edit', $page));
    $this->assertDatabaseMissing('wbcms_page_slots', ['id' => $pageSlot->id]);

    $this->actingAs($user)
      ->delete(route('admin.pages.slots.destroy', [$otherPage, $pageSlot]))
      ->assertNotFound();

    $this->assertDatabaseHas('wbcms_page_slots', ['id' => $otherSlot->id]);
  }

  #[Test]
  public function slot_delete_is_blocked_when_the_slot_still_contains_blocks(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
    [$page, $pageSlot] = $this->pageWithSlot($main);

    Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $sectionType->id,
      'type' => 'section',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)
      ->from(route('admin.pages.edit', $page))
      ->delete(route('admin.pages.slots.destroy', [$page, $pageSlot]), [
        'confirm_delete_slot' => '1',
      ]);

    $response->assertRedirect(route('admin.pages.edit', $page));
    $response->assertSessionHasErrors('slot');
    $this->assertDatabaseHas('wbcms_page_slots', ['id' => $pageSlot->id]);
  }

  #[Test]
  public function slot_reorder_endpoints_move_slots_and_handle_edge_positions_safely(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    $main = $this->slotType('main', 'Main', 2);
    $sidebar = $this->slotType('sidebar', 'Sidebar', 3);
    [$page, $headerSlot] = $this->pageWithSlot($header);
    $mainSlot = PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'sort_order' => 1,
    ]);
    $sidebarSlot = PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $sidebar->id,
      'sort_order' => 2,
    ]);

    $moveDown = $this->actingAs($user)->post(route('admin.pages.slots.move-down', [$page, $headerSlot]));
    $moveDown->assertRedirect(route('admin.pages.edit', $page));
    $this->assertSame(['main', 'header', 'sidebar'], $page->fresh()->slots()->with('slotType')->orderBy('sort_order')->get()->pluck('slotType.slug')->all());

    $moveUp = $this->actingAs($user)->post(route('admin.pages.slots.move-up', [$page, $sidebarSlot]));
    $moveUp->assertRedirect(route('admin.pages.edit', $page));
    $this->assertSame(['main', 'sidebar', 'header'], $page->fresh()->slots()->with('slotType')->orderBy('sort_order')->get()->pluck('slotType.slug')->all());

    $edgeUp = $this->actingAs($user)->post(route('admin.pages.slots.move-up', [$page, $page->fresh()->slots()->orderBy('sort_order')->firstOrFail()]));
    $edgeUp->assertRedirect(route('admin.pages.edit', $page));
    $edgeUp->assertSessionHas('status', 'Slot is already at the edge of the page.');

    $edgeDown = $this->actingAs($user)->post(route('admin.pages.slots.move-down', [$page, $headerSlot->fresh()]));
    $edgeDown->assertRedirect(route('admin.pages.edit', $page));
    $edgeDown->assertSessionHas('status', 'Slot is already at the edge of the page.');
    $this->assertSame(['main', 'sidebar', 'header'], $page->fresh()->slots()->with('slotType')->orderBy('sort_order')->get()->pluck('slotType.slug')->all());
  }

  #[Test]
  public function slot_block_picker_lists_all_published_foundation_block_types(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_tab' => 'all']));

    $response->assertOk();
    $response->assertSee('Block Types');
    $response->assertSee('Header');
    $response->assertSee('Plain Text');
    $response->assertSee('Section');
    $response->assertSee('Container');
    $response->assertSee('Cluster');
    $response->assertSee('Grid');
    $response->assertSee('Content Header');
    $response->assertSee('Button Link');
    $response->assertSee('Card');
    $response->assertSee('Stat Card');
    $response->assertSee('Table');
    $response->assertSee('Quote');
    $response->assertSee('Alert');
    $response->assertSee('Contact Form');
    $response->assertSee('data-block-type-slug="contact_form"', false);
    $response->assertSee('TOC');
    $response->assertSee('Rich Text');
    $response->assertSee('data-block-type-slug="rich-text"', false);
    $response->assertSee('Link List');
    $response->assertSee('Link List Item');
    $response->assertSee('Breadcrumb');
    $response->assertSee('Sidebar Brand');
    $response->assertSee('Sidebar Navigation');
    $response->assertSee('Sidebar Footer');
    $response->assertSee('Hero');
    $response->assertSee('Columns');
    $response->assertSee('Column Item');
    $response->assertSee('CTA');
    $response->assertSee('Feature Grid');
    $response->assertSee('Feature Item');
    $response->assertDontSee('Heading');
    $response->assertDontSee('Sidebar Nav Item');
    $response->assertDontSee('Sidebar Nav Group');

    $content = $response->getContent();
    $this->assertNotFalse($content);
    $tableStart = strpos($content, '<tbody>');
    $tableEnd = strpos($content, '</tbody>');
    $this->assertNotFalse($tableStart);
    $this->assertNotFalse($tableEnd);
    $tableBody = substr($content, $tableStart, $tableEnd - $tableStart);
    $this->assertStringContainsString('data-block-type-slug="rich-text"', $tableBody);
    $this->assertStringContainsString('>Rich Text</strong>', $tableBody);
  }

  #[Test]
  public function grid_blocks_can_store_alternating_media_text_section_settings(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $gridType = BlockType::query()->where('slug', 'grid')->firstOrFail();

    $formResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $gridType->id]));

    $formResponse->assertOk();
    $formResponse->assertSee('name="grid_alternate_media_text_sections"', false);
    $formResponse->assertSee('name="grid_alternate_start"', false);
    $formResponse->assertSee('Alternate media/text sections');

    $storeResponse = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $gridType->id,
      'sort_order' => 0,
      'name' => 'Alternating sections',
      'grid_columns' => '2',
      'grid_gap' => '4',
      'grid_alternate_media_text_sections' => '1',
      'grid_alternate_start' => 'text_left',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $block = Block::query()->where('page_id', $page->id)->where('type', 'grid')->firstOrFail();
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);

    $storeResponse->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $this->assertSame('Alternating sections', $settings['layout_name'] ?? null);
    $this->assertSame('2', $settings['columns'] ?? null);
    $this->assertSame('4', $settings['gap'] ?? null);
    $this->assertTrue($settings['alternate_media_text_sections'] ?? false);
    $this->assertSame('text_left', $settings['alternate_start'] ?? null);
  }

  #[Test]
  public function slot_block_picker_defaults_to_the_common_tab(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1]));
    $content = $response->getContent();

    $response->assertOk();
    $response->assertSee('id="slot-block-picker-tab-common"', false);
    $response->assertSee('aria-selected="true"', false);
    $this->assertNotFalse($content);
    $commonPanelStart = strpos($content, 'id="slot-block-picker-panel-common"');
    $layoutPanelStart = strpos($content, 'id="slot-block-picker-panel-layout"');
    $this->assertNotFalse($commonPanelStart);
    $this->assertNotFalse($layoutPanelStart);
    $commonPanelMarkup = substr($content, $commonPanelStart, $layoutPanelStart - $commonPanelStart);
    $this->assertStringContainsString('>Header</strong>', $commonPanelMarkup);
    $this->assertStringContainsString('>Rich Text</strong>', $commonPanelMarkup);
    $this->assertStringContainsString('>Card</strong>', $commonPanelMarkup);
    $this->assertStringContainsString('>Table</strong>', $commonPanelMarkup);
    $this->assertStringNotContainsString('>Section</strong>', $commonPanelMarkup);
    $this->assertStringNotContainsString('>Container</strong>', $commonPanelMarkup);
    $this->assertStringNotContainsString('>Link List</strong>', $commonPanelMarkup);
    $this->assertStringNotContainsString('>Breadcrumb</strong>', $commonPanelMarkup);
  }

  #[Test]
  public function slot_block_picker_real_html_includes_rich_text_and_code_rows_when_both_are_published(): void
  {
    $this->seedFoundation();

    BlockType::query()->updateOrCreate(
      ['slug' => 'code'],
      [
        'name' => 'Code',
        'category' => 'legacy',
        'description' => 'Legacy code block for translated snippets.',
        'source_type' => 'static',
        'is_system' => false,
        'is_container' => false,
        'sort_order' => 20,
        'status' => 'published',
      ],
    );

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1]));
    $content = $response->getContent();

    $response->assertOk();
    $response->assertSee('Block Types');
    $response->assertSee('Rich Text');
    $response->assertSee('Code');
    $this->assertNotFalse($content);
    $commonPanelStart = strpos($content, 'id="slot-block-picker-panel-common"');
    $layoutPanelStart = strpos($content, 'id="slot-block-picker-panel-layout"');
    $this->assertNotFalse($commonPanelStart);
    $this->assertNotFalse($layoutPanelStart);
    $commonPanelMarkup = substr($content, $commonPanelStart, $layoutPanelStart - $commonPanelStart);
    $this->assertStringNotContainsString('Plain Text', $commonPanelMarkup);
    $response->assertSee('data-block-type-slug="rich-text"', false);
    $response->assertSee('data-block-type-slug="code"', false);
  }

  #[Test]
  public function slot_block_picker_shows_rich_text_after_existing_legacy_catalog_entry_is_reseeded(): void
  {
    $this->seedFoundation();

    BlockType::query()->where('slug', 'rich-text')->update([
      'category' => 'legacy',
      'status' => 'draft',
      'sort_order' => 100,
    ]);

    $this->seed(BlockTypeSeeder::class);

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1]));

    $response->assertOk();
    $response->assertSee('Rich Text');
  }

  #[Test]
  public function link_list_parent_save_allows_child_items_without_meta_or_description(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $linkListType = BlockType::query()->where('slug', 'link-list')->firstOrFail();
    $linkListItemType = BlockType::query()->where('slug', 'link-list-item')->firstOrFail();

    $linkList = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'link-list',
      'block_type_id' => $linkListType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->put(route('admin.blocks.update', $linkList), [
      'page_id' => $page->id,
      'parent_id' => null,
      'slot_type_id' => $main->id,
      'block_type_id' => $linkListType->id,
      'sort_order' => 0,
      'title' => null,
      'subtitle' => null,
      'content' => null,
      'status' => 'published',
      'link_list_items' => [
        [
          'id' => null,
          'block_type_id' => $linkListItemType->id,
          'title' => 'With meta only',
          'subtitle' => 'Optional meta',
          'content' => '',
          'url' => 'with-meta.html',
          'status' => 'published',
          'is_system' => 0,
          'sort_order' => 0,
          '_delete' => 0,
        ],
        [
          'id' => null,
          'block_type_id' => $linkListItemType->id,
          'title' => 'Without meta or description',
          'subtitle' => '',
          'content' => '',
          'url' => 'without-meta.html',
          'status' => 'published',
          'is_system' => 0,
          'sort_order' => 1,
          '_delete' => 0,
        ],
      ],
      '_slot_block_mode' => 'edit',
      '_slot_block_id' => $linkList->id,
    ]);

    $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $response->assertSessionDoesntHaveErrors();

    $items = Block::query()
      ->where('parent_id', $linkList->id)
      ->where('type', 'link-list-item')
      ->orderBy('sort_order')
      ->get();

    $this->assertCount(2, $items);
    $this->assertSame('with-meta.html', $items[0]->url);
    $this->assertSame('without-meta.html', $items[1]->url);
    $this->assertDatabaseHas('wbcms_block_text_translations', [
      'block_id' => $items[0]->id,
      'locale_id' => $this->defaultLocale()->id,
      'title' => 'With meta only',
      'subtitle' => 'Optional meta',
      'content' => null,
    ]);
    $this->assertDatabaseHas('wbcms_block_text_translations', [
      'block_id' => $items[1]->id,
      'locale_id' => $this->defaultLocale()->id,
      'title' => 'Without meta or description',
      'subtitle' => null,
      'content' => null,
    ]);
  }

  #[Test]
  public function link_list_parent_save_still_requires_child_item_title(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $linkListType = BlockType::query()->where('slug', 'link-list')->firstOrFail();
    $linkListItemType = BlockType::query()->where('slug', 'link-list-item')->firstOrFail();

    $linkList = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'link-list',
      'block_type_id' => $linkListType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)
      ->from(route('admin.pages.slots.blocks', [$page, $pageSlot, 'edit' => $linkList->id]))
      ->put(route('admin.blocks.update', $linkList), [
        'page_id' => $page->id,
        'parent_id' => null,
        'slot_type_id' => $main->id,
        'block_type_id' => $linkListType->id,
        'sort_order' => 0,
        'title' => null,
        'subtitle' => null,
        'content' => null,
        'status' => 'published',
        'link_list_items' => [[
          'id' => null,
          'block_type_id' => $linkListItemType->id,
          'title' => '',
          'subtitle' => 'Optional meta',
          'content' => '',
          'url' => 'missing-title.html',
          'status' => 'published',
          'is_system' => 0,
          'sort_order' => 0,
          '_delete' => 0,
        ]],
        '_slot_block_mode' => 'edit',
        '_slot_block_id' => $linkList->id,
      ]);

    $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot, 'edit' => $linkList->id]));
    $response->assertSessionHasErrors('link_list_items.0.title');
    $this->assertDatabaseMissing('wbcms_blocks', [
      'parent_id' => $linkList->id,
      'type' => 'link-list-item',
      'url' => 'missing-title.html',
    ]);
  }

  #[Test]
  public function direct_link_list_item_save_allows_blank_meta_and_description(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $linkListType = BlockType::query()->where('slug', 'link-list')->firstOrFail();
    $linkListItemType = BlockType::query()->where('slug', 'link-list-item')->firstOrFail();
    $linkList = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'link-list',
      'block_type_id' => $linkListType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'parent_id' => $linkList->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $linkListItemType->id,
      'sort_order' => 0,
      'title' => 'Direct item',
      'subtitle' => '',
      'content' => '',
      'url' => 'direct.html',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $response->assertSessionDoesntHaveErrors();

    $item = Block::query()->where('parent_id', $linkList->id)->where('type', 'link-list-item')->firstOrFail();

    $this->assertSame('direct.html', $item->url);
    $this->assertDatabaseHas('wbcms_block_text_translations', [
      'block_id' => $item->id,
      'locale_id' => $this->defaultLocale()->id,
      'title' => 'Direct item',
      'subtitle' => null,
      'content' => null,
    ]);
  }

  #[Test]
  public function code_block_editor_renders_without_missing_locale_flags(): void
  {
    $this->seedFoundation();

    $codeType = BlockType::query()->where('slug', 'code')->firstOrFail();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [
      $page,
      $pageSlot,
      'picker' => 1,
      'block_type_id' => $codeType->id,
    ]));

    $response->assertOk();
    $response->assertDontSee('Undefined variable', false);
    $response->assertSee('Add Block: Code');
    $response->assertSee('Syntax Language');
    $this->assertFalse(Str::contains((string) $response->getContent(), 'Undefined variable $isDefaultLocale'));
  }

  #[Test]
  public function existing_code_blocks_open_the_slot_edit_modal_after_catalog_refresh(): void
  {
    $this->seedFoundation();

    $codeType = BlockType::query()->where('slug', 'code')->firstOrFail();
    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main, 'Getting Started', 'getting-started');

    $codeBlock = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'code',
      'block_type_id' => $codeType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'title' => 'Install command',
      'subtitle' => 'composer.json',
      'content' => 'composer install',
      'settings' => json_encode(['language' => 'bash'], JSON_THROW_ON_ERROR),
      'status' => 'published',
      'is_system' => false,
    ]);

    $this->seed(BlockTypeSeeder::class);
    $pageReturnUrl = route('admin.pages.index', ['site' => $page->site_id]);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'edit' => $codeBlock->id, 'return_url' => $pageReturnUrl]));

    $response->assertOk();
    $response->assertSee('id="slot-block-editor-modal"', false);
    $response->assertSee('Edit Block: Code (Getting Started / Main)', false);
    $response->assertSee('name="_slot_block_mode" value="edit"', false);
    $response->assertSee('name="_slot_block_id" value="'.$codeBlock->id.'"', false);
    $response->assertSee('name="title"', false);
    $response->assertSee('name="subtitle"', false);
    $response->assertSee('name="content"', false);
    $response->assertSee('name="language"', false);
    $response->assertSee('composer install');
    $response->assertSee('value="bash"', false);
    $response->assertSee('href="'.e(route('admin.pages.slots.blocks', ['page' => $page, 'slot' => $pageSlot, 'edit' => $codeBlock->id, 'return_url' => $pageReturnUrl])).'" class="wb-action-btn wb-action-btn-edit"', false);
  }

  #[Test]
  public function card_and_card_regions_are_seeded_with_the_expected_container_contracts(): void
  {
    $this->seedFoundation();

    $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();
    $cardHeaderType = BlockType::query()->where('slug', 'card_header')->firstOrFail();
    $cardBodyType = BlockType::query()->where('slug', 'card_body')->firstOrFail();
    $cardFooterType = BlockType::query()->where('slug', 'card_footer')->firstOrFail();
    $card = new Block(['type' => 'card', 'block_type_id' => $cardType->id]);
    $cardHeader = new Block(['type' => 'card_header', 'block_type_id' => $cardHeaderType->id]);
    $cardBody = new Block(['type' => 'card_body', 'block_type_id' => $cardBodyType->id]);
    $cardFooter = new Block(['type' => 'card_footer', 'block_type_id' => $cardFooterType->id]);
    $card->setRelation('blockType', $cardType);
    $cardHeader->setRelation('blockType', $cardHeaderType);
    $cardBody->setRelation('blockType', $cardBodyType);
    $cardFooter->setRelation('blockType', $cardFooterType);

    $this->assertSame('published', $cardType->status);
    $this->assertTrue($cardType->is_container);
    $this->assertTrue($card->canAcceptChildren());
    $this->assertSame(['card_header', 'card_body', 'card_footer'], $card->allowedChildTypeSlugs());
    $this->assertTrue($card->canAcceptChildType('card_header'));
    $this->assertTrue($card->canAcceptChildType('card_body'));
    $this->assertTrue($card->canAcceptChildType('card_footer'));
    $this->assertFalse($card->canAcceptChildType('plain_text'));
    $this->assertTrue($cardHeader->canAcceptChildren());
    $this->assertTrue($cardBody->canAcceptChildren());
    $this->assertTrue($cardFooter->canAcceptChildren());
    $this->assertFalse($cardHeader->canAcceptChildType('card_body'));
    $this->assertFalse($cardBody->canAcceptChildType('card_footer'));
    $this->assertFalse($cardFooter->canAcceptChildType('card_header'));
  }

  #[Test]
  public function stat_card_is_seeded_as_published_content_block(): void
  {
    $this->seedFoundation();

    $statCardType = BlockType::query()->where('slug', 'stat-card')->firstOrFail();

    $this->assertSame('Stat Card', $statCardType->name);
    $this->assertSame('published', $statCardType->status);
    $this->assertSame('content', $statCardType->category);
    $this->assertFalse($statCardType->is_container);
  }

  #[Test]
  public function slot_block_picker_renders_tabbed_catalog_headers_and_filter_controls(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1]));
    $content = $response->getContent();

    $response->assertOk();
    $response->assertDontSee('Recommended');
    $response->assertSee('data-admin-listing-filters', false);
    $response->assertSee('data-admin-listing-filters-search', false);
    $response->assertSee('data-admin-listing-filters-actions', false);
    $response->assertSee('id="slot_block_type_search"', false);
    $response->assertSee('name="block_type_search"', false);
    $response->assertSee('name="picker" value="1"', false);
    $response->assertSee('Search block types', false);
    $response->assertDontSee('>Reset</a>', false);
    $response->assertSee('>Apply</button>', false);
    $response->assertDontSee('id="slot_block_type_sort"', false);
    $response->assertDontSee('name="block_type_sort"', false);
    $response->assertDontSee('id="slot_block_type_category"', false);
    $response->assertDontSee('name="block_type_category"', false);
    $response->assertSee('id="slot-block-picker-modal"', false);
    $response->assertSee('data-wb-admin-autoload-overlay', false);
    $response->assertSee('data-slot-block-picker-count', false);
    $response->assertDontSee('class="wb-modal wb-modal-xl is-open" id="slot-block-picker-modal"', false);
    $response->assertSee('data-wb-slot-block-picker-tabs', false);
    $response->assertSee('role="tablist"', false);
    $response->assertSee('type="button"', false);
    $response->assertSee('id="slot-block-picker-tab-common"', false);
    $response->assertSee('id="slot-block-picker-tab-layout"', false);
    $response->assertSee('id="slot-block-picker-tab-content"', false);
    $response->assertSee('id="slot-block-picker-tab-navigation"', false);
    $response->assertSee('id="slot-block-picker-tab-advanced"', false);
    $response->assertSee('id="slot-block-picker-tab-all"', false);
    $response->assertSee('id="slot-block-picker-panel-common"', false);
    $response->assertSee('id="slot-block-picker-panel-layout"', false);
    $response->assertSee('id="slot-block-picker-panel-content"', false);
    $response->assertSee('id="slot-block-picker-panel-navigation"', false);
    $response->assertSee('id="slot-block-picker-panel-advanced"', false);
    $response->assertSee('name="block_type_tab" value="common"', false);
    $response->assertSee('<th class="wb-nowrap">Name</th>', false);
    $response->assertSee('<th>Category</th>', false);
    $response->assertSee('<th>Description</th>', false);
    $this->assertNotFalse($content);
    $this->assertSame(1, substr_count($content, 'id="slot-block-picker-modal"'));
    $this->assertStringContainsString('class="wb-modal wb-modal-xl wb-slot-block-picker-modal" id="slot-block-picker-modal"', $content);
    $this->assertStringContainsString('class="wb-modal-dialog wb-slot-block-picker-dialog"', $content);
    $this->assertStringContainsString('class="wb-modal-body wb-stack wb-gap-4 wb-slot-block-picker-body"', $content);
    $this->assertStringContainsString('data-wb-admin-autoload-overlay hidden', $content);
    $this->assertStringNotContainsString('wb-overlay-layer', $content);
    $this->assertStringNotContainsString('wb-overlay-backdrop', $content);
    $this->assertMatchesRegularExpression('/data-slot-block-picker-count>\d+</', $content);
    $this->assertStringContainsString('class="wb-admin-listing-filters"', $content);
    $this->assertStringContainsString('data-wb-slot-block-picker-tab="common"', $content);
    $this->assertStringContainsString('data-wb-tab="slot-block-picker-panel-common"', $content);
    $this->assertStringContainsString('id="slot-block-picker-panel-common"', $content);
    $this->assertStringContainsString('class="wb-table-wrap wb-slot-block-picker-table-wrap"', $content);
    $this->assertStringNotContainsString('wb-slot-block-picker-results-card', $content);
    $this->assertStringNotContainsString('wb-slot-block-picker-results-body', $content);
    $this->assertMatchesRegularExpression('/data-wb-tab="slot-block-picker-panel-layout"/s', $content);
    $this->assertMatchesRegularExpression('/id="slot-block-picker-panel-layout"/s', $content);
    $this->assertMatchesRegularExpression('/<div class="wb-tabs-panel is-active wb-stack wb-gap-0" id="slot-block-picker-panel-common"[^>]*aria-hidden="false"/s', $content);
    $this->assertMatchesRegularExpression('/<div class="wb-tabs-panel\s+wb-stack wb-gap-0" id="slot-block-picker-panel-layout"[^>]*hidden[^>]*aria-hidden="true"/s', $content);

    $listStart = strpos($content, '<div class="wb-table-wrap wb-slot-block-picker-table-wrap"');
    $footerStart = strpos($content, '<div class="wb-modal-footer');

    $this->assertNotFalse($listStart);
    $this->assertNotFalse($footerStart);

    $listMarkup = substr($content, $listStart, $footerStart - $listStart);
    $commonPanelEnd = strpos($listMarkup, 'id="slot-block-picker-panel-layout"');
    $this->assertNotFalse($commonPanelEnd);
    $commonListMarkup = substr($listMarkup, 0, $commonPanelEnd);

    $this->assertStringContainsString('>Header</strong>', $commonListMarkup);
    $this->assertStringContainsString('>Rich Text</strong>', $commonListMarkup);
    $this->assertStringContainsString('>Button Link</strong>', $commonListMarkup);
    $this->assertStringContainsString('>Card</strong>', $commonListMarkup);
    $this->assertStringContainsString('>Table</strong>', $commonListMarkup);
    $this->assertStringContainsString('>Quote</strong>', $commonListMarkup);
    $this->assertStringContainsString('>Alert</strong>', $commonListMarkup);
    $this->assertStringNotContainsString('>Section</strong>', $commonListMarkup);
    $this->assertStringNotContainsString('>Card Header</strong>', $commonListMarkup);
    $this->assertStringNotContainsString('>Card Body</strong>', $commonListMarkup);
    $this->assertStringNotContainsString('>Card Footer</strong>', $commonListMarkup);
    $this->assertStringNotContainsString('>Link List</strong>', $commonListMarkup);
    $this->assertStringNotContainsString('>HTML (Trusted)</strong>', $commonListMarkup);
    $this->assertMatchesRegularExpression('/>Alert<\/strong>.*>Button Link<\/strong>.*>Card<\/strong>.*>Header<\/strong>.*>Quote<\/strong>.*>Rich Text<\/strong>.*>Table<\/strong>/s', $commonListMarkup);
  }

  #[Test]
  public function trusted_html_is_hidden_from_the_picker_for_editors(): void
  {
    $this->seedFoundation();

    $user = User::factory()->editor()->create();
    $site = Site::query()->firstOrFail();
    $user->sites()->sync([$site->id]);
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $page->update(['status' => 'draft']);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1]));

    $response->assertOk();
    $response->assertDontSee('HTML (Trusted)');
    $response->assertDontSee('id="slot-block-picker-tab-advanced"', false);
  }

  #[Test]
  public function advanced_tab_includes_trusted_html_for_super_admins(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_tab' => 'advanced']));

    $response->assertOk();
    $response->assertSee('id="slot-block-picker-tab-advanced"', false);
    $response->assertSee('aria-selected="true"', false);
    $response->assertSee('>HTML (Trusted)</strong>', false);
  }

  #[Test]
  public function trusted_html_form_and_create_flow_are_available_to_super_admins(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $htmlType = BlockType::query()->where('slug', 'html')->firstOrFail();

    $formResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $htmlType->id]));

    $formResponse->assertOk();
    $formResponse->assertSee('Add Block: HTML (Trusted)');
    $formResponse->assertSee('Trusted HTML');
    $formResponse->assertSee('Trusted HTML only.');
    $formResponse->assertSee('Use Rich Text for normal formatted copy and Code for escaped snippets.');

    $storeResponse = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $htmlType->id,
      'sort_order' => 0,
      'content' => '<div class="wb-card"><div class="wb-card-body"><i class="wb-icon wb-icon-home" aria-hidden="true"></i><strong>Home</strong></div></div>',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $storeResponse->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $this->assertDatabaseHas('wbcms_blocks', [
      'page_id' => $page->id,
      'type' => 'html',
      'content' => null,
    ]);
    $this->assertTextTranslation(Block::query()->where('page_id', $page->id)->where('type', 'html')->firstOrFail(), $this->defaultLocale()->id, [
      'content' => '<div class="wb-card"><div class="wb-card-body"><i class="wb-icon wb-icon-home" aria-hidden="true"></i><strong>Home</strong></div></div>',
    ]);
  }

  #[Test]
  public function trusted_html_direct_create_request_does_not_open_for_editors(): void
  {
    $this->seedFoundation();

    $user = User::factory()->editor()->create();
    $site = Site::query()->firstOrFail();
    $user->sites()->sync([$site->id]);
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $page->update(['status' => 'draft']);
    $htmlType = BlockType::query()->where('slug', 'html')->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $htmlType->id]));

    $response->assertOk();
    $response->assertDontSee('Add Block: HTML (Trusted)');
    $response->assertDontSee('Trusted HTML only.');
  }

  #[Test]
  public function breadcrumb_is_seeded_as_a_published_navigation_system_block(): void
  {
    $this->seedFoundation();

    $breadcrumbType = BlockType::query()->where('slug', 'breadcrumb')->firstOrFail();

    $this->assertSame('Breadcrumb', $breadcrumbType->name);
    $this->assertSame('published', $breadcrumbType->status);
    $this->assertSame('navigation', $breadcrumbType->category);
    $this->assertTrue($breadcrumbType->is_system);
    $this->assertFalse($breadcrumbType->is_container);
  }

  #[Test]
  public function header_actions_is_seeded_as_a_published_navigation_system_block(): void
  {
    $this->seedFoundation();

    $headerActionsType = BlockType::query()->where('slug', 'header-actions')->firstOrFail();

    $this->assertSame('Header Actions', $headerActionsType->name);
    $this->assertSame('published', $headerActionsType->status);
    $this->assertSame('navigation', $headerActionsType->category);
    $this->assertTrue($headerActionsType->is_system);
    $this->assertFalse($headerActionsType->is_container);
  }

  #[Test]
  public function sticky_navbar_is_seeded_as_a_published_navigation_system_block(): void
  {
    $this->seedFoundation();

    $stickyNavbarType = BlockType::query()->where('slug', 'sticky-navbar')->firstOrFail();
    $navbarBrandType = BlockType::query()->where('slug', 'navbar-brand')->firstOrFail();
    $navbarNavigationType = BlockType::query()->where('slug', 'navbar-navigation')->firstOrFail();

    $navbarBlock = new Block(['type' => 'sticky-navbar', 'block_type_id' => $stickyNavbarType->id]);
    $navbarBlock->setRelation('blockType', $stickyNavbarType);

    $this->assertSame('Navbar', $stickyNavbarType->name);
    $this->assertSame('published', $stickyNavbarType->status);
    $this->assertSame('navigation', $stickyNavbarType->category);
    $this->assertTrue($stickyNavbarType->is_system);
    $this->assertTrue($stickyNavbarType->is_container);
    $this->assertSame('Navbar Brand', $navbarBrandType->name);
    $this->assertSame('Navbar Navigation', $navbarNavigationType->name);
    $this->assertFalse($navbarBrandType->is_container);
    $this->assertFalse($navbarNavigationType->is_container);
    $this->assertSame(['container', 'cluster', 'header', 'plain_text', 'rich-text', 'button_link', 'navbar-brand', 'navbar-navigation', 'header-actions', 'search-form'], $navbarBlock->allowedChildTypeSlugs());
    $this->assertTrue($navbarBlock->ownsPublicRoot());
  }

  #[Test]
  public function deferred_non_container_blocks_do_not_offer_new_child_creation_but_historical_rows_stay_visible(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $codeType = BlockType::query()->where('slug', 'code')->firstOrFail();
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

    $code = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $codeType->id,
      'type' => 'code',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'content' => 'echo true;',
      'status' => 'published',
      'is_system' => false,
    ]);

    $legacyChild = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $code->id,
      'block_type_id' => $plainTextType->id,
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'content' => 'Legacy child row',
      'status' => 'published',
      'is_system' => false,
    ]);

    $indexResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $indexResponse->assertOk();
    $indexResponse->assertSee('<span class="wb-cms-block-children-badge" aria-label="1 child block">1</span>', false);
    $indexResponse->assertSee('data-block-id="'.$legacyChild->id.'"', false);
    $indexResponse->assertSee('data-parent-id="'.$code->id.'"', false);
    $indexResponse->assertDontSee('href="'.e(route('admin.pages.slots.blocks', ['page' => $page, 'slot' => $pageSlot, 'picker' => 1, 'parent_id' => $code->id])).'" class="wb-action-btn" title="Add child block"', false);

    $pickerResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'parent_id' => $code->id]));

    $pickerResponse->assertOk();
    $pickerResponse->assertSee('No common shortcuts are available for this picker context.');
    $pickerResponse->assertDontSee('data-block-type-slug=', false);

    $invalidCreate = $this->actingAs($user)
      ->from(route('admin.pages.slots.blocks', [$page, $pageSlot]))
      ->post(route('admin.blocks.store'), [
        'page_id' => $page->id,
        'parent_id' => $code->id,
        'slot_type_id' => $main->id,
        'block_type_id' => $plainTextType->id,
        'sort_order' => 1,
        'text' => 'Bad child',
        'status' => 'published',
        '_slot_block_mode' => 'create',
      ]);

    $invalidCreate->assertStatus(302);
    $location = $invalidCreate->headers->get('Location');
    $this->assertNotNull($location);
    $this->assertStringStartsWith(route('admin.pages.slots.blocks', [$page, $pageSlot]), $location);
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $this->assertSame('1', $query['picker'] ?? null);
    $this->assertSame((string) $plainTextType->id, $query['block_type_id'] ?? null);
    $this->assertSame((string) $code->id, $query['parent_id'] ?? null);
    $invalidCreate->assertSessionHasErrors('parent_id');
  }

  #[Test]
  public function navbar_form_is_dedicated_and_stores_only_position_settings(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    [$page, $pageSlot] = $this->pageWithSlot($header);
    $stickyNavbarType = BlockType::query()->where('slug', 'sticky-navbar')->firstOrFail();

    $formResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $stickyNavbarType->id]));

    $formResponse->assertOk();
    $formResponse->assertSee('Add Block: Navbar');
    $formResponse->assertSee('Navbar renders only <code>nav.wb-navbar</code> and its child blocks.', false);
    $formResponse->assertSee('name="name"', false);
    $formResponse->assertSee('name="sticky_navbar_mode"', false);
    $formResponse->assertDontSee('name="sticky_navbar_menu_key"', false);
    $formResponse->assertDontSee('name="sticky_navbar_brand_url"', false);
    $formResponse->assertDontSee('name="sticky_navbar_variant"', false);
    $formResponse->assertDontSee('name="sticky_navbar_compact"', false);

    $storeResponse = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $header->id,
      'block_type_id' => $stickyNavbarType->id,
      'sort_order' => 0,
      'name' => 'Primary Header',
      'sticky_navbar_mode' => 'sticky',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $block = Block::query()->where('page_id', $page->id)->where('type', 'sticky-navbar')->firstOrFail();
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);

    $storeResponse->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $this->assertSame('webblocks-cms::admin.blocks.types.sticky-navbar', $block->adminFormView());
    $this->assertSame('webblocks-cms::pages.partials.blocks.sticky-navbar', $block->publicRenderView());
    $this->assertNull($block->textTranslations()->first()?->title);
    $this->assertSame('sticky', $settings['sticky_mode'] ?? null);
    $this->assertSame('Primary Header', $settings['layout_name'] ?? null);
    $this->assertArrayNotHasKey('menu_key', $settings ?? []);
    $this->assertArrayNotHasKey('brand_url', $settings ?? []);
    $this->assertArrayNotHasKey('visual_variant', $settings ?? []);
    $this->assertArrayNotHasKey('compact', $settings ?? []);
    $this->assertArrayNotHasKey('width', $settings ?? []);
  }

  #[Test]
  public function navbar_brand_and_navigation_blocks_require_navbar_ancestor(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    [$page, $pageSlot] = $this->pageWithSlot($header);
    $navbarType = BlockType::query()->where('slug', 'sticky-navbar')->firstOrFail();
    $brandType = BlockType::query()->where('slug', 'navbar-brand')->firstOrFail();
    $navigationType = BlockType::query()->where('slug', 'navbar-navigation')->firstOrFail();
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
    $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
    $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();

    $navbar = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sticky-navbar',
      'block_type_id' => $navbarType->id,
      'source_type' => 'static',
      'slot' => 'header',
      'slot_type_id' => $header->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => true,
    ]);

    $plain = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'plain_text',
      'block_type_id' => $plainTextType->id,
      'source_type' => 'static',
      'slot' => 'header',
      'slot_type_id' => $header->id,
      'sort_order' => 1,
      'status' => 'published',
      'is_system' => false,
    ]);

    $container = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $navbar->id,
      'type' => 'container',
      'block_type_id' => $containerType->id,
      'source_type' => 'static',
      'slot' => 'header',
      'slot_type_id' => $header->id,
      'sort_order' => 2,
      'status' => 'published',
      'is_system' => false,
    ]);

    $cluster = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'cluster',
      'block_type_id' => $clusterType->id,
      'source_type' => 'static',
      'slot' => 'header',
      'slot_type_id' => $header->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $header->id,
      'block_type_id' => $brandType->id,
      'parent_id' => $navbar->id,
      'sort_order' => 0,
      'title' => 'Direct Brand',
      'url' => '/',
      'status' => 'published',
    ])->assertSessionDoesntHaveErrors();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $header->id,
      'block_type_id' => $brandType->id,
      'parent_id' => $cluster->id,
      'sort_order' => 1,
      'title' => 'Nested Brand',
      'url' => '/',
      'status' => 'published',
    ])->assertSessionDoesntHaveErrors();

    NavigationItem::query()->create([
      'site_id' => $page->site_id,
      'menu_key' => 'primary',
      'title' => 'About',
      'link_type' => NavigationItem::LINK_PAGE,
      'page_id' => $page->id,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $header->id,
      'block_type_id' => $navigationType->id,
      'parent_id' => $navbar->id,
      'sort_order' => 0,
      'title' => 'Direct navigation',
      'navbar_navigation_menu_key' => 'primary',
      'navbar_navigation_active_indicator' => 'pill',
      'navbar_navigation_active_matching' => 'section',
      'status' => 'published',
    ])->assertSessionDoesntHaveErrors();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $header->id,
      'block_type_id' => $navigationType->id,
      'parent_id' => $cluster->id,
      'sort_order' => 1,
      'title' => 'Nested navigation',
      'navbar_navigation_menu_key' => 'primary',
      'navbar_navigation_active_indicator' => 'underline',
      'navbar_navigation_active_matching' => 'path',
      'status' => 'published',
    ])->assertSessionDoesntHaveErrors();

    $nestedPicker = $this->actingAs($user)
      ->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'parent_id' => $cluster->id, 'block_type_tab' => 'navigation']));

    $nestedPicker->assertOk();
    $nestedPicker->assertSee('data-block-type-slug="navbar-brand"', false);
    $nestedPicker->assertSee('data-block-type-slug="navbar-navigation"', false);

    $outsidePicker = $this->actingAs($user)
      ->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'parent_id' => $plain->id, 'block_type_tab' => 'navigation']));

    $outsidePicker->assertOk();
    $outsidePicker->assertDontSee('data-block-type-slug="navbar-brand"', false);
    $outsidePicker->assertDontSee('data-block-type-slug="navbar-navigation"', false);

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $header->id,
      'block_type_id' => $brandType->id,
      'parent_id' => $plain->id,
      'sort_order' => 2,
      'title' => 'Outside Brand',
      'url' => '/',
      'status' => 'published',
    ])->assertSessionHasErrors('parent_id');

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $header->id,
      'block_type_id' => $navigationType->id,
      'parent_id' => $plain->id,
      'sort_order' => 3,
      'title' => 'Outside navigation',
      'navbar_navigation_menu_key' => 'primary',
      'status' => 'published',
    ])->assertSessionHasErrors('parent_id');
  }

  #[Test]
  public function invalid_add_block_modal_submit_reopens_the_add_block_modal_with_visible_validation_errors(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $contactFormType = BlockType::query()->where('slug', 'contact_form')->firstOrFail();
    $returnUrl = route('admin.pages.index', ['site' => $page->site_id]);

    $payload = [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $contactFormType->id,
      'sort_order' => 0,
      'heading' => 'Contact support',
      'intro_text' => 'Reach the team.',
      'recipient_email' => 'team@example.com',
      'send_email_notification' => '1',
      'status' => 'published',
      '_slot_block_mode' => 'create',
      'return_url' => $returnUrl,
    ];

    $response = $this->actingAs($user)
      ->from(route('admin.pages.slots.blocks', [
        'page' => $page,
        'slot' => $pageSlot,
        'picker' => 1,
        'block_type_id' => $contactFormType->id,
        'return_url' => $returnUrl,
      ]))
      ->post(route('admin.blocks.store'), $payload);

    $response->assertStatus(302);
    $location = $response->headers->get('Location');
    $this->assertNotNull($location);
    $this->assertStringStartsWith(route('admin.pages.slots.blocks', [$page, $pageSlot]), $location);
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $this->assertSame('1', $query['picker'] ?? null);
    $this->assertSame((string) $contactFormType->id, $query['block_type_id'] ?? null);
    $this->assertSame($returnUrl, $query['return_url'] ?? null);
    $response->assertSessionHasErrors(['submit_label', 'success_message']);

    $followUp = $this->actingAs($user)
      ->from(route('admin.pages.slots.blocks', [
        'page' => $page,
        'slot' => $pageSlot,
        'picker' => 1,
        'block_type_id' => $contactFormType->id,
        'return_url' => $returnUrl,
      ]))
      ->followingRedirects()
      ->post(route('admin.blocks.store'), $payload);
    $content = $followUp->getContent();
    $modalPosition = strpos($content, 'id="slot-block-editor-modal"');

    $followUp->assertOk();
    $followUp->assertSee('id="slot-block-editor-modal"', false);
    $followUp->assertSee('data-wb-slot-block-modal-autoload', false);
    $followUp->assertSee('Add Block: Contact Form', false);
    $this->assertNotFalse($modalPosition);
    $modalMarkup = substr($content, $modalPosition, 4000);
    $this->assertIsString($modalMarkup);
    $this->assertStringContainsString('Validation Error', $modalMarkup);
    $this->assertStringContainsString('The submit label field is required.', $modalMarkup);
    $followUp->assertSee('value="Contact support"', false);
    $followUp->assertSee('Reach the team.');
    $followUp->assertSee('name="submit_label"', false);
    $followUp->assertSee('name="success_message"', false);
  }

  #[Test]
  public function invalid_edit_block_modal_submit_reopens_the_edit_block_modal_with_visible_validation_errors(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $contactFormType = BlockType::query()->where('slug', 'contact_form')->firstOrFail();
    $returnUrl = route('admin.pages.index', ['site' => $page->site_id]);

    $block = Block::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $contactFormType->id,
      'type' => 'contact_form',
      'slot' => 'main',
      'source_type' => 'static',
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
      'settings' => json_encode([
        'recipient_email' => 'team@example.com',
        'send_email_notification' => true,
        'store_submissions' => true,
      ], JSON_UNESCAPED_SLASHES),
    ]);

    $this->assertDatabaseMissing('wbcms_block_contact_form_translations', ['block_id' => $block->id]);

    $payload = [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $contactFormType->id,
      'sort_order' => 0,
      'heading' => 'Updated contact form',
      'intro_text' => 'Updated intro copy.',
      'recipient_email' => 'team@example.com',
      'send_email_notification' => '1',
      'status' => 'published',
      '_slot_block_mode' => 'edit',
      '_slot_block_id' => $block->id,
      'return_url' => $returnUrl,
    ];

    $response = $this->actingAs($user)
      ->from(route('admin.pages.slots.blocks', [
        'page' => $page,
        'slot' => $pageSlot,
        'edit' => $block->id,
        'return_url' => $returnUrl,
      ]))
      ->put(route('admin.blocks.update', $block), $payload);

    $response->assertStatus(302);
    $location = $response->headers->get('Location');
    $this->assertNotNull($location);
    $this->assertStringStartsWith(route('admin.pages.slots.blocks', [$page, $pageSlot]), $location);
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $this->assertSame((string) $block->id, $query['edit'] ?? null);
    $this->assertSame($returnUrl, $query['return_url'] ?? null);
    $response->assertSessionHasErrors(['submit_label', 'success_message']);

    $followUp = $this->actingAs($user)
      ->from(route('admin.pages.slots.blocks', [
        'page' => $page,
        'slot' => $pageSlot,
        'edit' => $block->id,
        'return_url' => $returnUrl,
      ]))
      ->followingRedirects()
      ->put(route('admin.blocks.update', $block), $payload);
    $content = $followUp->getContent();
    $modalPosition = strpos($content, 'id="slot-block-editor-modal"');

    $followUp->assertOk();
    $followUp->assertSee('id="slot-block-editor-modal"', false);
    $followUp->assertSee('data-wb-slot-block-modal-autoload', false);
    $followUp->assertSee('Edit Block: Contact Form', false);
    $this->assertNotFalse($modalPosition);
    $modalMarkup = substr($content, $modalPosition, 4000);
    $this->assertIsString($modalMarkup);
    $this->assertStringContainsString('Validation Error', $modalMarkup);
    $this->assertStringContainsString('The submit label field is required.', $modalMarkup);
    $followUp->assertSee('value="Updated contact form"', false);
    $followUp->assertSee('Updated intro copy.');
    $followUp->assertSee('name="submit_label"', false);
    $followUp->assertSee('name="success_message"', false);
  }

  #[Test]
  public function system_block_editor_uses_configured_admin_locale_copy(): void
  {
    $this->seedFoundation();

    SystemSetting::query()->updateOrCreate(
      ['key' => SystemSettings::ADMIN_LOCALE],
      ['value' => 'tr'],
    );

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $commentsType = BlockType::query()->where('slug', 'comments')->firstOrFail();
    $ratingType = BlockType::query()->where('slug', 'rating')->firstOrFail();

    $pickerResponse = $this->actingAs($user)->get(route('admin.blocks.create'));

    $pickerResponse->assertOk();
    $pickerResponse->assertSee('Blok Ekle');
    $pickerResponse->assertSee('Sistem Blokları');
    $pickerResponse->assertSee('Blok türü seçilmedi');
    $pickerResponse->assertDontSee('No block type selected');

    $commentsResponse = $this->actingAs($user)->get(route('admin.blocks.create', [
      'block_type_id' => $commentsType->id,
    ]));

    $commentsResponse->assertOk();
    $commentsResponse->assertSee('Sistem Yorumları');
    $commentsResponse->assertSee('Yeni yorumları kabul et');
    $commentsResponse->assertSee('Yazar adlarını gizle');
    $commentsResponse->assertDontSee('System Comments');
    $commentsResponse->assertDontSee('Accept new comments');

    $ratingResponse = $this->actingAs($user)->get(route('admin.blocks.create', [
      'block_type_id' => $ratingType->id,
    ]));

    $ratingResponse->assertOk();
    $ratingResponse->assertSee('Sistem Puanlaması');
    $ratingResponse->assertSee('Ziyaretçiler puanlarını güncelleyebilsin');
    $ratingResponse->assertSee('Ortalama ve sayıyı göster');
    $ratingResponse->assertDontSee('System Rating');
    $ratingResponse->assertDontSee('Allow visitors to update their rating');
  }

  #[Test]
  public function docs_sidebar_block_types_are_seeded_with_expected_container_rules(): void
  {
    $this->seedFoundation();

    $brand = BlockType::query()->where('slug', 'sidebar-brand')->firstOrFail();
    $navigation = BlockType::query()->where('slug', 'sidebar-navigation')->firstOrFail();
    $item = BlockType::query()->where('slug', 'sidebar-nav-item')->firstOrFail();
    $group = BlockType::query()->where('slug', 'sidebar-nav-group')->firstOrFail();
    $footer = BlockType::query()->where('slug', 'sidebar-footer')->firstOrFail();

    $navigationBlock = new Block(['type' => 'sidebar-navigation', 'block_type_id' => $navigation->id]);
    $navigationBlock->setRelation('blockType', $navigation);
    $groupBlock = new Block(['type' => 'sidebar-nav-group', 'block_type_id' => $group->id]);
    $groupBlock->setRelation('blockType', $group);

    $this->assertSame('published', $brand->status);
    $this->assertSame('navigation', $brand->category);
    $this->assertFalse($brand->is_container);
    $this->assertTrue($navigation->is_container);
    $this->assertFalse($item->is_container);
    $this->assertTrue($group->is_container);
    $this->assertFalse($footer->is_container);
    $this->assertSame(['sidebar-nav-item', 'sidebar-nav-group'], $navigationBlock->allowedChildTypeSlugs());
    $this->assertSame(['sidebar-nav-item'], $groupBlock->allowedChildTypeSlugs());
  }

  #[Test]
  public function sidebar_navigation_and_group_parent_child_rules_are_enforced(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $sidebar = $this->slotType('sidebar', 'Sidebar', 1);
    [$page, $pageSlot] = $this->pageWithSlot($sidebar, 'Docs', 'docs');
    $navigationType = BlockType::query()->where('slug', 'sidebar-navigation')->firstOrFail();
    $itemType = BlockType::query()->where('slug', 'sidebar-nav-item')->firstOrFail();
    $groupType = BlockType::query()->where('slug', 'sidebar-nav-group')->firstOrFail();
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

    $navigation = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sidebar-navigation',
      'block_type_id' => $navigationType->id,
      'source_type' => 'static',
      'slot' => 'sidebar',
      'slot_type_id' => $sidebar->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $group = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $navigation->id,
      'type' => 'sidebar-nav-group',
      'block_type_id' => $groupType->id,
      'source_type' => 'static',
      'slot' => 'sidebar',
      'slot_type_id' => $sidebar->id,
      'sort_order' => 1,
      'status' => 'published',
      'is_system' => false,
    ]);

    $plain = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'plain_text',
      'block_type_id' => $plainTextType->id,
      'source_type' => 'static',
      'slot' => 'sidebar',
      'slot_type_id' => $sidebar->id,
      'sort_order' => 2,
      'status' => 'published',
      'is_system' => false,
    ]);

    $this->actingAs($user)
      ->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'parent_id' => $navigation->id, 'block_type_tab' => 'navigation']))
      ->assertOk()
      ->assertSee('Sidebar Nav Item')
      ->assertSee('Sidebar Nav Group');

    $this->actingAs($user)
      ->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'parent_id' => $group->id, 'block_type_tab' => 'all']))
      ->assertOk()
      ->assertSee('Sidebar Nav Item');

    $invalidRoot = $this->actingAs($user)
      ->from(route('admin.pages.slots.blocks', [$page, $pageSlot]))
      ->post(route('admin.blocks.store'), [
        'page_id' => $page->id,
        'slot_type_id' => $sidebar->id,
        'block_type_id' => $itemType->id,
        'sort_order' => 0,
        'title' => 'Root item',
        'url' => '/docs',
        'status' => 'published',
        '_slot_block_mode' => 'create',
      ]);

    $invalidRoot->assertStatus(302);
    $location = $invalidRoot->headers->get('Location');
    $this->assertNotNull($location);
    $this->assertStringStartsWith(route('admin.pages.slots.blocks', [$page, $pageSlot]), $location);
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $this->assertSame('1', $query['picker'] ?? null);
    $this->assertSame((string) $itemType->id, $query['block_type_id'] ?? null);
    $invalidRoot->assertSessionHasErrors('parent_id');

    $invalidPlainChild = $this->actingAs($user)
      ->from(route('admin.pages.slots.blocks', [$page, $pageSlot]))
      ->post(route('admin.blocks.store'), [
        'page_id' => $page->id,
        'parent_id' => $plain->id,
        'slot_type_id' => $sidebar->id,
        'block_type_id' => $itemType->id,
        'sort_order' => 0,
        'title' => 'Bad item',
        'url' => '/docs',
        'status' => 'published',
        '_slot_block_mode' => 'create',
      ]);

    $invalidPlainChild->assertStatus(302);
    $location = $invalidPlainChild->headers->get('Location');
    $this->assertNotNull($location);
    $this->assertStringStartsWith(route('admin.pages.slots.blocks', [$page, $pageSlot]), $location);
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $this->assertSame('1', $query['picker'] ?? null);
    $this->assertSame((string) $itemType->id, $query['block_type_id'] ?? null);
    $this->assertSame((string) $plain->id, $query['parent_id'] ?? null);
    $invalidPlainChild->assertSessionHasErrors('parent_id');

    $validGroupChild = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'parent_id' => $group->id,
      'slot_type_id' => $sidebar->id,
      'block_type_id' => $itemType->id,
      'sort_order' => 0,
      'title' => 'Group child',
      'url' => '/docs',
      'sidebar_nav_item_active_mode' => 'path',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $validGroupChild->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $this->assertDatabaseHas('wbcms_blocks', [
      'page_id' => $page->id,
      'parent_id' => $group->id,
      'type' => 'sidebar-nav-item',
    ]);
  }

  #[Test]
  public function sidebar_brand_can_store_logo_media_and_rejects_non_image_media(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $sidebar = $this->slotType('sidebar', 'Sidebar', 1);
    [$page, $pageSlot] = $this->pageWithSlot($sidebar, 'Docs', 'docs');
    $brandType = BlockType::query()->where('slug', 'sidebar-brand')->firstOrFail();

    $image = Asset::query()->create([
      'disk' => 'public',
      'path' => 'media/images/sidebar-brand-logo.png',
      'filename' => 'sidebar-brand-logo.png',
      'original_name' => 'sidebar-brand-logo.png',
      'extension' => 'png',
      'mime_type' => 'image/png',
      'size' => 100,
      'kind' => 'image',
      'visibility' => 'public',
      'uploaded_by' => $user->id,
    ]);

    $document = Asset::query()->create([
      'disk' => 'public',
      'path' => 'media/documents/sidebar-brand.pdf',
      'filename' => 'sidebar-brand.pdf',
      'original_name' => 'sidebar-brand.pdf',
      'extension' => 'pdf',
      'mime_type' => 'application/pdf',
      'size' => 100,
      'kind' => 'document',
      'visibility' => 'public',
      'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
      ->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $brandType->id]))
      ->assertOk()
      ->assertSee('Upload the logo in Media, then select it here.');

    $valid = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $sidebar->id,
      'block_type_id' => $brandType->id,
      'sort_order' => 0,
      'title' => 'WebBlocks UI',
      'subtitle' => 'UI building blocks for humans and AI',
      'url' => '/',
      'target' => '_self',
      'asset_id' => $image->id,
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $valid->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $this->assertDatabaseHas('wbcms_blocks', [
      'page_id' => $page->id,
      'type' => 'sidebar-brand',
      'media_id' => $image->id,
    ]);

    $invalid = $this->actingAs($user)
      ->from(route('admin.pages.slots.blocks', [$page, $pageSlot]))
      ->post(route('admin.blocks.store'), [
        'page_id' => $page->id,
        'slot_type_id' => $sidebar->id,
        'block_type_id' => $brandType->id,
        'sort_order' => 1,
        'title' => 'Bad Logo',
        'url' => '/',
        'asset_id' => $document->id,
        'status' => 'published',
        '_slot_block_mode' => 'create',
      ]);

    $invalid->assertStatus(302);
    $location = $invalid->headers->get('Location');
    $this->assertNotNull($location);
    $this->assertStringStartsWith(route('admin.pages.slots.blocks', [$page, $pageSlot]), $location);
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $this->assertSame('1', $query['picker'] ?? null);
    $this->assertSame((string) $brandType->id, $query['block_type_id'] ?? null);
    $invalid->assertSessionHasErrors('media_id');
  }

  #[Test]
  public function sidebar_brand_accepts_logo_only_with_accessible_label_and_optional_url(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $sidebar = $this->slotType('sidebar', 'Sidebar', 1);
    [$page, $pageSlot] = $this->pageWithSlot($sidebar, 'Docs', 'docs');
    $brandType = BlockType::query()->where('slug', 'sidebar-brand')->firstOrFail();

    $asset = Asset::query()->create([
      'disk' => 'public',
      'path' => 'media/images/sidebar-brand-logo-only-admin.png',
      'filename' => 'sidebar-brand-logo-only-admin.png',
      'original_name' => 'sidebar-brand-logo-only-admin.png',
      'extension' => 'png',
      'mime_type' => 'image/png',
      'size' => 1200,
      'kind' => 'image',
      'visibility' => 'public',
    ]);

    $formResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $brandType->id]));

    $formResponse->assertOk();
    $formResponse->assertSee('name="sidebar_brand_aria_label"', false);
    $formResponse->assertDontSee('name="title" class="wb-input" type="text" value="" required', false);
    $formResponse->assertDontSee('name="url" class="wb-input" type="text" value="" placeholder="Falls back to the site home URL" required', false);

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $sidebar->id,
      'block_type_id' => $brandType->id,
      'sort_order' => 0,
      'title' => '',
      'subtitle' => '',
      'asset_id' => $asset->id,
      'url' => '',
      'target' => '_self',
      'sidebar_brand_aria_label' => 'Docs Home',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertSessionDoesntHaveErrors();

    $brand = Block::query()->where('page_id', $page->id)->where('type', 'sidebar-brand')->firstOrFail();

    $this->assertSame('Docs Home', $brand->sidebarBrandAriaLabel());
    $this->assertNull($brand->fresh()->title);

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $sidebar->id,
      'block_type_id' => $brandType->id,
      'sort_order' => 1,
      'title' => '',
      'subtitle' => '',
      'url' => '',
      'target' => '_self',
      'sidebar_brand_aria_label' => '',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertSessionHasErrors('title');
  }

  #[Test]
  public function sidebar_navigation_icon_validation_uses_the_catalog_without_throwing_for_new_blocks(): void
  {
    $this->seedFoundation();
    $this->seed(IconCatalogSeeder::class);

    $user = User::factory()->superAdmin()->create();
    $sidebar = $this->slotType('sidebar', 'Sidebar', 1);
    [$page, $pageSlot] = $this->pageWithSlot($sidebar, 'Docs', 'docs');
    $navigationType = BlockType::query()->where('slug', 'sidebar-navigation')->firstOrFail();
    $itemType = BlockType::query()->where('slug', 'sidebar-nav-item')->firstOrFail();
    $groupType = BlockType::query()->where('slug', 'sidebar-nav-group')->firstOrFail();

    $navigation = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sidebar-navigation',
      'block_type_id' => $navigationType->id,
      'source_type' => 'static',
      'slot' => 'sidebar',
      'slot_type_id' => $sidebar->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $invalidItem = $this->actingAs($user)
      ->from(route('admin.pages.slots.blocks', [$page, $pageSlot]))
      ->post(route('admin.blocks.store'), [
        'page_id' => $page->id,
        'parent_id' => $navigation->id,
        'slot_type_id' => $sidebar->id,
        'block_type_id' => $itemType->id,
        'sort_order' => 0,
        'title' => 'Bad icon item',
        'url' => '/docs',
        'sidebar_nav_item_icon' => 'not-a-real-icon',
        'sidebar_nav_item_active_mode' => 'path',
        'status' => 'published',
        '_slot_block_mode' => 'create',
      ]);

    $invalidItem->assertStatus(302);
    $location = $invalidItem->headers->get('Location');
    $this->assertNotNull($location);
    $this->assertStringStartsWith(route('admin.pages.slots.blocks', [$page, $pageSlot]), $location);
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $this->assertSame('1', $query['picker'] ?? null);
    $this->assertSame((string) $itemType->id, $query['block_type_id'] ?? null);
    $this->assertSame((string) $navigation->id, $query['parent_id'] ?? null);
    $invalidItem->assertSessionHasErrors('sidebar_nav_item_icon');

    $validGroup = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'parent_id' => $navigation->id,
      'slot_type_id' => $sidebar->id,
      'block_type_id' => $groupType->id,
      'sort_order' => 1,
      'title' => 'Guides',
      'name' => 'guides',
      'sidebar_nav_group_icon' => 'layout',
      'sidebar_nav_group_initially_open' => true,
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $validGroup->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $group = Block::query()
      ->where('page_id', $page->id)
      ->where('parent_id', $navigation->id)
      ->where('type', 'sidebar-nav-group')
      ->latest('id')
      ->firstOrFail();

    $this->assertSame('layout', $group->sidebarNavItemIcon());
    $this->assertTrue($group->sidebarNavGroupInitiallyOpen());
  }

  #[Test]
  public function breadcrumb_form_is_dedicated_and_can_be_added_to_the_header_slot(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    [$page, $pageSlot] = $this->pageWithSlot($header);
    $breadcrumbType = BlockType::query()->where('slug', 'breadcrumb')->firstOrFail();

    $formResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $breadcrumbType->id]));

    $formResponse->assertOk();
    $formResponse->assertSee('Add Block: Breadcrumb');
    $formResponse->assertSee('System Breadcrumb');
    $formResponse->assertSee('name="breadcrumb_home_label"', false);
    $formResponse->assertSee('name="breadcrumb_include_current"', false);
    $formResponse->assertDontSee('Generic Block Form');
    $formResponse->assertDontSee('name="title"', false);
    $formResponse->assertDontSee('name="content"', false);

    $storeResponse = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $header->id,
      'block_type_id' => $breadcrumbType->id,
      'sort_order' => 0,
      'breadcrumb_home_label' => 'Start',
      'breadcrumb_include_current' => '1',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $block = Block::query()->where('page_id', $page->id)->where('type', 'breadcrumb')->firstOrFail();

    $storeResponse->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $this->assertDatabaseHas('wbcms_blocks', [
      'id' => $block->id,
      'type' => 'breadcrumb',
      'slot' => 'header',
      'title' => null,
      'content' => null,
      'is_system' => true,
    ]);
    $this->assertSame([
      'home_label' => 'Start',
      'include_current' => true,
    ], json_decode((string) $block->fresh()->getRawOriginal('settings'), true));
    $this->assertSame('webblocks-cms::admin.blocks.types.breadcrumb', $block->adminFormView());
    $this->assertSame('webblocks-cms::pages.partials.blocks.breadcrumb', $block->publicRenderView());
  }

  #[Test]
  public function header_actions_form_is_dedicated_and_can_be_added_to_the_header_slot(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    [$page, $pageSlot] = $this->pageWithSlot($header);
    $headerActionsType = BlockType::query()->where('slug', 'header-actions')->firstOrFail();

    $formResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $headerActionsType->id]));

    $formResponse->assertOk();
    $formResponse->assertSee('Add Block: Header Actions');
    $formResponse->assertSee('System Header Actions');
    $formResponse->assertSee('name="header_actions_show_mode_toggle"', false);
    $formResponse->assertSee('name="header_actions_show_accent_toggle"', false);
    $formResponse->assertSee('name="header_actions_show_search"', false);
    $formResponse->assertDontSee('Generic Block Form');
    $formResponse->assertDontSee('name="title"', false);
    $formResponse->assertDontSee('name="content"', false);

    $storeResponse = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $header->id,
      'block_type_id' => $headerActionsType->id,
      'sort_order' => 0,
      'header_actions_show_mode_toggle' => '1',
      'header_actions_show_accent_toggle' => '0',
      'header_actions_show_search' => '1',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $block = Block::query()->where('page_id', $page->id)->where('type', 'header-actions')->firstOrFail();

    $storeResponse->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $this->assertDatabaseHas('wbcms_blocks', [
      'id' => $block->id,
      'type' => 'header-actions',
      'slot' => 'header',
      'title' => null,
      'content' => null,
      'is_system' => true,
    ]);
    $this->assertSame('webblocks-cms::admin.blocks.types.header-actions', $block->adminFormView());
    $this->assertSame('webblocks-cms::pages.partials.blocks.header-actions', $block->publicRenderView());
    $this->assertSame(['show_mode_toggle' => true, 'show_accent_toggle' => false, 'show_search' => true], json_decode((string) $block->getRawOriginal('settings'), true));
  }

  #[Test]
  public function slot_edit_screen_hides_wrapper_controls_and_keeps_block_editing_workflow_visible(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    [$page, $pageSlot] = $this->pageWithSlot($header);

    $this->actingAs($user)
      ->get(route('admin.pages.slots.blocks', [$page, $pageSlot]))
      ->assertOk()
      ->assertSee('Public Wrapper')
      ->assertSee('resolved automatically from the page shell and slot name')
      ->assertDontSee('Slot Settings')
      ->assertDontSee('Wrapper element')
      ->assertDontSee('Wrapper preset')
      ->assertDontSee('Save Slot Settings')
      ->assertDontSee('name="wrapper_element"', false)
      ->assertDontSee('name="wrapper_preset"', false)
      ->assertSee('Blocks')
      ->assertSee('Add Block');
  }

  #[Test]
  public function edit_page_screen_keeps_slot_level_block_editing_available_from_slot_controls(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $pageReturnUrl = route('admin.pages.index', ['site' => $page->site_id]);

    $this->actingAs($user)
      ->get(route('admin.pages.edit', $page))
      ->assertOk()
      ->assertSee('href="'.route('admin.pages.slots.blocks', ['page' => $page, 'slot' => $pageSlot, 'return_url' => $pageReturnUrl]).'" class="wb-btn wb-btn-primary wb-btn-sm">Edit Blocks</a>', false);
  }

  #[Test]
  public function slot_settings_update_route_is_removed(): void
  {
    $this->assertFalse(Route::has('admin.pages.slots.settings.update'));
  }

  #[Test]
  public function edit_slot_blocks_list_renders_native_sortable_markup_and_fallback_move_controls(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
    $alertType = BlockType::query()->where('slug', 'alert')->firstOrFail();
    $pageReturnUrl = route('admin.pages.index', ['site' => $page->site_id]);

    $section = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $sectionType->id,
      'type' => 'section',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $section->id,
      'block_type_id' => $alertType->id,
      'type' => 'alert',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'return_url' => $pageReturnUrl]));

    $response->assertOk();
    $response->assertSee('data-admin-sortable-list', false);
    $response->assertSee('data-admin-sortable-mode="slot-blocks"', false);
    $response->assertSee('data-admin-sortable-reorder-url', false);
    $response->assertSee('data-admin-sortable-item', false);
    $response->assertSee('data-page-id="'.$page->id.'"', false);
    $response->assertSee('data-slot-type-id="'.$main->id.'"', false);
    $response->assertSee('data-slot-block-row', false);
    $response->assertSee('data-slot-block-toggle', false);
    $response->assertSee('data-block-id="'.$section->id.'"', false);
    $response->assertSee('data-parent-id=""', false);
    $response->assertSee('data-slot-type-id="'.$main->id.'"', false);
    $response->assertSee('draggable="true"', false);
    $response->assertSee('data-admin-sortable-handle', false);
    $response->assertDontSee('wb-icon-grip-vertical', false);
    $response->assertSee('<span aria-hidden="true">::</span>', false);
    $response->assertSee('<div class="wb-table-wrap wb-admin-slot-blocks-table-wrap">', false);
    $response->assertSee('class="wb-table wb-table-striped wb-table-hover wb-admin-slot-blocks-table"', false);
    $response->assertSee('class="wb-block-hierarchy-cell wb-admin-slot-block-type-cell"', false);
    $response->assertSee('class="wb-cms-block-tree-label wb-admin-slot-block-type"', false);
    $response->assertSee('class="wb-cms-block-row-title"', false);
    $response->assertSee('class="wb-admin-slot-block-status-cell"', false);
    $response->assertSee('class="wb-admin-slot-block-actions-cell"', false);
    $response->assertSee('<th>Children</th>', false);
    $response->assertSee('<th>Actions</th>', false);
    $response->assertSee('<div class="wb-action-group">', false);
    $response->assertSee('title="Move block up"', false);
    $response->assertSee('title="Move block down"', false);
    $response->assertSee('title="Edit block"', false);
    $response->assertSee('title="Add child block"', false);
    $response->assertSee('href="'.e(route('admin.pages.slots.blocks', ['page' => $page, 'slot' => $pageSlot, 'delete' => $section->id, 'return_url' => $pageReturnUrl])).'" class="wb-action-btn wb-action-btn-delete"', false);
    $response->assertDontSee('onsubmit="return confirm(\'Delete this block?\');"', false);
    $response->assertDontSee('name="expanded"', false);
    $response->assertDontSee('?expanded=', false);
    $response->assertDontSee('&expanded=', false);
  }

  #[Test]
  public function slot_block_list_uses_compact_plain_text_summaries_for_long_rich_text_content(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $richTextType = BlockType::query()->where('slug', 'rich-text')->firstOrFail();

    $block = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $richTextType->id,
      'type' => 'rich-text',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $block->textTranslations()->create([
      'locale_id' => $this->defaultLocale()->id,
      'title' => null,
      'subtitle' => null,
      'content' => '<p>Alpha &amp; Beta <strong>Gamma</strong> Delta Epsilon Zeta Eta Theta Iota Kappa Lambda Mu Nu Xi Omicron Pi Rho Sigma Tau Upsilon Phi Chi Psi Omega.</p>',
    ]);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $response->assertOk();
    $response->assertSee('Rich Text');
    $response->assertSee('>Rich Text</strong>', false);
    $response->assertSee('>Rich Text</strong></a>', false);
    $response->assertSee('wb-cms-block-row-title', false);
    $response->assertDontSee('<p>Alpha &amp; Beta <strong>Gamma</strong>', false);
    $response->assertDontSee('Alpha & Beta Gamma', false);
    $response->assertDontSee('Rho Sigma Tau Upsilon Phi Chi Psi Omega.', false);
    $response->assertSee('published');
    $response->assertSee('title="Edit block"', false);
    $response->assertSee('title="Move block up"', false);
  }

  #[Test]
  public function slot_block_rows_stay_compact_without_detail_markup(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

    $block = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $plainTextType->id,
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'draft',
      'is_system' => false,
    ]);

    $block->textTranslations()->create([
      'locale_id' => $this->defaultLocale()->id,
      'content' => 'Compact row content that should stay in the collapsed summary only.',
    ]);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $response->assertOk();
    $response->assertDontSee('id="slot-block-details-'.$block->id.'"', false);
    $response->assertDontSee('data-slot-block-details-row', false);
    $response->assertDontSee('data-wb-slot-block-details-row', false);
    $response->assertDontSee('title="Expand block details"', false);
    $response->assertDontSee('Preview');
    $response->assertDontSee('Visitor-facing block');
    $response->assertSee('>Plain Text</strong></a>', false);
    $response->assertDontSee('Compact row content that should stay in the collapsed summary only.', false);
    $response->assertSee('>-<', false);
  }

  #[Test]
  public function slot_block_delete_modal_shows_recursive_delete_details_for_nested_blocks(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
    $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

    $section = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $sectionType->id,
      'type' => 'section',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $container = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $section->id,
      'block_type_id' => $containerType->id,
      'type' => 'container',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'block_type_id' => $plainTextType->id,
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'content' => 'Nested copy',
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'delete' => $section->id]));

    $response->assertOk();
    $response->assertSee('id="slot-block-delete-modal"', false);
    $response->assertSee('Also delete all nested child blocks');
    $response->assertSee('This block currently contains 1 direct child and 2 nested descendants.');
    $response->assertSee('Delete block and children');
    $response->assertSee('Delete block');
    $response->assertSee('Recursive deletion cannot be undone except by restoring a revision or backup.');
  }

  #[Test]
  public function delete_all_blocks_action_only_appears_when_slot_has_blocks(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main, 'Filled', 'filled');
    [$emptyPage, $emptySlot] = $this->pageWithSlot($main, 'Empty', 'empty');
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

    Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $plainTextType->id,
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $filledResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $emptyResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$emptyPage, $emptySlot]));

    $filledResponse->assertOk();
    $filledResponse->assertSee('Delete All Blocks');

    $emptyResponse->assertOk();
    $emptyResponse->assertDontSee('Delete All Blocks');
  }

  #[Test]
  public function delete_all_blocks_modal_shows_slot_counts_and_requires_confirmation(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
    $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

    $section = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $sectionType->id,
      'type' => 'section',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $section->id,
      'block_type_id' => $containerType->id,
      'type' => 'container',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $plainTextType->id,
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 1,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'delete_all' => 1]));

    $response->assertOk();
    $response->assertSee('Delete All Blocks');
    $response->assertSee('Page: '.$page->title);
    $response->assertSee('Slot: Main');
    $response->assertSee('Top-level blocks:</strong> 2', false);
    $response->assertSee('Nested descendants:</strong> 1', false);
    $response->assertSee('I understand that this deletes every block in this slot.');
    $response->assertSee('Recovery is only possible through revisions or backups.');

    $failure = $this->actingAs($user)->from(route('admin.pages.slots.blocks', [$page, $pageSlot, 'delete_all' => 1]))
      ->delete(route('admin.pages.slots.blocks.destroy-all', [$page, $pageSlot]));

    $failure->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot, 'delete_all' => 1]));
    $failure->assertSessionHasErrors('confirm_delete_all_blocks');
  }

  #[Test]
  public function delete_all_blocks_removes_only_selected_slot_tree_and_creates_page_revision(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    $sidebar = $this->slotType('sidebar', 'Sidebar', 2);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $sidebar->id,
      'sort_order' => 1,
    ]);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

    $parent = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $sectionType->id,
      'type' => 'section',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $child = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $parent->id,
      'block_type_id' => $plainTextType->id,
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $sibling = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $plainTextType->id,
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 1,
      'status' => 'published',
      'is_system' => false,
    ]);
    $otherSlotBlock = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $plainTextType->id,
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot' => 'sidebar',
      'slot_type_id' => $sidebar->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->delete(route('admin.pages.slots.blocks.destroy-all', [$page, $pageSlot]), [
      'confirm_delete_all_blocks' => '1',
    ]);

    $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $response->assertSessionHas('status', 'Deleted all blocks from Main.');
    $this->assertDatabaseMissing('wbcms_blocks', ['id' => $parent->id]);
    $this->assertDatabaseMissing('wbcms_blocks', ['id' => $child->id]);
    $this->assertDatabaseMissing('wbcms_blocks', ['id' => $sibling->id]);
    $this->assertDatabaseHas('wbcms_blocks', ['id' => $otherSlotBlock->id]);
    $this->assertDatabaseHas('wbcms_page_revisions', [
      'page_id' => $page->id,
      'event' => 'block_deleted',
      'label' => 'All slot blocks deleted',
    ]);
  }

  #[Test]
  public function deleting_a_parent_block_without_recursive_delete_preserves_children(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
    $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

    $parent = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $sectionType->id,
      'type' => 'section',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $child = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $parent->id,
      'block_type_id' => $containerType->id,
      'type' => 'container',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $grandchild = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $child->id,
      'block_type_id' => $plainTextType->id,
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'content' => 'Grandchild content',
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)
      ->delete(route('admin.blocks.destroy', $parent), ['locale' => $this->defaultLocale()->code]);

    $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot, 'locale' => $this->defaultLocale()->code]));
    $response->assertSessionHas('status', 'Block deleted.');
    $this->assertDatabaseMissing('wbcms_blocks', ['id' => $parent->id]);
    $this->assertDatabaseHas('wbcms_blocks', ['id' => $child->id, 'parent_id' => null]);
    $this->assertDatabaseHas('wbcms_blocks', ['id' => $grandchild->id, 'parent_id' => $child->id]);
  }

  #[Test]
  public function deleting_a_parent_block_with_recursive_delete_removes_only_that_subtree(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    $sidebar = $this->slotType('sidebar', 'Sidebar', 2);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    [$otherPage] = $this->pageWithSlot($main, 'Other', 'other');
    $otherPageSidebar = PageSlot::query()->create([
      'page_id' => $otherPage->id,
      'slot_type_id' => $sidebar->id,
      'sort_order' => 1,
    ]);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
    $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

    $parent = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $sectionType->id,
      'type' => 'section',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $child = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $parent->id,
      'block_type_id' => $containerType->id,
      'type' => 'container',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $grandchild = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $child->id,
      'block_type_id' => $plainTextType->id,
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'content' => 'Nested subtree content',
      'status' => 'published',
      'is_system' => false,
    ]);
    $sibling = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $plainTextType->id,
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 1,
      'content' => 'Sibling content',
      'status' => 'published',
      'is_system' => false,
    ]);
    $otherSlotBlock = Block::query()->create([
      'page_id' => $otherPage->id,
      'block_type_id' => $plainTextType->id,
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot' => 'sidebar',
      'slot_type_id' => $sidebar->id,
      'sort_order' => 0,
      'content' => 'Other slot content',
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)
      ->delete(route('admin.blocks.destroy', $parent), [
        'locale' => $this->defaultLocale()->code,
        'delete_descendants' => '1',
      ]);

    $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot, 'locale' => $this->defaultLocale()->code]));
    $response->assertSessionHas('status', 'Block and nested child blocks deleted.');
    $this->assertDatabaseMissing('wbcms_blocks', ['id' => $parent->id]);
    $this->assertDatabaseMissing('wbcms_blocks', ['id' => $child->id]);
    $this->assertDatabaseMissing('wbcms_blocks', ['id' => $grandchild->id]);
    $this->assertDatabaseHas('wbcms_blocks', ['id' => $sibling->id]);
    $this->assertDatabaseHas('wbcms_blocks', ['id' => $otherSlotBlock->id]);
    $this->assertNotNull($otherPageSidebar);
  }

  #[Test]
  public function recursive_delete_still_deletes_a_block_without_children(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

    $block = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $plainTextType->id,
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'content' => 'Solo block',
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)
      ->delete(route('admin.blocks.destroy', $block), [
        'locale' => $this->defaultLocale()->code,
        'delete_descendants' => '1',
      ]);

    $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot, 'locale' => $this->defaultLocale()->code]));
    $response->assertSessionHas('status', 'Block and nested child blocks deleted.');
    $this->assertDatabaseMissing('wbcms_blocks', ['id' => $block->id]);
  }

  #[Test]
  public function rich_text_slot_block_rows_keep_plain_text_compact_summaries_even_when_expanded_state_exists(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $richTextType = BlockType::query()->where('slug', 'rich-text')->firstOrFail();

    $block = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $richTextType->id,
      'type' => 'rich-text',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $block->textTranslations()->create([
      'locale_id' => $this->defaultLocale()->id,
      'content' => '<p>Alpha <strong>Beta</strong> <em>Gamma</em>.</p>',
    ]);

    $response = $this->withSession(['slot_block_expanded' => [$block->id]])
      ->actingAs($user)
      ->get(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $response->assertOk();
    $response->assertDontSee('id="slot-block-details-'.$block->id.'"', false);
    $response->assertDontSee('wb-block-row-details-body', false);
    $response->assertDontSee('Preview');
    $response->assertSee('>Rich Text</strong></a>', false);
    $response->assertDontSee('Alpha Beta Gamma.', false);
    $response->assertDontSee('<p>Alpha <strong>Beta</strong>', false);
  }

  #[Test]
  public function slot_block_rows_preserve_actions_statuses_and_nested_child_toggle_support(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
    $codeType = BlockType::query()->where('slug', 'code')->firstOrFail();

    $section = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $sectionType->id,
      'type' => 'section',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
      'settings' => json_encode(['layout_name' => 'Docs shell'], JSON_THROW_ON_ERROR),
    ]);

    $child = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $section->id,
      'block_type_id' => $codeType->id,
      'type' => 'code',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'draft',
      'is_system' => false,
      'content' => "<div>Hi</div>\nconsole.log('x');",
      'settings' => json_encode(['language' => 'html'], JSON_THROW_ON_ERROR),
    ]);

    $response = $this->withSession(['slot_block_expanded' => [$section->id, $child->id]])
      ->actingAs($user)
      ->get(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $response->assertOk();
    $response->assertSee('title="Move block up"', false);
    $response->assertSee('title="Move block down"', false);
    $response->assertSee('title="Edit block"', false);
    $response->assertSee('published');
    $response->assertSee('draft');
    $response->assertSee('data-wb-slot-toggle="'.$section->id.'"', false);
    $response->assertDontSee('data-wb-slot-toggle="'.$child->id.'"', false);
    $response->assertSee('data-wb-slot-parent-id="'.$section->id.'"', false);
    $response->assertSee('aria-controls="slot-block-row-'.$child->id.'"', false);
    $response->assertSee('Docs shell');
    $response->assertSee('>HTML</strong></a>', false);
    $response->assertDontSee('HTML | &lt;div&gt;Hi&lt;/div&gt;', false);
    $response->assertDontSee('Code preview');
    $response->assertSee('>1<', false);
    $response->assertSee('>-<', false);
  }

  #[Test]
  public function slot_block_reorder_endpoint_updates_sort_order_for_valid_same_parent_group(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();

    $first = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $sectionType->id,
      'type' => 'section',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $second = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $sectionType->id,
      'type' => 'section',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 1,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->postJson(route('admin.pages.slots.blocks.reorder', [$page, $pageSlot]), [
      'blocks' => [$second->id, $first->id],
    ]);

    $response->assertOk();
    $response->assertJson(['ok' => true, 'message' => 'Saved']);
    $this->assertSame(0, $second->fresh()->sort_order);
    $this->assertSame(1, $first->fresh()->sort_order);
  }

  #[Test]
  public function slot_block_reorder_endpoint_updates_public_render_order_for_reordered_top_level_blocks(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

    $first = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $plainTextType->id,
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'content' => 'Alpha block',
      'status' => 'published',
      'is_system' => false,
    ]);

    $second = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $plainTextType->id,
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 1,
      'content' => 'Beta block',
      'status' => 'published',
      'is_system' => false,
    ]);

    $third = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $plainTextType->id,
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 2,
      'content' => 'Gamma block',
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->postJson(route('admin.pages.slots.blocks.reorder', [$page, $pageSlot]), [
      'blocks' => [$third->id, $first->id, $second->id],
    ]);

    $response->assertOk();

    $orderedIds = Block::query()
      ->where('page_id', $page->id)
      ->whereNull('parent_id')
      ->where('slot_type_id', $main->id)
      ->orderBy('sort_order')
      ->orderBy('id')
      ->pluck('id')
      ->all();

    $this->assertSame([$third->id, $first->id, $second->id], $orderedIds);

    $publicResponse = $this->get(route('pages.show', 'about'));

    $publicResponse->assertOk();
    $publicResponse->assertSeeInOrder([
      'Gamma block',
      'Alpha block',
      'Beta block',
    ]);
  }

  #[Test]
  public function slot_block_reorder_endpoint_rejects_mixed_parent_groups(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
    $alertType = BlockType::query()->where('slug', 'alert')->firstOrFail();

    $section = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $sectionType->id,
      'type' => 'section',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $child = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $section->id,
      'block_type_id' => $alertType->id,
      'type' => 'alert',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->postJson(route('admin.pages.slots.blocks.reorder', [$page, $pageSlot]), [
      'blocks' => [$section->id, $child->id],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['blocks']);
  }

  #[Test]
  public function slot_block_reorder_endpoint_rejects_blocks_from_another_page(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    [$otherPage] = $this->pageWithSlot($main, 'Docs', 'docs');
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();

    $local = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $sectionType->id,
      'type' => 'section',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $foreign = Block::query()->create([
      'page_id' => $otherPage->id,
      'block_type_id' => $sectionType->id,
      'type' => 'section',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->postJson(route('admin.pages.slots.blocks.reorder', [$page, $pageSlot]), [
      'blocks' => [$local->id, $foreign->id],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['blocks']);
  }

  #[Test]
  public function slot_block_reorder_endpoint_rejects_blocks_from_another_slot(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    $sidebar = $this->slotType('sidebar', 'Sidebar', 2);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $sidebar->id,
      'sort_order' => 1,
    ]);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();

    $mainBlock = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $sectionType->id,
      'type' => 'section',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $sidebarBlock = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $sectionType->id,
      'type' => 'section',
      'source_type' => 'static',
      'slot' => 'sidebar',
      'slot_type_id' => $sidebar->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->postJson(route('admin.pages.slots.blocks.reorder', [$page, $pageSlot]), [
      'blocks' => [$mainBlock->id, $sidebarBlock->id],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['blocks']);
  }

  #[Test]
  public function stat_card_form_and_store_preserve_zero_value_in_translation_and_admin_summary(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $statCardType = BlockType::query()->where('slug', 'stat-card')->firstOrFail();

    $formResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $statCardType->id]));

    $formResponse->assertOk();
    $formResponse->assertSee('Add Block: Stat Card');
    $formResponse->assertSee('name="subtitle"', false);
    $formResponse->assertSee('name="title"', false);
    $formResponse->assertSee('name="content"', false);
    $formResponse->assertSee('name="url"', false);
    $formResponse->assertSee('This may be 0, 6, 14+, 173');

    $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $statCardType->id,
      'sort_order' => 0,
      'subtitle' => 'Dependencies',
      'title' => '0',
      'content' => 'No framework requirement for the package itself',
      'url' => '/package',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $block = Block::query()->where('page_id', $page->id)->where('type', 'stat-card')->firstOrFail();

    $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $this->assertDatabaseHas('wbcms_blocks', [
      'id' => $block->id,
      'type' => 'stat-card',
      'title' => null,
      'subtitle' => null,
      'content' => null,
      'url' => '/package',
    ]);
    $this->assertDatabaseHas('wbcms_block_text_translations', [
      'block_id' => $block->id,
      'locale_id' => $this->defaultLocale()->id,
      'title' => '0',
      'subtitle' => 'Dependencies',
      'content' => 'No framework requirement for the package itself',
    ]);
    $this->assertSame('0', $block->fresh()->editorLabel());
    $this->assertSame('0', $block->fresh()->editorSummary());

    $treeResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $treeResponse->assertOk();
    $treeResponse->assertSee('>0<', false);
  }

  #[Test]
  public function alert_form_renders_translated_fields_and_shared_variant_settings(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $alertType = BlockType::query()->where('slug', 'alert')->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $alertType->id]));

    $response->assertOk();
    $response->assertSee('Add Block: Alert');
    $response->assertSee('name="title"', false);
    $response->assertSee('name="content"', false);
    $response->assertSee('name="alert_variant"', false);
    $response->assertSee('Alert title and body copy are translated per locale. Alert variant stays shared across locales.');
  }

  #[Test]
  public function alert_store_creates_translated_copy_and_shared_variant(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $alertType = BlockType::query()->where('slug', 'alert')->firstOrFail();

    $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $alertType->id,
      'sort_order' => 0,
      'title' => 'What this page is proving',
      'content' => 'This page proves docs callouts can ship as first-class blocks.',
      'alert_variant' => 'success',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $block = Block::query()->where('page_id', $page->id)->where('type', 'alert')->firstOrFail();

    $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $this->assertDatabaseHas('wbcms_block_text_translations', [
      'block_id' => $block->id,
      'locale_id' => $this->defaultLocale()->id,
      'title' => 'What this page is proving',
      'content' => 'This page proves docs callouts can ship as first-class blocks.',
    ]);
    $this->assertSame('success', $block->fresh()->alertVariant());
  }

  #[Test]
  public function slot_block_picker_lists_all_results_in_name_order(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [
      $page,
      $pageSlot,
      'picker' => 1,
      'block_type_tab' => 'all',
    ]));

    $response->assertOk();
    $response->assertSeeInOrder([
      '>Alert</strong>',
      '>Breadcrumb</strong>',
      '>Button Link</strong>',
      '>Card</strong>',
      '>Cluster</strong>',
      '>Container</strong>',
      '>Content Header</strong>',
      '>Grid</strong>',
      '>Header</strong>',
      '>Plain Text</strong>',
      '>Rich Text</strong>',
      '>Section</strong>',
    ], false);
  }

  #[Test]
  public function slot_block_picker_search_matches_slug_name_description_and_category_terms(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);

    foreach ([
      ['term' => 'content header', 'expected' => 'Content Header'],
      ['term' => 'intro', 'expected' => 'Content Header'],
      ['term' => 'meta', 'expected' => 'Content Header'],
      ['term' => 'content_header', 'expected' => 'Content Header'],
      ['term' => 'rich', 'expected' => 'Rich Text'],
      ['term' => 'rich-text', 'expected' => 'Rich Text'],
      ['term' => 'editorial', 'expected' => 'Rich Text'],
      ['term' => 'alert', 'expected' => 'Alert'],
      ['term' => 'callout', 'expected' => 'Alert'],
      ['term' => 'pattern', 'expected' => 'Alert'],
      ['term' => 'button', 'expected' => 'Button Link'],
      ['term' => 'button link', 'expected' => 'Button Link'],
      ['term' => 'cluster', 'expected' => 'Cluster'],
      ['term' => 'section', 'expected' => 'Section'],
      ['term' => 'container', 'expected' => 'Container'],
      ['term' => 'layout', 'expected' => 'Cluster'],
    ] as $search) {
      $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [
        $page,
        $pageSlot,
        'picker' => 1,
        'block_type_search' => $search['term'],
      ]));

      $response->assertOk();
      $response->assertSee($search['expected']);
    }

    $sortedSearchResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [
      $page,
      $pageSlot,
      'picker' => 1,
      'block_type_search' => 'button',
    ]));

    $sortedSearchResponse->assertOk();
    $sortedSearchResponse->assertSee('Button Link');
    $sortedSearchResponse->assertSee('Search results');
  }

  #[Test]
  public function slot_block_picker_searches_across_tabs_even_when_a_different_tab_is_selected(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [
      $page,
      $pageSlot,
      'picker' => 1,
      'block_type_tab' => 'content',
      'block_type_search' => 'breadcrumb',
    ]));

    $response->assertOk();
    $response->assertSee('Search results');
    $response->assertSee('Showing matches across the full eligible catalog.');
    $response->assertSee('Breadcrumb');
    $response->assertDontSee('id="slot_block_type_category"', false);
    $response->assertDontSee('name="block_type_category"', false);
  }

  #[Test]
  public function slot_block_picker_reset_returns_to_the_common_tab(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $pageReturnUrl = route('admin.pages.index', ['site' => $page->site_id]);

    $filteredResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [
      $page,
      $pageSlot,
      'picker' => 1,
      'block_type_tab' => 'layout',
      'block_type_search' => 'section',
      'return_url' => $pageReturnUrl,
    ]));

    $filteredResponse->assertOk();
    $filteredResponse->assertSee('>Apply</button>', false);
    $filteredResponse->assertSee('href="'.e(route('admin.pages.slots.blocks', ['page' => $page, 'slot' => $pageSlot, 'picker' => 1, 'return_url' => $pageReturnUrl])).'"', false);

    $resetResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'return_url' => $pageReturnUrl]));
    $resetContent = $resetResponse->getContent();

    $resetResponse->assertOk();
    $resetResponse->assertSee('id="slot-block-picker-tab-common"', false);
    $this->assertNotFalse($resetContent);
    $resetCommonPanelStart = strpos($resetContent, 'id="slot-block-picker-panel-common"');
    $resetLayoutPanelStart = strpos($resetContent, 'id="slot-block-picker-panel-layout"');
    $this->assertNotFalse($resetCommonPanelStart);
    $this->assertNotFalse($resetLayoutPanelStart);
    $resetCommonPanelMarkup = substr($resetContent, $resetCommonPanelStart, $resetLayoutPanelStart - $resetCommonPanelStart);
    $this->assertStringContainsString('>Header</strong>', $resetCommonPanelMarkup);
    $this->assertStringNotContainsString('>Section</strong>', $resetCommonPanelMarkup);
  }

  #[Test]
  public function slot_block_picker_filter_form_preserves_picker_state_for_nested_picker_context(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();
    $pageReturnUrl = route('admin.pages.index', ['site' => $page->site_id]);

    $card = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'card',
      'block_type_id' => $cardType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [
      $page,
      $pageSlot,
      'picker' => 1,
      'parent_id' => $card->id,
      'block_type_tab' => 'all',
      'block_type_search' => 'button',
      'return_url' => $pageReturnUrl,
    ]));

    $response->assertOk();
    $response->assertSee('name="picker" value="1"', false);
    $response->assertSee('name="parent_id" value="'.$card->id.'"', false);
    $response->assertSee('name="block_type_tab" value="all"', false);
    $response->assertSee('value="button"', false);
    $response->assertDontSee('name="block_type_category"', false);
    $response->assertDontSee('name="block_type_sort"', false);
    $response->assertSee('>Apply</button>', false);
    $response->assertSee('href="'.e(route('admin.pages.slots.blocks', ['page' => $page, 'slot' => $pageSlot, 'picker' => 1, 'parent_id' => $card->id, 'return_url' => $pageReturnUrl])).'" class="wb-btn wb-btn-secondary">Reset</a>', false);
    $response->assertSee('data-base-url="', false);
    $response->assertSee('value="all"', false);
    $response->assertSee('picker=1', false);
    $response->assertSee('parent_id='.$card->id, false);
    $response->assertSee('block_type_tab=all', false);
    $response->assertSee('block_type_search=button', false);
    $response->assertDontSee('block_type_category=', false);
    $response->assertDontSee('block_type_sort=', false);
    $response->assertSee('return_url='.urlencode($pageReturnUrl), false);
  }

  #[Test]
  public function header_admin_form_uses_text_and_level_fields(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $headerType->id]));

    $response->assertOk();
    $response->assertSee('Add Block: Header');
    $response->assertSee('name="text"', false);
    $response->assertSee('name="level"', false);
    $response->assertSee('name="anchor"', false);
    $response->assertDontSee('Heading Text');
    $response->assertSee('Anchor ID');
  }

  #[Test]
  public function table_toc_and_quote_block_forms_open_from_the_slot_picker(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $tableType = BlockType::query()->where('slug', 'table')->firstOrFail();
    $tocType = BlockType::query()->where('slug', 'toc')->firstOrFail();
    $quoteType = BlockType::query()->where('slug', 'quote')->firstOrFail();

    $tableResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $tableType->id]));
    $tocResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $tocType->id]));
    $quoteResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $quoteType->id]));

    $tableResponse->assertOk();
    $tableResponse->assertSee('Add Block: Table');
    $tableResponse->assertSee('name="content"', false);
    $tableResponse->assertSee('name="variant"', false);

    $tocResponse->assertOk();
    $tocResponse->assertSee('Add Block: TOC');
    $tocResponse->assertSee('name="title"', false);
    $tocResponse->assertDontSee('Generic Block Form');

    $quoteResponse->assertOk();
    $quoteResponse->assertSee('Add Block: Quote');
    $quoteResponse->assertSee('name="content"', false);
    $quoteResponse->assertSee('name="variant"', false);
  }

  #[Test]
  public function code_table_toc_quote_and_header_are_seeded_as_published_catalog_entries_while_heading_stays_removed(): void
  {
    $this->seedFoundation();

    $codeType = BlockType::query()->where('slug', 'code')->firstOrFail();
    $tableType = BlockType::query()->where('slug', 'table')->firstOrFail();
    $tocType = BlockType::query()->where('slug', 'toc')->firstOrFail();
    $quoteType = BlockType::query()->where('slug', 'quote')->firstOrFail();
    $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();

    $this->assertSame('published', $codeType->status);
    $this->assertSame('content', $codeType->category);
    $this->assertSame('published', $tableType->status);
    $this->assertSame('published', $tocType->status);
    $this->assertSame('published', $quoteType->status);
    $this->assertSame('published', $headerType->status);
    $this->assertDatabaseMissing('wbcms_block_types', ['slug' => 'heading']);
  }

  #[Test]
  public function header_anchor_is_saved_and_toc_related_block_types_can_be_created(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();
    $tableType = BlockType::query()->where('slug', 'table')->firstOrFail();
    $tocType = BlockType::query()->where('slug', 'toc')->firstOrFail();
    $quoteType = BlockType::query()->where('slug', 'quote')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $headerType->id,
      'sort_order' => 0,
      'text' => 'Overview',
      'level' => 'h2',
      'anchor' => 'overview',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $tableType->id,
      'sort_order' => 1,
      'title' => 'Plans',
      'content' => "Plan | Seats\nStarter | 3",
      'variant' => 'header-row',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $tocType->id,
      'sort_order' => 2,
      'title' => 'On this page',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $quoteType->id,
      'sort_order' => 3,
      'content' => 'Quoted support text.',
      'title' => 'Editor',
      'subtitle' => 'CMS Team',
      'variant' => 'testimonial',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $this->assertDatabaseHas('wbcms_blocks', [
      'page_id' => $page->id,
      'type' => 'header',
      'url' => 'overview',
    ]);
    $this->assertDatabaseHas('wbcms_blocks', ['page_id' => $page->id, 'type' => 'table']);
    $this->assertDatabaseHas('wbcms_blocks', ['page_id' => $page->id, 'type' => 'toc']);
    $this->assertDatabaseHas('wbcms_blocks', ['page_id' => $page->id, 'type' => 'quote']);
  }

  #[Test]
  public function new_block_modal_defaults_status_to_published_and_shows_compact_block_info_fields(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $headerType->id]));

    $response->assertOk();
    $response->assertSee('Block Info');
    $response->assertSee('Block Fields');
    $response->assertSee('Settings');
    $response->assertSee('Parent Block');
    $response->assertSee('Sort Order');
    $response->assertSee('Status');
    $response->assertSee('<option value="published" selected>published</option>', false);
    $response->assertDontSee('Translation Status');
    $response->assertDontSee('Selected page');
    $response->assertDontSee('This block type defines the current builder behavior.');
    $response->assertDontSee('Runtime output comes from editorial fields authored on this block.');
    $response->assertDontSee('Runtime output comes from application data and compact block config.');
  }

  #[Test]
  public function plain_text_admin_form_uses_text_field(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $plainTextType->id]));

    $response->assertOk();
    $response->assertSee('Add Block: Plain Text');
    $response->assertSee('name="text"', false);
    $response->assertDontSee('Rich Text');
  }

  #[Test]
  public function gallery_admin_form_uses_compact_gallery_items_controls_without_legacy_title_or_description_fields(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $galleryType = BlockType::query()->where('slug', 'gallery')->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $galleryType->id]));

    $response->assertOk();
    $response->assertSee('Add Block: Gallery');
    $response->assertSee('Gallery Items');
    $response->assertDontSee('Content Fields for Gallery');
    $response->assertSee('Add Gallery Items');
    $response->assertSee('value="masonry"', false);
    $response->assertSee('Masonry');
    $response->assertSee('Viewer title');
    $response->assertSee('name="gallery_viewer_title"', false);
    $response->assertDontSee('Masonary');
    $response->assertSee('data-wb-gallery-items-count', false);
    $response->assertSee('data-wb-gallery-alt-summary', false);
    $response->assertSee('data-wb-gallery-caption-summary', false);
    $response->assertSee('data-wb-gallery-overlay-summary', false);
    $response->assertSee('Add, remove, and reorder gallery images. Per-item copy stays in each item editor.');
    $response->assertSee('data-wb-picker-panel-mode="overlay"', false);
    $response->assertSee('class="wb-modal wb-modal-lg wb-gallery-picker-modal"', false);
    $response->assertDontSee('class="wb-modal wb-modal-xl wb-gallery-picker-modal"', false);
    $this->assertMatchesRegularExpression('/data-wb-picker-owner-id="wb-picker-owner-gallery-assets-gallery_media_ids-[^"]+"/', $response->getContent());
    $response->assertSee('data-wb-picker-results-variant="compact-list"', false);
    $response->assertSee('wb-picker-results--compact', false);
    $response->assertSee('wb-gallery-picker-dialog', false);
    $response->assertSee('wb-gallery-picker-filter-region', false);
    $response->assertDontSee('wb-gallery-picker-filters-sticky', false);
    $response->assertDontSee('wb-gallery-picker-layout', false);
    $response->assertDontSee('wb-gallery-picker-results-region', false);
    $response->assertDontSee('data-wb-picker-results-region', false);
    $response->assertSee('data-wb-picker-mode="multiple"', false);
    $response->assertSee('data-wb-dismiss="modal" data-wb-picker-close', false);
    $response->assertSee('Add Selected');
    $response->assertSee('Remove All');
    $response->assertSee('data-wb-admin-dirty-form', false);
    $response->assertSee('data-wb-admin-dirty-close-confirm="Discard block changes?"', false);
    $response->assertSee('id="slot-block-editor-modal"', false);
    $response->assertSee('data-wb-slot-block-modal-autoload', false);
    $response->assertSee('data-wb-admin-autoload-overlay', false);
    $response->assertSee('data-wb-admin-close-url=', false);
    $content = $response->getContent();
    $this->assertNotFalse($content);
    $overlayRootPosition = strpos($content, 'id="wb-overlay-root"');
    $this->assertNotFalse($overlayRootPosition);
    $this->assertSame(1, preg_match('/id="(gallery_media_ids_gallery-assets-gallery_media_ids-[^"]+_picker_panel)"/', $content, $pickerPanelMatches));
    $pickerPanelId = $pickerPanelMatches[1];
    $this->assertSame(1, substr_count($content, 'id="slot-block-editor-modal"'));
    $this->assertSame(2, preg_match_all('/data-wb-picker-owner-id="wb-picker-owner-gallery-assets-gallery_media_ids-[^"]+"/', $content));
    $this->assertMatchesRegularExpression('/id="wb-overlay-root" class="wb-overlay-root">.*id="gallery_media_ids_gallery-assets-gallery_media_ids-[^"]+_picker_panel".*data-wb-picker-panel-mode="overlay".*data-wb-picker-owner-id="wb-picker-owner-gallery-assets-gallery_media_ids-[^"]+"/s', $content);
    $this->assertMatchesRegularExpression('/id="wb-overlay-root" class="wb-overlay-root">.*id="gallery_media_ids_gallery-assets-gallery_media_ids-[^"]+_picker_panel".*Add Selected/s', $content);
    $this->assertMatchesRegularExpression('/id="wb-overlay-root" class="wb-overlay-root">.*id="slot-block-editor-modal"/s', $content);
    $this->assertStringNotContainsString('<div class="wb-overlay-layer wb-overlay-layer--dialog"><div class="wb-overlay-backdrop"></div><div class="wb-modal wb-modal-xl is-open" id="slot-block-editor-modal"', str_replace(["\n", ' '], '', $content));
    $this->assertStringContainsString('class="wb-modal wb-modal-xl" id="slot-block-editor-modal"', $content);
    $this->assertStringContainsString('data-wb-slot-block-modal-autoload data-wb-admin-autoload-overlay hidden', $content);
    $this->assertStringNotContainsString('class="wb-modal wb-modal-xl is-open" id="slot-block-editor-modal"', $content);
    $adminCss = file_get_contents(public_path('cms/css/admin.css'));
    $this->assertNotFalse($adminCss);
    $this->assertStringContainsString('#wb-overlay-root [data-wb-overlay-runtime="true"][data-wb-overlay-interactive="false"]', $adminCss);
    $this->assertStringContainsString('pointer-events: none;', $adminCss);
    $this->assertStringNotContainsString('.wb-gallery-picker-overlay.wb-gallery-picker-overlay--stacked .wb-gallery-picker-modal', $adminCss);
    $this->assertStringNotContainsString('z-index: calc(var(--wb-z-modal) + 3);', $adminCss);
    $this->assertStringNotContainsString('.wb-picker-results--compact {', $adminCss);
    $this->assertStringNotContainsString('min-block-size: 0;', $adminCss);
    $this->assertStringContainsString('.wb-picker-asset-row[data-wb-asset-selected="true"]', $adminCss);
    $this->assertStringContainsString('.wb-picker-asset-row__body', $adminCss);
    $this->assertStringNotContainsString('.wb-gallery-picker-modal {', $adminCss);
    $this->assertStringNotContainsString('inline-size: min(64rem, calc(100vw - 2rem));', $adminCss);
    $this->assertStringNotContainsString('inline-size: min(72rem, calc(100vw - 2rem));', $adminCss);
    $this->assertStringNotContainsString('.wb-slot-block-picker-dialog {', $adminCss);
    $this->assertStringContainsString('.wb-gallery-picker-dialog {', $adminCss);
    $this->assertStringContainsString('max-block-size: calc(100dvh - 2rem);', $adminCss);
    $this->assertStringContainsString('.wb-gallery-picker-dialog > .wb-modal-header,', $adminCss);
    $this->assertStringContainsString('.wb-gallery-picker-dialog > .wb-gallery-picker-filter-region,', $adminCss);
    $this->assertStringContainsString('.wb-gallery-picker-filter-region {', $adminCss);
    $this->assertStringContainsString('padding: 0 var(--wb-s4) var(--wb-s3);', $adminCss);
    $this->assertStringContainsString('background: var(--wb-surface);', $adminCss);
    $this->assertStringContainsString('border-bottom: 1px solid var(--wb-border);', $adminCss);
    $this->assertStringContainsString('.wb-gallery-picker-dialog > .wb-modal-body {', $adminCss);
    $this->assertStringContainsString('overflow: hidden;', $adminCss);
    $this->assertStringContainsString('overflow: auto;', $adminCss);
    $this->assertStringContainsString('min-height: 0;', $adminCss);
    $this->assertStringNotContainsString('background: inherit;', $adminCss);
    $this->assertStringNotContainsString('wb-slot-block-picker-results-card', $adminCss);
    $this->assertStringNotContainsString('wb-slot-block-picker-results-body', $adminCss);
    $this->assertStringNotContainsString('.wb-gallery-picker-layout {', $adminCss);
    $this->assertStringNotContainsString('.wb-gallery-picker-results-region {', $adminCss);
    $this->assertStringNotContainsString('.wb-gallery-picker-filters-sticky {', $adminCss);
    $this->assertStringNotContainsString('position: sticky;', $adminCss);
    $assetPickerJs = file_get_contents(public_path('cms/js/admin/asset-picker.js'));
    $this->assertNotFalse($assetPickerJs);
    $this->assertStringContainsString('modalRuntime.open(modal, openButton || null);', $assetPickerJs);
    $this->assertStringNotContainsString('panel.hidden = false;', $assetPickerJs);
    $this->assertStringContainsString('return pickerActiveRuntimePanel(root) || root;', $assetPickerJs);
    $this->assertStringContainsString('function pickerActiveRuntimePanel(root)', $assetPickerJs);
    $this->assertStringContainsString('var context = pickerContext(root);', $assetPickerJs);
    $pageBuilderModalsJs = file_get_contents(public_path('cms/js/admin/page-builder-modals.js'));
    $this->assertNotFalse($pageBuilderModalsJs);
    $this->assertStringContainsString('data-wb-slot-block-modal-autoload', $pageBuilderModalsJs);
    $this->assertStringContainsString('data-wb-slot-block-picker-tabs', $pageBuilderModalsJs);
    $this->assertStringContainsString('data-wb-slot-block-picker-tab-input', $pageBuilderModalsJs);
    $this->assertStringContainsString('runtime.open(modal, null);', $pageBuilderModalsJs);
    $this->assertStringContainsString('panel.hidden = !isActive;', $pageBuilderModalsJs);
    $this->assertStringContainsString("panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');", $pageBuilderModalsJs);
    $this->assertStringNotContainsString('modal.classList.add(\'is-open\')', $pageBuilderModalsJs);
    $this->assertStringContainsString('data-wb-picker-error', $response->getContent());
    $this->assertStringNotContainsString('ensureLayer(\'dialog\')', $assetPickerJs);
    $response->assertDontSee('Gallery Assets');
    $response->assertDontSee('Add More Assets');
    $response->assertDontSee('Gallery is a media collection block.');
    $response->assertDontSee('Alt text, caption, and overlay copy are translated per locale.');
    $response->assertDontSee('Gallery Title');
    $response->assertDontSee('Description');
    $response->assertDontSee('name="title"', false);
    $response->assertDontSee('name="subtitle"', false);
    $response->assertDontSee('Upload to Library');
    $response->assertDontSee('data-wb-picker-summary', false);
    $response->assertDontSee('data-wb-picker-preview-grid', false);
    $response->assertDontSee('assets selected');
    $xpath = new DOMXPath((function () use ($content) {
      $document = new DOMDocument;
      @$document->loadHTML($content);

      return $document;
    })());
    $pickerPanelQuery = '//*[@id="'.$pickerPanelId.'"]';
    $dialog = $xpath->query($pickerPanelQuery.'//*[contains(concat(" ", normalize-space(@class), " "), " wb-gallery-picker-dialog ")]')->item(0);
    $modalHeader = $xpath->query($pickerPanelQuery.'//*[contains(concat(" ", normalize-space(@class), " "), " wb-modal-header ")]')->item(0);
    $filterRegion = $xpath->query($pickerPanelQuery.'//*[contains(concat(" ", normalize-space(@class), " "), " wb-gallery-picker-filter-region ")]')->item(0);
    $modalBody = $xpath->query($pickerPanelQuery.'//*[contains(concat(" ", normalize-space(@class), " "), " wb-modal-body ")]')->item(0);
    $modalFooter = $xpath->query($pickerPanelQuery.'//*[contains(concat(" ", normalize-space(@class), " "), " wb-modal-footer ")]')->item(0);
    $filtersCard = $xpath->query('//*[@data-wb-picker-filters-card]')->item(0);
    $pickerGrid = $xpath->query('//*[@data-wb-picker-grid]')->item(0);
    $this->assertNotNull($dialog);
    $this->assertNotNull($modalHeader);
    $this->assertNotNull($filterRegion);
    $this->assertNotNull($modalBody);
    $this->assertNotNull($modalFooter);
    $this->assertNotNull($filtersCard);
    $this->assertNotNull($pickerGrid);
    $this->assertSame('wb-modal-dialog wb-gallery-picker-dialog', $dialog->getAttribute('class'));
    $this->assertSame('wb-gallery-picker-filter-region', $filterRegion->getAttribute('class'));
    $this->assertSame('wb-card wb-card-muted', $filtersCard->getAttribute('class'));
    $this->assertSame(0, $xpath->query('.//*[@data-wb-picker-results-region]', $modalBody)->length);
    $this->assertSame(0, $xpath->query('.//*[@data-wb-picker-filters-card]', $modalBody)->length);
    $this->assertSame(1, $xpath->query('.//*[@data-wb-picker-grid]', $modalBody)->length);
    $this->assertSame(1, $xpath->query('.//*[@data-wb-picker-empty]', $modalBody)->length);
    $this->assertSame(1, $xpath->query('.//*[@data-wb-picker-error]', $modalBody)->length);
    $this->assertSame(0, $xpath->query('.//*[@data-wb-picker-filters-card]', $modalHeader)->length);
    $this->assertSame(1, $xpath->query('.//*[@data-wb-picker-filters-card]', $filterRegion)->length);
    $this->assertSame(0, $xpath->query('.//*[@data-wb-picker-grid]', $filterRegion)->length);
    $this->assertSame(0, $xpath->query('.//*[@data-wb-picker-filters-card]', $modalFooter)->length);
    $this->assertSame(0, $xpath->query('.//*[@data-wb-picker-grid]', $modalFooter)->length);
    $this->assertSame(1, $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " wb-modal-header ")]', $dialog)->length);
    $this->assertSame(1, $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " wb-gallery-picker-filter-region ")]', $dialog)->length);
    $this->assertSame(1, $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " wb-modal-body ")]', $dialog)->length);
    $this->assertSame(1, $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " wb-modal-footer ")]', $dialog)->length);
    $this->assertSame(1, $xpath->query('./following-sibling::*[1][contains(concat(" ", normalize-space(@class), " "), " wb-gallery-picker-filter-region ")]', $modalHeader)->length);
    $this->assertSame(1, $xpath->query('./following-sibling::*[1][contains(concat(" ", normalize-space(@class), " "), " wb-modal-body ")]', $filterRegion)->length);
    $this->assertSame(1, $xpath->query('./following-sibling::*[1][contains(concat(" ", normalize-space(@class), " "), " wb-modal-footer ")]', $modalBody)->length);
    $this->assertMatchesRegularExpression('/<div class="wb-modal-dialog wb-gallery-picker-dialog">\s*<div class="wb-modal-header">.*<div class="wb-gallery-picker-filter-region">.*<div class="wb-modal-body wb-stack wb-gap-3">.*<div class="wb-modal-footer wb-flex wb-justify-between wb-gap-2">/s', $content);
  }

  #[Test]
  public function image_admin_form_uses_nested_media_picker_modal_and_single_selected_summary(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $imageType = BlockType::query()->where('slug', 'image')->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $imageType->id]));

    $response->assertOk();
    $response->assertSee('Add Block: Image');
    $response->assertSee('Choose from Media');
    $response->assertSee('data-wb-picker-panel-mode="overlay"', false);
    $response->assertSee('id="asset_id_picker_panel"', false);
    $response->assertSee('data-wb-picker-owner-id="wb-picker-owner-asset_id"', false);
    $response->assertSee('data-wb-picker-results-variant="compact-list"', false);
    $response->assertSee('wb-picker-results--compact', false);
    $response->assertSee('data-wb-picker-selector-card', false);
    $response->assertSee('data-wb-picker-selector-card-title', false);
    $response->assertSee('Media Asset', false);
    $response->assertSee('data-wb-picker-selector-help', false);
    $response->assertSee('data-wb-picker-filters-card', false);
    $response->assertSee('data-wb-picker-filters', false);
    $response->assertSee('Search', false);
    $response->assertSee('Folder', false);
    $response->assertSee('Kind', false);
    $response->assertDontSee('Upload to Library');
    $response->assertSee('data-wb-dismiss="modal" data-wb-picker-close', false);
    $response->assertDontSee('Close Panel', false);
    $content = $response->getContent();
    $this->assertNotFalse($content);
    $this->assertMatchesRegularExpression('/id="wb-overlay-root" class="wb-overlay-root">.*id="asset_id_picker_panel".*data-wb-picker-panel-mode="overlay".*data-wb-picker-owner-id="wb-picker-owner-asset_id"/s', $content);
    $this->assertMatchesRegularExpression('/data-wb-picker-selector-card.*Media Asset.*No asset selected.*Choose an internal image asset for this block\./s', $content);
    $this->assertMatchesRegularExpression('/data-wb-picker-filters-card.*Search.*Folder.*Kind/s', $content);
    $document = new DOMDocument;
    @$document->loadHTML($content);
    $xpath = new DOMXPath($document);
    $selectorCard = $xpath->query('//*[@data-wb-picker-selector-card]')->item(0);
    $this->assertNotNull($selectorCard);
    $this->assertSame(1, $xpath->query('.//button[@data-wb-picker-open and normalize-space()="Choose from Media"]', $selectorCard)->length);
    $this->assertSame(1, $xpath->query('.//button[@data-wb-picker-clear and normalize-space()="Remove"]', $selectorCard)->length);
    $this->assertSame(1, $xpath->query('.//strong[normalize-space()="No asset selected"]', $selectorCard)->length);
    $this->assertSame(0, $xpath->query('.//label[@for="subtitle"]', $selectorCard)->length);
    $this->assertSame(0, $xpath->query('.//label[@for="url"]', $selectorCard)->length);
    $this->assertSame(0, $xpath->query('.//label[@for="title"]', $selectorCard)->length);
    $this->assertSame(1, $xpath->query('//label[@for="subtitle"]')->length);
    $this->assertSame(1, $xpath->query('//label[@for="url"]')->length);
    $this->assertSame(1, $xpath->query('//label[@for="title"]')->length);
    $this->assertStringNotContainsString('class="wb-grid wb-grid-3 wb-picker-results"', $content);
    $this->assertStringNotContainsString('data-wb-picker-preview-grid', $content);
    $this->assertStringNotContainsString('data-wb-picker-preview data-wb-picker-preview-id=', $content);
  }

  #[Test]
  public function gallery_picker_renders_selectable_image_rows_for_default_image_filter_and_folder_options(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $galleryType = BlockType::query()->where('slug', 'gallery')->firstOrFail();
    $folder = MediaFolder::query()->create(['name' => 'Gallery Folder']);

    Media::query()->create([
      'folder_id' => $folder->id,
      'disk' => 'public',
      'path' => 'media/images/gallery-picker-image.jpg',
      'filename' => 'gallery-picker-image.jpg',
      'original_name' => 'gallery-picker-image.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1200,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Picker Image',
    ]);

    Media::query()->create([
      'disk' => 'public',
      'path' => 'media/documents/gallery-picker-guide.pdf',
      'filename' => 'gallery-picker-guide.pdf',
      'original_name' => 'gallery-picker-guide.pdf',
      'extension' => 'pdf',
      'mime_type' => 'application/pdf',
      'size' => 1200,
      'kind' => 'document',
      'visibility' => 'public',
      'title' => 'Picker Guide',
    ]);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $galleryType->id]));

    $response->assertOk();
    $response->assertSee('id="gallery_media_ids_asset_kind"', false);
    $response->assertSee('<option value="image" selected>Image</option>', false);
    $response->assertSee('<option value="">All folders</option>', false);
    $response->assertSee('Gallery Folder (1)', false);
    $response->assertSee('Picker Image', false);
    $response->assertSee('gallery-picker-image.jpg', false);
    $response->assertSee('data-wb-asset-kind="image"', false);
    $response->assertSee('data-wb-asset-folder-id="'.$folder->id.'"', false);
    $response->assertSee('data-wb-asset-toggle', false);
    $response->assertDontSee('wb-skeleton', false);
    $response->assertDontSee('skeleton', false);
    $response->assertDontSee('Picker Guide', false);
    $response->assertDontSee('gallery-picker-guide.pdf', false);
  }

  #[Test]
  public function gallery_picker_shows_real_empty_state_when_no_images_match_default_kind_filter(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $galleryType = BlockType::query()->where('slug', 'gallery')->firstOrFail();

    Media::query()->create([
      'disk' => 'public',
      'path' => 'media/documents/gallery-only-document.pdf',
      'filename' => 'gallery-only-document.pdf',
      'original_name' => 'gallery-only-document.pdf',
      'extension' => 'pdf',
      'mime_type' => 'application/pdf',
      'size' => 1200,
      'kind' => 'document',
      'visibility' => 'public',
      'title' => 'Only Document',
    ]);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $galleryType->id]));

    $response->assertOk();
    $response->assertSee('class="wb-empty" data-wb-picker-empty', false);
    $response->assertSee('No matching images');
    $response->assertSee('Use Admin -&gt; Media to upload an image, or adjust the search or folder filter to find one in the shared media library.', false);
    $response->assertDontSee('data-wb-asset-card', false);
    $response->assertDontSee('wb-skeleton', false);
    $response->assertDontSee('skeleton', false);
    $response->assertSee('data-wb-picker-error', false);
  }

  #[Test]
  public function gallery_edit_form_targets_existing_item_modals_by_media_id_and_keeps_real_hidden_field_names(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page] = $this->pageWithSlot($main);
    $galleryType = BlockType::query()->where('slug', 'gallery')->firstOrFail();
    $asset = Asset::query()->create([
      'disk' => 'public',
      'path' => 'media/images/gallery-existing-item.jpg',
      'filename' => 'gallery-existing-item.jpg',
      'original_name' => 'gallery-existing-item.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1200,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Existing gallery item',
      'alt_text' => 'Existing fallback alt',
    ]);

    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'gallery',
      'block_type_id' => $galleryType->id,
      'source_type' => 'asset',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $blockMedia = $block->blockMedia()->create([
      'media_id' => $asset->id,
      'role' => 'gallery_item',
      'position' => 0,
    ]);

    $blockMedia->galleryItemTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'alt_text' => 'Saved alt text',
      'caption' => 'Saved caption',
      'overlay_title' => 'Saved overlay title',
      'overlay_text' => 'Saved overlay text',
    ]);

    $response = $this->actingAs($user)->followingRedirects()->get(route('admin.blocks.edit', $block));

    $response->assertOk();
    $response->assertSee('data-wb-gallery-item-row data-media-id="'.$asset->id.'"', false);
    $response->assertSee('name="gallery_items[0][media_id]"', false);
    $response->assertSee('name="gallery_items[0][caption]" value="Saved caption"', false);
    $response->assertSee('name="gallery_items[0][alt_text]" value="Saved alt text"', false);
    $response->assertSee('name="gallery_items[0][overlay_title]" value="Saved overlay title"', false);
    $response->assertSee('name="gallery_items[0][overlay_text]" value="Saved overlay text"', false);
    $response->assertSee('data-wb-target="#gallery-item-modal-gallery-'.$asset->id.'"', false);
    $response->assertSee('id="gallery-item-modal-gallery-'.$asset->id.'"', false);
    $response->assertSee('data-media-id="'.$asset->id.'"', false);
    $response->assertSee('data-wb-gallery-edit-item', false);
    $response->assertSee('data-wb-gallery-caption-summary', false);
    $response->assertSee('Saved caption', false);
    $content = $response->getContent();
    $this->assertNotFalse($content);
    $this->assertStringContainsString('data-wb-picker-owner-id="wb-picker-owner-b'.$block->id.'-gallery_media_ids"', $content);
    $this->assertSame(1, substr_count($content, 'id="gallery_media_ids_b'.$block->id.'-gallery_media_ids_picker_panel"'));
    $this->assertSame(1, substr_count($content, 'id="slot-block-editor-modal"'));
    $this->assertMatchesRegularExpression('/id="wb-overlay-root" class="wb-overlay-root">.*id="gallery_media_ids_b'.$block->id.'-gallery_media_ids_picker_panel".*data-wb-picker-panel-mode="overlay"/s', $content);
    $this->assertMatchesRegularExpression('/id="wb-overlay-root" class="wb-overlay-root">.*id="slot-block-editor-modal"/s', $content);
  }

  #[Test]
  public function representative_admin_modals_use_standard_close_contract_and_dirty_form_markers(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $codeType = BlockType::query()->where('slug', 'code')->firstOrFail();

    $slotBlockResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $codeType->id]));
    $pageImportResponse = $this->actingAs($user)->get(route('admin.pages.index', ['modal' => 'page-import']));

    $slotBlockResponse->assertOk();
    $slotBlockResponse->assertSee('id="slot-block-editor-modal"', false);
    $slotBlockResponse->assertSee('data-wb-slot-block-modal-autoload', false);
    $slotBlockResponse->assertSee('data-wb-admin-autoload-overlay', false);
    $slotBlockResponse->assertSee('data-wb-admin-close-url=', false);
    $slotBlockResponse->assertSee('class="wb-modal-close" data-wb-dismiss="modal"', false);
    $slotBlockResponse->assertSee('data-wb-admin-dirty-form', false);
    $slotBlockResponse->assertSee('data-wb-admin-dirty-close-confirm="Discard block changes?"', false);
    $slotBlockResponse->assertDontSee('window.confirm(', false);

    $pageImportResponse->assertOk();
    $pageImportResponse->assertSee('id="page-import-modal"', false);
    $pageImportResponse->assertSee('data-wb-admin-autoload-overlay', false);
    $pageImportResponse->assertSee('data-wb-admin-close-url=', false);
    $pageImportResponse->assertSee('class="wb-modal-close" data-wb-dismiss="modal"', false);
    $pageImportResponse->assertSee('class="wb-btn wb-btn-secondary" data-wb-dismiss="modal"', false);
    $pageImportResponse->assertSee('data-wb-admin-dirty-form', false);
    $pageImportResponse->assertSee('data-wb-admin-dirty-close-confirm="Discard import changes?"', false);

    $adminCoreJs = file_get_contents(public_path('cms/js/admin/core.js'));
    $this->assertNotFalse($adminCoreJs);
    $this->assertStringContainsString('document.querySelectorAll(\'[data-wb-admin-autoload-overlay]\')', $adminCoreJs);
    $this->assertStringContainsString('modalRuntime.open(overlay, null);', $adminCoreJs);
    $this->assertStringContainsString('wb:overlay:close-request', $adminCoreJs);
    $this->assertStringContainsString('data-wb-admin-dirty-close-confirm-action', $adminCoreJs);
    $this->assertStringContainsString('Keep editing', $adminCoreJs);
    $this->assertStringContainsString('Close without saving', $adminCoreJs);
    $this->assertStringContainsString('Unsaved changes will be lost', $adminCoreJs);
    $this->assertStringContainsString('overlay.dataset.wbAdminForceClose = \'true\';', $adminCoreJs);
    $this->assertStringContainsString('if (overlay.dataset && overlay.dataset.wbAdminForceClose === \'true\')', $adminCoreJs);
    $this->assertStringNotContainsString('function syncAdminModalBackdrops()', $adminCoreJs);
    $this->assertStringNotContainsString('.wb-overlay-backdrop[data-wb-overlay-backdrop="true"]', $adminCoreJs);
    $this->assertStringNotContainsString('MutationObserver', $adminCoreJs);
    $this->assertStringNotContainsString('data-wb-admin-hidden-backdrop', $adminCoreJs);
    $this->assertStringNotContainsString('backdrop.hidden = !isActiveBackdrop;', $adminCoreJs);
    $this->assertStringNotContainsString('window.confirm(', $adminCoreJs);
  }

  #[Test]
  public function layout_block_admin_forms_show_expected_fields_and_settings_controls(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
    $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
    $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();

    $sectionResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $sectionType->id]));
    $containerResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $containerType->id]));
    $clusterResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $clusterType->id]));

    $sectionResponse->assertOk()->assertSee('name="name"', false)->assertSee('name="spacing"', false)->assertSee('Admin-only label used in the block tree and parent selector.')->assertSee('This layout block has no public content fields.')->assertDontSee('name="text"', false);
    $containerResponse->assertOk()->assertSee('name="name"', false)->assertSee('name="width"', false)->assertSee('name="container_flow"', false)->assertSee('Container owns width only.')->assertSee('This layout block has no public content fields.')->assertDontSee('name="text"', false);
    $clusterResponse->assertOk()
      ->assertSee('name="name"', false)
      ->assertSee('name="cluster_width"', false)
      ->assertSee('name="cluster_justify"', false)
      ->assertSee('name="cluster_align"', false)
      ->assertSee('name="cluster_wrap"', false)
      ->assertSee('name="cluster_gap"', false)
      ->assertSee('Admin-only label used in the block tree and parent selector.')
      ->assertSee('This layout block has no public content fields.')
      ->assertDontSee('name="text"', false);
  }

  #[Test]
  public function navbar_brand_accepts_logo_only_with_accessible_label_but_rejects_missing_logo_and_title(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    [$page, $pageSlot] = $this->pageWithSlot($header);
    $navbarType = BlockType::query()->where('slug', 'sticky-navbar')->firstOrFail();
    $brandType = BlockType::query()->where('slug', 'navbar-brand')->firstOrFail();

    $asset = Asset::query()->create([
      'disk' => 'public',
      'path' => 'media/images/navbar-brand-logo-only.png',
      'filename' => 'navbar-brand-logo-only.png',
      'original_name' => 'navbar-brand-logo-only.png',
      'extension' => 'png',
      'mime_type' => 'image/png',
      'size' => 1200,
      'kind' => 'image',
      'visibility' => 'public',
    ]);

    $navbar = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'sticky-navbar',
      'block_type_id' => $navbarType->id,
      'source_type' => 'static',
      'slot' => 'header',
      'slot_type_id' => $header->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => true,
    ]);

    $formResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $brandType->id]));

    $formResponse->assertOk();
    $formResponse->assertSee('name="navbar_brand_aria_label"', false);
    $formResponse->assertDontSee('name="title" class="wb-input" type="text" value="" required', false);

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $header->id,
      'block_type_id' => $brandType->id,
      'parent_id' => $navbar->id,
      'sort_order' => 0,
      'title' => '',
      'subtitle' => '',
      'asset_id' => $asset->id,
      'url' => '/',
      'target' => '_self',
      'navbar_brand_aria_label' => 'Fklavye Web Services',
      'status' => 'published',
    ])->assertSessionDoesntHaveErrors();

    $brand = Block::query()->where('page_id', $page->id)->where('type', 'navbar-brand')->firstOrFail();

    $this->assertSame('Fklavye Web Services', $brand->navbarBrandAriaLabel());
    $this->assertNull($brand->fresh()->title);

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $header->id,
      'block_type_id' => $brandType->id,
      'parent_id' => $navbar->id,
      'sort_order' => 1,
      'title' => '',
      'subtitle' => '',
      'url' => '/',
      'target' => '_self',
      'navbar_brand_aria_label' => '',
      'status' => 'published',
    ])->assertSessionHasErrors('title');
  }

  #[Test]
  public function header_and_plain_text_settings_controls_render_in_the_settings_tab(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

    $headerResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $headerType->id]));
    $plainTextResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $plainTextType->id]));

    $headerResponse->assertOk()->assertSee('Settings')->assertSee('name="alignment"', false)->assertSee('Applies shipped WebBlocks UI text alignment classes only.');
    $plainTextResponse->assertOk()->assertSee('Settings')->assertSee('name="alignment"', false)->assertSee('Applies shipped WebBlocks UI text alignment classes only.');
  }

  #[Test]
  public function content_header_form_renders_editor_friendly_fields_and_settings(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $contentHeaderType = BlockType::query()->where('slug', 'content_header')->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $contentHeaderType->id]));

    $response->assertOk();
    $response->assertSee('Add Block: Content Header');
    $response->assertSee('name="title"', false);
    $response->assertSee('name="intro_text"', false);
    $response->assertSee('name="meta_items[]"', false);
    $response->assertDontSee('name="title_level"', false);
    $response->assertSee('name="alignment"', false);
    $response->assertSee('Title, intro text, and meta items are translated per locale. Alignment stays shared across locales.');
  }

  #[Test]
  public function button_link_form_renders_translated_label_and_shared_link_settings(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $buttonLinkType = BlockType::query()->where('slug', 'button_link')->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $buttonLinkType->id]));

    $response->assertOk();
    $response->assertSee('Add Block: Button Link');
    $response->assertSee('name="label"', false);
    $response->assertSee('name="url"', false);
    $response->assertSee('name="target"', false);
    $response->assertSee('name="variant"', false);
    $response->assertSee('Button label is translated per locale. URL, target, and variant stay shared across locales.');
    $response->assertSee('Applies shipped WebBlocks UI button classes only.');
  }

  #[Test]
  public function layout_block_name_is_saved_and_rendered_in_admin_tree(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
    $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
    $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $sectionType->id,
      'sort_order' => 0,
      'name' => 'Hero area',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $section = Block::query()->where('page_id', $page->id)->where('type', 'section')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $containerType->id,
      'parent_id' => $section->id,
      'sort_order' => 0,
      'name' => 'Hero content',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $container = Block::query()->where('page_id', $page->id)->where('type', 'container')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'parent_id' => $container->id,
      'block_type_id' => $clusterType->id,
      'sort_order' => 0,
      'name' => 'Action row',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $cluster = Block::query()->where('page_id', $page->id)->where('type', 'cluster')->firstOrFail();

    $this->assertSame('Hero area', $section->fresh()->setting('layout_name'));
    $this->assertSame('Hero content', $container->fresh()->setting('layout_name'));
    $this->assertSame('Action row', $cluster->fresh()->setting('layout_name'));

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $response->assertOk();
    $response->assertSee('Section');
    $response->assertSee('Container');
    $response->assertSee('Cluster');
    $response->assertSee('>Hero area<', false);
    $response->assertSee('>Hero content<', false);
    $response->assertSee('>Action row<', false);
    $response->assertDontSee('— Section');
    $response->assertDontSee('— Container');
  }

  #[Test]
  public function cluster_settings_are_saved_with_full_layout_controls(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $clusterType->id,
      'sort_order' => 0,
      'name' => 'Navbar row',
      'cluster_width' => 'full',
      'cluster_justify' => 'between',
      'cluster_align' => 'end',
      'cluster_wrap' => 'nowrap',
      'cluster_gap' => 'lg',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $cluster = Block::query()->where('page_id', $page->id)->where('type', 'cluster')->firstOrFail();

    $this->assertSame('Navbar row', $cluster->fresh()->setting('layout_name'));
    $this->assertSame('full', $cluster->fresh()->setting('width'));
    $this->assertSame('between', $cluster->fresh()->setting('alignment'));
    $this->assertSame('end', $cluster->fresh()->setting('items_alignment'));
    $this->assertSame('nowrap', $cluster->fresh()->setting('wrap'));
    $this->assertSame('lg', $cluster->fresh()->setting('gap'));
  }

  #[Test]
  public function parent_dropdown_lists_only_container_blocks_and_excludes_current_block_and_descendants(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
    $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
    $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();
    $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

    $section = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'section',
      'block_type_id' => $sectionType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'settings' => json_encode(['layout_name' => 'Hero area'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $container = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $section->id,
      'type' => 'container',
      'block_type_id' => $containerType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'settings' => json_encode(['layout_name' => 'Hero content'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $cluster = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'cluster',
      'block_type_id' => $clusterType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 1,
      'settings' => json_encode(['layout_name' => 'Actions'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $header = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'header',
      'block_type_id' => $headerType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'variant' => 'h2',
      'status' => 'published',
      'is_system' => false,
    ]);

    Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'plain_text',
      'block_type_id' => $plainTextType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 1,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'edit' => $container->id]));

    $response->assertOk();
    $response->assertSee('<option value="">No parent</option>', false);
    $response->assertSee('Section: Hero area');
    $response->assertDontSee('<option value="'.$header->id.'">', false);
    $response->assertDontSee('>Header</option>', false);
    $response->assertDontSee('>Plain Text</option>', false);
    $response->assertDontSee('<option value="'.$container->id.'"', false);

    $headerResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'edit' => $header->id]));

    $headerResponse->assertOk();
    $headerResponse->assertSee('Section: Hero area');
    $headerResponse->assertSee('— Container: Hero content');
    $headerResponse->assertSee('— — Cluster: Actions');
    $headerResponse->assertDontSee('>Header</option>', false);
    $headerResponse->assertDontSee('>Plain Text</option>', false);
    $headerResponse->assertDontSee('<option value="'.$header->id.'">', false);
    $headerResponse->assertDontSee('<option value="'.$plainTextType->id.'">', false);
  }

  #[Test]
  public function cluster_edit_modal_lists_eligible_card_footer_parent_candidates_and_allows_move_under_card_footer(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
    $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
    $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();
    $cardFooterType = BlockType::query()->where('slug', 'card_footer')->firstOrFail();
    $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();
    $buttonLinkType = BlockType::query()->where('slug', 'button_link')->firstOrFail();
    $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();

    $section = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'section',
      'block_type_id' => $sectionType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'settings' => json_encode(['layout_name' => 'Page Header'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $container = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $section->id,
      'type' => 'container',
      'block_type_id' => $containerType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'settings' => json_encode(['layout_name' => 'Page Header'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $card = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'card',
      'block_type_id' => $cardType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $card->textTranslations()->create([
      'locale_id' => $this->defaultLocale()->id,
      'title' => 'WebBlocks UI - UI building blocks for humans and AI.',
    ]);

    $cardFooter = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $card->id,
      'type' => 'card_footer',
      'block_type_id' => $cardFooterType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $cluster = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'cluster',
      'block_type_id' => $clusterType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 1,
      'settings' => json_encode(['layout_name' => 'Actions'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $button = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $cluster->id,
      'type' => 'button_link',
      'block_type_id' => $buttonLinkType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'variant' => 'primary',
      'settings' => json_encode(['url' => '/start-here', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    $button->textTranslations()->create([
      'locale_id' => $this->defaultLocale()->id,
      'title' => 'Start Here',
    ]);

    Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'header',
      'block_type_id' => $headerType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 2,
      'variant' => 'h2',
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'edit' => $cluster->id]));

    $response->assertOk();
    $response->assertSee('Section: Page Header');
    $response->assertSee('— Container: Page Header');
    $response->assertSee('— — Card', false);
    $response->assertSee('— — — Card Footer', false);
    $response->assertDontSee('Cluster: Actions</option>', false);
    $response->assertDontSee('Button Link: Start Here</option>', false);

    $updateResponse = $this->actingAs($user)->put(route('admin.blocks.update', $cluster), [
      'page_id' => $page->id,
      'parent_id' => $cardFooter->id,
      'block_type_id' => $clusterType->id,
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'name' => 'Actions',
      'status' => 'published',
      '_slot_block_mode' => 'edit',
      '_slot_block_id' => $cluster->id,
    ]);

    $updateResponse->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $this->assertSame($cardFooter->id, $cluster->fresh()->parent_id);

    $movedResponse = $this->get(route('pages.show', 'about'));

    $movedResponse->assertOk();
    $movedResponse->assertSeeInOrder([
      '<article class="wb-card" data-wb-public-block-type="card">',
      '<div class="wb-card-footer" data-wb-public-block-type="card-footer">',
      '<div class="wb-cluster" data-wb-public-block-type="cluster">',
      '<a href="/start-here" class="wb-btn wb-btn-primary">Start Here</a>',
    ], false);
  }

  #[Test]
  public function card_footer_still_appears_as_cluster_parent_candidate_when_container_metadata_is_stale(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
    $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();
    $cardFooterType = BlockType::query()->where('slug', 'card_footer')->firstOrFail();
    $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();

    $container = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'container',
      'block_type_id' => $containerType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'settings' => json_encode(['layout_name' => 'Page Header'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $card = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'card',
      'block_type_id' => $cardType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $cardFooter = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $card->id,
      'type' => 'card_footer',
      'block_type_id' => $cardFooterType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    BlockType::query()->whereKey($cardType->id)->update(['is_container' => false]);

    $cluster = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'cluster',
      'block_type_id' => $clusterType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 1,
      'settings' => json_encode(['layout_name' => 'Actions'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'edit' => $cluster->id]));

    $response->assertOk();
    $response->assertSee('Card Footer');
  }

  #[Test]
  public function parent_dropdown_excludes_card_when_it_cannot_accept_the_edited_block_type(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
    $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();
    $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();

    $container = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'container',
      'block_type_id' => $containerType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'settings' => json_encode(['layout_name' => 'Page Header'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $card = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'card',
      'block_type_id' => $cardType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $header = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'header',
      'block_type_id' => $headerType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 1,
      'variant' => 'h2',
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'edit' => $header->id]));

    $response->assertOk();
    $response->assertSee('Container: Page Header');
    $response->assertDontSee('Card</option>', false);
  }

  #[Test]
  public function slot_block_picker_filters_child_block_types_for_card_context(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();

    $card = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'card',
      'block_type_id' => $cardType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'parent_id' => $card->id]));
    $content = $response->getContent();

    $response->assertOk();
    $response->assertSee('Showing block types allowed inside Card.');
    $response->assertSee('id="slot-block-picker-tab-layout"', false);
    $this->assertStringContainsString('id="slot-block-picker-tab-layout"', $content);
    $this->assertMatchesRegularExpression('/id="slot-block-picker-tab-layout"[^>]*aria-selected="true"/s', $content);
    $this->assertMatchesRegularExpression('/<div class="wb-tabs-panel is-active wb-stack wb-gap-0" id="slot-block-picker-panel-layout"[^>]*aria-hidden="false"/s', $content);
    $response->assertSee('>Card Header</strong>', false);
    $response->assertSee('>Card Body</strong>', false);
    $response->assertSee('>Card Footer</strong>', false);
    $response->assertDontSee('data-block-type-slug="rich-text"', false);
    $this->assertNotFalse($content);
    $commonPanelStart = strpos($content, 'id="slot-block-picker-panel-common"');
    $layoutPanelStart = strpos($content, 'id="slot-block-picker-panel-layout"');
    $this->assertNotFalse($commonPanelStart);
    $this->assertNotFalse($layoutPanelStart);
    $commonPanelMarkup = substr($content, $commonPanelStart, $layoutPanelStart - $commonPanelStart);
    $this->assertStringNotContainsString('>Button Link</strong>', $commonPanelMarkup);
    $this->assertStringNotContainsString('>Cluster</strong>', $commonPanelMarkup);
    $this->assertStringNotContainsString('>Header</strong>', $commonPanelMarkup);
    $this->assertStringNotContainsString('>Plain Text</strong>', $commonPanelMarkup);
    $this->assertStringNotContainsString('>Rich Text</strong>', $commonPanelMarkup);
    $this->assertStringNotContainsString('>Content Header</strong>', $commonPanelMarkup);
    $this->assertStringNotContainsString('>Grid</strong>', $commonPanelMarkup);

    $allResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'parent_id' => $card->id, 'block_type_tab' => 'all']));

    $allResponse->assertOk();
    $allResponse->assertSee('>Card Header</strong>', false);
    $allResponse->assertSee('>Card Body</strong>', false);
    $allResponse->assertSee('>Card Footer</strong>', false);
    $allResponse->assertDontSee('>Cluster</strong>', false);
    $allResponse->assertDontSee('>Button Link</strong>', false);
  }

  #[Test]
  public function top_level_picker_keeps_card_regions_hidden_while_card_parent_picker_defaults_to_visible_region_choices(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();

    $topLevelResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1]));

    $topLevelResponse->assertOk();
    $topLevelResponse->assertSee('>Card</strong>', false);
    $topLevelResponse->assertDontSee('>Card Header</strong>', false);
    $topLevelResponse->assertDontSee('>Card Body</strong>', false);
    $topLevelResponse->assertDontSee('>Card Footer</strong>', false);
    $topLevelResponse->assertSee('name="block_type_tab" value="common"', false);

    $card = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'card',
      'block_type_id' => $cardType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $section = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'section',
      'block_type_id' => $sectionType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 1,
      'status' => 'published',
      'is_system' => false,
    ]);

    $cardChildResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'parent_id' => $card->id]));

    $cardChildResponse->assertOk();
    $cardChildResponse->assertSee('Showing block types allowed inside Card.');
    $cardChildContent = $cardChildResponse->getContent();
    $this->assertNotFalse($cardChildContent);
    $this->assertMatchesRegularExpression('/id="slot-block-picker-tab-layout"[^>]*aria-selected="true"/s', $cardChildContent);
    $this->assertMatchesRegularExpression('/<div class="wb-tabs-panel is-active wb-stack wb-gap-0" id="slot-block-picker-panel-layout"[^>]*aria-hidden="false"/s', $cardChildContent);
    $cardChildResponse->assertSee('>Card Header</strong>', false);
    $cardChildResponse->assertSee('>Card Body</strong>', false);
    $cardChildResponse->assertSee('>Card Footer</strong>', false);

    $sectionChildResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'parent_id' => $section->id]));

    $sectionChildResponse->assertOk();
    $sectionChildResponse->assertSee('Showing block types allowed inside Section.');
    $sectionChildResponse->assertDontSee('>Card Header</strong>', false);
    $sectionChildResponse->assertDontSee('>Card Body</strong>', false);
    $sectionChildResponse->assertDontSee('>Card Footer</strong>', false);
  }

  #[Test]
  public function card_region_and_nested_action_blocks_can_be_created_and_are_visible_in_admin_tree(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
    $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
    $contentHeaderType = BlockType::query()->where('slug', 'content_header')->firstOrFail();
    $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();
    $cardFooterType = BlockType::query()->where('slug', 'card_footer')->firstOrFail();
    $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();
    $buttonLinkType = BlockType::query()->where('slug', 'button_link')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $sectionType->id,
      'sort_order' => 0,
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $section = Block::query()->where('page_id', $page->id)->where('type', 'section')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'parent_id' => $section->id,
      'block_type_id' => $containerType->id,
      'sort_order' => 0,
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $container = Block::query()->where('page_id', $page->id)->where('type', 'container')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'parent_id' => $container->id,
      'block_type_id' => $contentHeaderType->id,
      'sort_order' => 0,
      'title' => 'Documentation',
      'intro_text' => 'Intro',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'parent_id' => $container->id,
      'block_type_id' => $cardType->id,
      'sort_order' => 1,
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $card = Block::query()->where('page_id', $page->id)->where('type', 'card')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'parent_id' => $card->id,
      'block_type_id' => $cardFooterType->id,
      'sort_order' => 0,
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $cardFooter = Block::query()->where('page_id', $page->id)->where('type', 'card_footer')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'parent_id' => $cardFooter->id,
      'block_type_id' => $clusterType->id,
      'sort_order' => 0,
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $cluster = Block::query()->where('page_id', $page->id)->where('type', 'cluster')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'parent_id' => $cluster->id,
      'block_type_id' => $buttonLinkType->id,
      'sort_order' => 0,
      'label' => 'Start Here',
      'url' => '/start-here',
      'variant' => 'primary',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'parent_id' => $cluster->id,
      'block_type_id' => $buttonLinkType->id,
      'sort_order' => 1,
      'label' => 'See primitives',
      'url' => '/see-primitives',
      'variant' => 'secondary',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $this->assertDatabaseHas('wbcms_blocks', ['page_id' => $page->id, 'type' => 'card_footer', 'parent_id' => $card->id]);
    $this->assertDatabaseHas('wbcms_blocks', ['page_id' => $page->id, 'type' => 'cluster', 'parent_id' => $cardFooter->id]);
    $this->assertDatabaseHas('wbcms_blocks', ['page_id' => $page->id, 'type' => 'button_link', 'parent_id' => $cluster->id, 'sort_order' => 0]);
    $this->assertDatabaseHas('wbcms_blocks', ['page_id' => $page->id, 'type' => 'button_link', 'parent_id' => $cluster->id, 'sort_order' => 1]);
    $this->assertTrue($card->fresh()->canAcceptChildren());
    $this->assertTrue($card->fresh(['blockType'])->canAcceptChildType('card_footer'));
    $this->assertFalse($card->fresh(['blockType'])->canAcceptChildType('cluster'));
    $this->assertFalse($card->fresh(['blockType'])->canAcceptChildType('button_link'));
    $this->assertFalse($card->fresh(['blockType'])->canAcceptChildType('plain_text'));

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $response->assertOk();
    $response->assertDontSee('Children: 1 item');
    $response->assertDontSee('Children: 2 items');
    $response->assertSee('class="wb-cms-block-children-badge"', false);
    $response->assertSee('aria-label="1 child block">1</span>', false);
    $response->assertSee('aria-label="2 child blocks">2</span>', false);
    $response->assertSee('data-wb-slot-toggle="'.$card->id.'"', false);
    $response->assertSee('data-wb-slot-block-id="'.$card->id.'"', false);
    $response->assertSee('data-wb-slot-parent-id="'.$card->id.'"', false);
    $response->assertSee('data-base-url="', false);
    $response->assertSee('picker=1', false);
    $response->assertSee('parent_id='.$card->id, false);
    $response->assertSee('Start Here');
    $response->assertSee('See primitives');
  }

  #[Test]
  public function card_rejects_unsupported_child_block_types(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();
    $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();

    $card = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'card',
      'block_type_id' => $cardType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)
      ->from(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'parent_id' => $card->id, 'block_type_id' => $headerType->id]))
      ->post(route('admin.blocks.store'), [
        'page_id' => $page->id,
        'slot_type_id' => $main->id,
        'parent_id' => $card->id,
        'block_type_id' => $headerType->id,
        'sort_order' => 0,
        'text' => 'Invalid child',
        'level' => 'h2',
        'status' => 'published',
        '_slot_block_mode' => 'create',
      ]);

    $response->assertStatus(302);
    $location = $response->headers->get('Location');
    $this->assertNotNull($location);
    $this->assertStringStartsWith(route('admin.pages.slots.blocks', [$page, $pageSlot]), $location);
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $this->assertSame('1', $query['picker'] ?? null);
    $this->assertSame((string) $card->id, $query['parent_id'] ?? null);
    $this->assertSame((string) $headerType->id, $query['block_type_id'] ?? null);
    $response->assertSessionHasErrors('parent_id');
    $this->assertDatabaseMissing('wbcms_blocks', ['page_id' => $page->id, 'type' => 'header', 'parent_id' => $card->id]);
  }

  #[Test]
  public function card_regions_require_card_parent_and_card_footer_accepts_cluster_children(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $cardFooterType = BlockType::query()->where('slug', 'card_footer')->firstOrFail();
    $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();
    $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();

    $invalid = $this->actingAs($user)
      ->from(route('admin.pages.slots.blocks', [$page, $pageSlot]))
      ->post(route('admin.blocks.store'), [
        'page_id' => $page->id,
        'slot_type_id' => $main->id,
        'block_type_id' => $cardFooterType->id,
        'sort_order' => 0,
        'status' => 'published',
        '_slot_block_mode' => 'create',
      ]);

    $invalid->assertSessionHasErrors('parent_id');

    $card = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'card',
      'block_type_id' => $cardType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $footerCreate = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'parent_id' => $card->id,
      'block_type_id' => $cardFooterType->id,
      'sort_order' => 0,
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $footerCreate->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $cardFooter = Block::query()->where('page_id', $page->id)->where('type', 'card_footer')->firstOrFail();
    $this->assertTrue($cardFooter->fresh(['blockType'])->canAcceptChildType('cluster'));
  }

  #[Test]
  public function header_block_store_creates_translation_backed_text_and_shared_level(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();

    $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $headerType->id,
      'sort_order' => 0,
      'text' => 'Welcome title',
      'level' => 'h1',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $block = Block::query()->where('page_id', $page->id)->where('type', 'header')->firstOrFail();

    $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $this->assertDatabaseHas('wbcms_blocks', [
      'id' => $block->id,
      'type' => 'header',
      'title' => null,
      'content' => null,
      'variant' => 'h1',
    ]);
    $this->assertTextTranslation($block, $this->defaultLocale()->id, [
      'title' => 'Welcome title',
      'subtitle' => null,
      'content' => null,
    ]);
    $this->assertNull($block->fresh()->setting('alignment'));
  }

  #[Test]
  public function plain_text_block_store_creates_translation_backed_text_only(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

    $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $plainTextType->id,
      'sort_order' => 0,
      'text' => 'Plain paragraph copy',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $block = Block::query()->where('page_id', $page->id)->where('type', 'plain_text')->firstOrFail();

    $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $this->assertDatabaseHas('wbcms_blocks', [
      'id' => $block->id,
      'type' => 'plain_text',
      'title' => null,
      'content' => null,
      'variant' => null,
    ]);
    $this->assertTextTranslation($block, $this->defaultLocale()->id, [
      'title' => null,
      'subtitle' => null,
      'content' => 'Plain paragraph copy',
    ]);
    $this->assertNull($block->fresh()->setting('alignment'));
  }

  #[Test]
  public function block_settings_are_saved_as_shared_non_translatable_settings(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
    $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $headerType->id,
      'sort_order' => 0,
      'text' => 'Aligned heading',
      'level' => 'h2',
      'alignment' => 'center',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $plainTextType->id,
      'sort_order' => 1,
      'text' => 'Aligned paragraph',
      'alignment' => 'right',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $sectionType->id,
      'sort_order' => 2,
      'name' => 'Feature zone',
      'spacing' => 'lg',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $containerType->id,
      'sort_order' => 3,
      'width' => 'xl',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $header = Block::query()->where('page_id', $page->id)->where('type', 'header')->firstOrFail();
    $plainText = Block::query()->where('page_id', $page->id)->where('type', 'plain_text')->firstOrFail();
    $section = Block::query()->where('page_id', $page->id)->where('type', 'section')->firstOrFail();
    $container = Block::query()->where('page_id', $page->id)->where('type', 'container')->firstOrFail();

    $this->assertSame('center', $header->fresh()->setting('alignment'));
    $this->assertSame('right', $plainText->fresh()->setting('alignment'));
    $this->assertSame('Feature zone', $section->fresh()->setting('layout_name'));
    $this->assertSame('lg', $section->fresh()->setting('spacing'));
    $this->assertSame('xl', $container->fresh()->setting('width'));
  }

  #[Test]
  public function card_store_creates_shell_only_settings_without_translation_rows(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();

    $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $cardType->id,
      'sort_order' => 0,
      'name' => 'Contact card',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $block = Block::query()->where('page_id', $page->id)->where('type', 'card')->firstOrFail();

    $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $this->assertDatabaseHas('wbcms_blocks', [
      'id' => $block->id,
      'type' => 'card',
      'title' => null,
      'subtitle' => null,
      'content' => null,
      'variant' => null,
    ]);
    $this->assertDatabaseMissing('wbcms_block_text_translations', [
      'block_id' => $block->id,
    ]);
    $this->assertSame('Contact card', $block->fresh()->setting('layout_name'));
  }

  #[Test]
  public function moving_a_nested_section_up_only_swaps_with_the_previous_sibling_under_the_same_parent(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page] = $this->pageWithSlot($main);
    $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();

    $container = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $containerType->id,
      'type' => 'container',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $sections = collect(range(0, 4))->map(function (int $index) use ($page, $main, $container, $sectionType) {
      return Block::query()->create([
        'page_id' => $page->id,
        'parent_id' => $container->id,
        'block_type_id' => $sectionType->id,
        'type' => 'section',
        'source_type' => 'static',
        'slot' => 'main',
        'slot_type_id' => $main->id,
        'sort_order' => $index,
        'status' => 'published',
        'is_system' => false,
        'settings' => json_encode(['layout_name' => 'Section '.($index + 1)], JSON_UNESCAPED_SLASHES),
      ]);
    });

    $target = $sections[3];

    $response = $this->actingAs($user)->post(route('admin.blocks.move-up', $target));

    $response->assertRedirect();

    $orderedIds = Block::query()
      ->where('parent_id', $container->id)
      ->orderBy('sort_order')
      ->pluck('id')
      ->all();

    $this->assertSame([
      $sections[0]->id,
      $sections[1]->id,
      $sections[3]->id,
      $sections[2]->id,
      $sections[4]->id,
    ], $orderedIds);
  }

  #[Test]
  public function moving_a_nested_card_up_swaps_with_the_previous_sibling_inside_the_same_section(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page] = $this->pageWithSlot($main);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
    $alertType = BlockType::query()->where('slug', 'alert')->firstOrFail();
    $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();

    $section = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $sectionType->id,
      'type' => 'section',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $alert = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $section->id,
      'block_type_id' => $alertType->id,
      'type' => 'alert',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
      'settings' => json_encode(['alert_variant' => 'info'], JSON_UNESCAPED_SLASHES),
    ]);

    $card = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $section->id,
      'block_type_id' => $cardType->id,
      'type' => 'card',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 1,
      'status' => 'published',
      'is_system' => false,
      'settings' => json_encode(['layout_name' => 'Support card'], JSON_UNESCAPED_SLASHES),
    ]);

    $response = $this->actingAs($user)->post(route('admin.blocks.move-up', $card));

    $response->assertRedirect();

    $orderedIds = Block::query()
      ->where('parent_id', $section->id)
      ->orderBy('sort_order')
      ->pluck('id')
      ->all();

    $this->assertSame([$card->id, $alert->id], $orderedIds);
  }

  #[Test]
  public function content_header_store_creates_translation_backed_fields_and_alignment_only_settings(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $contentHeaderType = BlockType::query()->where('slug', 'content_header')->firstOrFail();

    $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $contentHeaderType->id,
      'sort_order' => 0,
      'title' => 'Docs heading',
      'intro_text' => 'Intro copy',
      'meta_items' => ['Updated today', '5 min read', 'API'],
      'alignment' => 'center',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $block = Block::query()->where('page_id', $page->id)->where('type', 'content_header')->firstOrFail();

    $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $this->assertDatabaseHas('wbcms_blocks', [
      'id' => $block->id,
      'type' => 'content_header',
      'title' => null,
      'subtitle' => null,
      'content' => null,
      'variant' => null,
    ]);
    $this->assertDatabaseHas('wbcms_block_text_translations', [
      'block_id' => $block->id,
      'locale_id' => $this->defaultLocale()->id,
      'title' => 'Docs heading',
      'subtitle' => 'Intro copy',
      'content' => null,
      'meta' => json_encode(['Updated today', '5 min read', 'API'], JSON_UNESCAPED_SLASHES),
    ]);
    $this->assertSame('center', $block->fresh()->setting('alignment'));
  }

  #[Test]
  public function button_link_store_creates_translated_label_and_shared_url_target_and_variant(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $buttonLinkType = BlockType::query()->where('slug', 'button_link')->firstOrFail();

    $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $buttonLinkType->id,
      'sort_order' => 0,
      'label' => 'Start here',
      'url' => '/start-here',
      'target' => '_blank',
      'variant' => 'secondary',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $block = Block::query()->where('page_id', $page->id)->where('type', 'button_link')->firstOrFail();

    $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $this->assertDatabaseHas('wbcms_blocks', [
      'id' => $block->id,
      'type' => 'button_link',
      'title' => null,
      'subtitle' => null,
      'content' => null,
      'variant' => 'secondary',
    ]);
    $this->assertDatabaseHas('wbcms_block_text_translations', [
      'block_id' => $block->id,
      'locale_id' => $this->defaultLocale()->id,
      'title' => 'Start here',
      'subtitle' => null,
      'content' => null,
    ]);
    $this->assertSame('/start-here', $block->fresh()->setting('url'));
    $this->assertSame('_blank', $block->fresh()->setting('target'));
  }

  #[Test]
  public function cluster_is_seeded_as_published_container_block(): void
  {
    $this->seedFoundation();

    $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();

    $this->assertSame('published', $clusterType->status);
    $this->assertTrue($clusterType->is_container);
    $this->assertSame('layout', $clusterType->category);
  }

  #[Test]
  public function invalid_block_settings_are_rejected(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page] = $this->pageWithSlot($main);
    $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $headerType->id,
      'sort_order' => 0,
      'text' => 'Heading',
      'level' => 'h2',
      'alignment' => 'diagonal',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertSessionHasErrors('alignment');

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $sectionType->id,
      'sort_order' => 0,
      'spacing' => 'xl',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertSessionHasErrors('spacing');

    $contentHeaderType = BlockType::query()->where('slug', 'content_header')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $contentHeaderType->id,
      'sort_order' => 0,
      'title' => 'Docs heading',
      'meta_items' => ['ok', 123],
      'alignment' => 'diagonal',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertSessionHasErrors(['alignment', 'meta_items.1']);

    $buttonLinkType = BlockType::query()->where('slug', 'button_link')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $buttonLinkType->id,
      'sort_order' => 0,
      'label' => '',
      'url' => 'not-a-link',
      'target' => '_parent',
      'variant' => 'ghost',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertSessionHasErrors(['label', 'url', 'target']);

    $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $clusterType->id,
      'sort_order' => 0,
      'name' => 'Actions',
      'cluster_gap' => '3',
      'cluster_justify' => 'space-between',
      'cluster_align' => 'baseline',
      'cluster_wrap' => 'stack',
      'cluster_width' => 'fluid',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertSessionHasErrors(['cluster_gap', 'cluster_justify', 'cluster_align', 'cluster_wrap', 'cluster_width']);
  }

  #[Test]
  public function primitive_block_validation_requires_text_and_valid_header_level(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

    $headerResponse = $this->actingAs($user)
      ->from(route('admin.pages.slots.blocks', [$page, $pageSlot]))
      ->post(route('admin.blocks.store'), [
        'page_id' => $page->id,
        'slot_type_id' => $main->id,
        'block_type_id' => $headerType->id,
        'sort_order' => 0,
        'text' => '',
        'level' => 'div',
        'status' => 'published',
      ]);

    $headerResponse->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $headerResponse->assertSessionHasErrors(['text', 'level']);

    $plainTextResponse = $this->actingAs($user)
      ->from(route('admin.pages.slots.blocks', [$page, $pageSlot]))
      ->post(route('admin.blocks.store'), [
        'page_id' => $page->id,
        'slot_type_id' => $main->id,
        'block_type_id' => $plainTextType->id,
        'sort_order' => 1,
        'text' => '',
        'status' => 'published',
      ]);

    $plainTextResponse->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $plainTextResponse->assertSessionHasErrors(['text']);
  }

  #[Test]
  public function nested_layout_blocks_can_be_created_in_admin_slot_editor(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
    $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
    $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();
    $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
    $buttonLinkType = BlockType::query()->where('slug', 'button_link')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'block_type_id' => $sectionType->id,
      'sort_order' => 0,
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $section = Block::query()->where('page_id', $page->id)->where('type', 'section')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'parent_id' => $section->id,
      'block_type_id' => $containerType->id,
      'sort_order' => 0,
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $container = Block::query()->where('page_id', $page->id)->where('type', 'container')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'parent_id' => $container->id,
      'block_type_id' => $clusterType->id,
      'sort_order' => 0,
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $cluster = Block::query()->where('page_id', $page->id)->where('type', 'cluster')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'parent_id' => $cluster->id,
      'block_type_id' => $buttonLinkType->id,
      'sort_order' => 0,
      'label' => 'Primary action',
      'url' => '/primary-action',
      'variant' => 'primary',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'parent_id' => $container->id,
      'block_type_id' => $headerType->id,
      'sort_order' => 0,
      'text' => 'Nested title',
      'level' => 'h1',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'parent_id' => $container->id,
      'block_type_id' => $plainTextType->id,
      'sort_order' => 1,
      'text' => 'Nested paragraph',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $section->refresh();
    $container->refresh();

    $this->assertNull($section->parent_id);
    $this->assertSame($section->id, $container->parent_id);
    $this->assertDatabaseHas('wbcms_blocks', ['page_id' => $page->id, 'type' => 'header', 'parent_id' => $container->id]);
    $this->assertDatabaseHas('wbcms_blocks', ['page_id' => $page->id, 'type' => 'plain_text', 'parent_id' => $container->id]);
    $this->assertDatabaseHas('wbcms_blocks', ['page_id' => $page->id, 'type' => 'button_link', 'parent_id' => $cluster->id]);
    $this->assertTrue($cluster->fresh()->canAcceptChildren());

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot]));

    $response->assertOk();
    $response->assertSee('Section');
    $response->assertSee('Container');
    $response->assertSee('Cluster');
    $response->assertSee('Nested title');
    $response->assertSee('>Plain Text</strong></a>', false);
    $response->assertSee('Primary action');
    $response->assertDontSee('<th>Order</th>', false);
    $response->assertSee('data-wb-slot-block-row', false);
    $response->assertSee('data-wb-cms-slot-block-tree', false);
    $response->assertSee('data-wb-slot-id="'.$pageSlot->id.'"', false);
    $response->assertSee('data-page-id="'.$page->id.'"', false);
    $response->assertSee('data-slot-type-id="'.$main->id.'"', false);
    $response->assertSee('data-wb-slot-block-id="'.$section->id.'"', false);
    $response->assertSee('data-wb-slot-block-id="'.$container->id.'"', false);
    $response->assertSee('data-wb-slot-block-id="'.$cluster->id.'"', false);
    $response->assertSee('title="Move block up"', false);
    $response->assertSee('title="Move block down"', false);
    $response->assertSee('class="wb-block-row wb-block-row-depth-0"', false);
    $response->assertSee('wb-block-row-depth-1', false);
    $response->assertSee('wb-block-row-depth-2', false);
    $response->assertSee('data-depth="0"', false);
    $response->assertSee('data-depth="1"', false);
    $response->assertSee('data-depth="2"', false);
    $response->assertDontSee('style="--block-depth:', false);
    $response->assertSee('data-wb-slot-parent-id="'.$section->id.'"', false);
    $response->assertSee('data-wb-slot-toggle="'.$section->id.'"', false);
    $response->assertSee('data-wb-slot-toggle="'.$container->id.'"', false);
    $response->assertSee('data-wb-slot-toggle="'.$cluster->id.'"', false);
    $response->assertSee('class="wb-block-hierarchy-cell wb-admin-slot-block-type-cell"', false);
    $response->assertSee('class="wb-block-hierarchy"', false);
    $response->assertSee('cms/css/admin.css', false);
    $response->assertDontSee('site/css/admin.css', false);
    $response->assertSee('cms/js/admin-sortable-list.js', false);
    $response->assertSee('cms/js/admin/inline-block-builder.js', false);
    $response->assertSee('cms/js/admin/builder-items.js', false);
    $response->assertSee('cms/js/admin/page-builder-modals.js', false);
    $response->assertSee('cms/js/admin/slot-block-delete-modal.js', false);
    $response->assertSee('cms/js/admin/slot-block-tree.js', false);
    $response->assertDontSee('cms/js/admin/slot-blocks.js', false);
    $response->assertDontSee('— Cluster', false);
    $response->assertDontSee('>0.1<', false);
  }

  #[Test]
  public function header_locale_update_only_changes_translated_text_and_keeps_shared_level(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $site = $this->defaultSite();
    $turkish = Locale::query()->create([
      'code' => 'tr',
      'name' => 'Turkish',
      'is_default' => false,
      'is_enabled' => true,
    ]);
    $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $turkish->id],
      ['site_id' => $site->id, 'name' => 'Hakkinda', 'slug' => 'hakkinda', 'path' => '/hakkinda'],
    );

    $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();
    $header = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'header',
      'block_type_id' => $headerType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'variant' => 'h2',
      'status' => 'published',
      'is_system' => false,
    ]);
    $header->textTranslations()->create([
      'locale_id' => $this->defaultLocale()->id,
      'title' => 'English heading',
      'subtitle' => null,
      'content' => null,
    ]);

    $response = $this->actingAs($user)->put(route('admin.blocks.update', $header), [
      'page_id' => $page->id,
      'parent_id' => null,
      'block_type_id' => $headerType->id,
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'text' => 'Turkce baslik',
      'level' => 'h5',
      'status' => 'published',
      'locale' => 'tr',
      '_slot_block_mode' => 'edit',
      '_slot_block_id' => $header->id,
    ]);

    $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot, 'locale' => 'tr']));
    $this->assertTextTranslation($header, $turkish->id, [
      'title' => 'Turkce baslik',
      'subtitle' => null,
      'content' => null,
    ]);
    $this->assertSame('h2', $header->fresh()->variant);
    $this->assertDatabaseHas('wbcms_block_text_translations', [
      'block_id' => $header->id,
      'locale_id' => $this->defaultLocale()->id,
      'title' => 'English heading',
    ]);
  }

  #[Test]
  public function cta_locale_update_changes_translated_labels_and_keeps_shared_urls(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $site = $this->defaultSite();
    $turkish = Locale::query()->create([
      'code' => 'tr',
      'name' => 'Turkish',
      'is_default' => false,
      'is_enabled' => true,
    ]);
    $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $turkish->id],
      ['site_id' => $site->id, 'name' => 'Hakkinda', 'slug' => 'hakkinda', 'path' => '/hakkinda'],
    );

    $ctaType = BlockType::query()->where('slug', 'cta')->firstOrFail();
    $buttonType = BlockType::query()->where('slug', 'button_link')->firstOrFail();
    $cta = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'cta',
      'block_type_id' => $ctaType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'variant' => 'accent',
      'status' => 'published',
      'is_system' => false,
    ]);
    $cta->textTranslations()->create([
      'locale_id' => $this->defaultLocale()->id,
      'title' => 'English heading',
      'subtitle' => 'English eyebrow',
      'content' => 'English body',
    ]);

    $primaryButton = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $cta->id,
      'type' => 'button_link',
      'block_type_id' => $buttonType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'url' => '/shared-primary',
      'subtitle' => '_self',
      'variant' => 'primary',
      'status' => 'published',
      'is_system' => false,
    ]);
    $primaryButton->textTranslations()->create([
      'locale_id' => $this->defaultLocale()->id,
      'title' => 'Start now',
    ]);

    $secondaryButton = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $cta->id,
      'type' => 'button_link',
      'block_type_id' => $buttonType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 1,
      'url' => '/shared-secondary',
      'subtitle' => '_self',
      'variant' => 'secondary',
      'status' => 'published',
      'is_system' => false,
    ]);
    $secondaryButton->textTranslations()->create([
      'locale_id' => $this->defaultLocale()->id,
      'title' => 'Read docs',
    ]);

    $response = $this->actingAs($user)->put(route('admin.blocks.update', $cta), [
      'page_id' => $page->id,
      'parent_id' => null,
      'block_type_id' => $ctaType->id,
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'subtitle' => 'Turkce etiket',
      'title' => 'Turkce baslik',
      'content' => 'Turkce govde',
      'primary_cta_label' => 'Hemen basla',
      'primary_cta_url' => '/should-be-ignored',
      'secondary_cta_label' => 'Dokumanlari oku',
      'secondary_cta_url' => '/also-ignored',
      'variant' => 'soft',
      'status' => 'published',
      'locale' => 'tr',
      '_slot_block_mode' => 'edit',
      '_slot_block_id' => $cta->id,
    ]);

    $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot, 'locale' => 'tr']));
    $this->assertTextTranslation($cta, $turkish->id, [
      'title' => 'Turkce baslik',
      'subtitle' => 'Turkce etiket',
      'content' => 'Turkce govde',
    ]);
    $this->assertDatabaseHas('wbcms_block_text_translations', [
      'block_id' => $primaryButton->id,
      'locale_id' => $turkish->id,
      'title' => 'Hemen basla',
    ]);
    $this->assertDatabaseHas('wbcms_block_text_translations', [
      'block_id' => $secondaryButton->id,
      'locale_id' => $turkish->id,
      'title' => 'Dokumanlari oku',
    ]);
    $this->assertSame('/shared-primary', $primaryButton->fresh()->url);
    $this->assertSame('/shared-secondary', $secondaryButton->fresh()->url);
    $this->assertSame('accent', $cta->fresh()->variant);
  }

  #[Test]
  public function columns_locale_update_changes_child_translations_and_keeps_shared_child_urls(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $site = $this->defaultSite();
    $turkish = Locale::query()->create([
      'code' => 'tr',
      'name' => 'Turkish',
      'is_default' => false,
      'is_enabled' => true,
    ]);
    $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $turkish->id],
      ['site_id' => $site->id, 'name' => 'Hakkinda', 'slug' => 'hakkinda', 'path' => '/hakkinda'],
    );

    $columnsType = BlockType::query()->where('slug', 'columns')->firstOrFail();
    $columnItemType = BlockType::query()->where('slug', 'column_item')->firstOrFail();
    $columns = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'columns',
      'block_type_id' => $columnsType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'variant' => 'cards',
      'status' => 'published',
      'is_system' => false,
    ]);
    $columns->textTranslations()->create([
      'locale_id' => $this->defaultLocale()->id,
      'title' => 'English columns',
      'subtitle' => 'English subtitle',
      'content' => 'English intro',
    ]);

    $columnItem = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $columns->id,
      'type' => 'column_item',
      'block_type_id' => $columnItemType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'url' => '/shared-column-link',
      'status' => 'published',
      'is_system' => false,
    ]);
    $columnItem->textTranslations()->create([
      'locale_id' => $this->defaultLocale()->id,
      'title' => 'English item',
      'subtitle' => '42',
      'content' => 'English item body',
    ]);

    $response = $this->actingAs($user)->put(route('admin.blocks.update', $columns), [
      'page_id' => $page->id,
      'parent_id' => null,
      'block_type_id' => $columnsType->id,
      'slot_type_id' => $main->id,
      'sort_order' => 0,
      'title' => 'Turkce sutunlar',
      'subtitle' => 'Turkce alt baslik',
      'content' => 'Turkce giris',
      'variant' => 'plain',
      'column_items' => [[
        'id' => $columnItem->id,
        'block_type_id' => $columnItemType->id,
        'title' => 'Turkce oge',
        'content' => 'Turkce oge govdesi',
        'url' => '/should-be-ignored',
        'status' => 'published',
        'sort_order' => 0,
      ]],
      'status' => 'published',
      'locale' => 'tr',
      '_slot_block_mode' => 'edit',
      '_slot_block_id' => $columns->id,
    ]);

    $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot, 'locale' => 'tr']));
    $this->assertTextTranslation($columns, $turkish->id, [
      'title' => 'Turkce sutunlar',
      'subtitle' => 'Turkce alt baslik',
      'content' => 'Turkce giris',
    ]);
    $this->assertTextTranslation($columnItem, $turkish->id, [
      'title' => 'Turkce oge',
      'subtitle' => null,
      'content' => 'Turkce oge govdesi',
    ]);
    $this->assertSame('/shared-column-link', $columnItem->fresh()->url);
    $this->assertSame('cards', $columns->fresh()->variant);
  }

  #[Test]
  public function rich_text_block_form_exposes_safe_toolbar_editor_markup(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $main = $this->slotType('main', 'Main', 1);
    [$page, $pageSlot] = $this->pageWithSlot($main);
    $richTextType = BlockType::query()->where('slug', 'rich-text')->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $richTextType->id]));

    $response->assertOk();
    $response->assertSee('<label for="content__surface">Rich Text</label>', false);
    $response->assertSee('class="wb-admin-rich-text-editor"', false);
    $response->assertSee('class="wb-toolbar wb-toolbar-sm wb-admin-rich-text-toolbar" role="toolbar" aria-label="Rich Text formatting"', false);
    $response->assertSee('class="wb-action-group" role="group" aria-label="Inline formatting"', false);
    $response->assertSee('class="wb-action-group" role="group" aria-label="Links"', false);
    $response->assertSee('class="wb-action-group" role="group" aria-label="Lists"', false);
    $response->assertSee('class="wb-action-group" role="group" aria-label="Cleanup"', false);
    $response->assertSee('data-wb-rich-text-editor', false);
    $response->assertSee('data-wb-rich-text-surface', false);
    $response->assertSee('data-wb-rich-text-input', false);
    $response->assertSee('contenteditable="true"', false);
    $response->assertSee('data-wb-rich-text-action="bold"', false);
    $response->assertSee('data-wb-rich-text-action="italic"', false);
    $response->assertSee('data-wb-rich-text-action="code"', false);
    $response->assertSee('data-wb-rich-text-action="link"', false);
    $response->assertSee('data-wb-rich-text-action="bullet-list"', false);
    $response->assertSee('data-wb-rich-text-action="numbered-list"', false);
    $response->assertSee('data-wb-rich-text-action="clear"', false);
    $response->assertSee('name="content"', false);
    $response->assertSee('hidden', false);
    $response->assertSee('aria-label="Bold" title="Bold">B</button>', false);
    $response->assertSee('aria-label="Italic" title="Italic">I</button>', false);
    $response->assertSee('>Code</button>', false);
    $response->assertSee('>Link</button>', false);
    $response->assertSee('>• List</button>', false);
    $response->assertSee('>1. List</button>', false);
    $response->assertSee('>Clear</button>', false);
    $response->assertSee('Headings should use Header blocks.', false);
    $response->assertSee('cms/js/admin/rich-text-editor.js', false);

    $assetContents = file_get_contents(public_path('cms/js/admin/rich-text-editor.js'));

    $partialContents = file_get_contents(resource_path('views/admin/blocks/types/partials/rich-text-editor.blade.php'));

    $this->assertNotFalse($assetContents);
    $this->assertNotFalse($partialContents);
    $this->assertStringContainsString('function sanitizeHtmlFragment(html, doc)', $assetContents);
    $this->assertStringContainsString('function bindEditor(root)', $assetContents);
    $this->assertStringContainsString("document.addEventListener('selectionchange'", $assetContents);
    $this->assertStringContainsString("document.addEventListener('mousedown'", $assetContents);
    $this->assertStringContainsString('event.preventDefault();', $assetContents);
    $this->assertStringContainsString('editor.savedRange = range.cloneRange();', $assetContents);
    $this->assertStringContainsString("document.addEventListener('submit'", $assetContents);
    $this->assertStringContainsString('window.WebBlocksCmsAdminRichTextEditor = {', $assetContents);
    $this->assertStringContainsString('data-wb-rich-text-surface', $partialContents);
    $this->assertStringContainsString('data-wb-rich-text-input', $partialContents);
    $this->assertStringNotContainsString('toggleWrap(textarea', $assetContents);
    $this->assertStringNotContainsString('getMarkdownLinkRange', $assetContents);
    $this->assertStringNotContainsString('**', $assetContents);
    $this->assertStringNotContainsString('<script', $partialContents);
  }

  #[Test]
  public function page_edit_screen_displays_slot_source_states_and_compatible_shared_slot_options(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    $main = $this->slotType('main', 'Main', 2);
    $page = Page::query()->create([
      'site_id' => $this->defaultSite()->id,
      'title' => 'Source Demo',
      'slug' => 'source-demo',
      'status' => Page::STATUS_DRAFT,
      'settings' => ['public_shell' => 'docs'],
    ]);

    $pageSlot = PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $header->id,
      'source_type' => PageSlot::SOURCE_TYPE_PAGE,
      'sort_order' => 0,
    ]);

    $sharedSlot = $this->activeSharedSlotForPage($page, 'Reusable Header', 'reusable-header', 'header', 'docs');

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
      'shared_slot_id' => $sharedSlot->id,
      'sort_order' => 1,
    ]);

    $disabledSlot = PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $this->slotType('sidebar', 'Sidebar', 3)->id,
      'source_type' => PageSlot::SOURCE_TYPE_DISABLED,
      'sort_order' => 2,
    ]);

    $response = $this->actingAs($user)->get(route('admin.pages.edit', $page));
    $content = $response->getContent();

    $response->assertOk();
    $this->assertNotFalse($content);
    $response->assertSee('Page Content');
    $response->assertSee('Shared Slot: Reusable Header');
    $response->assertSee('Disabled');
    $response->assertSee('<th>Slot</th>', false);
    $response->assertSee('Top-level Blocks');
    $response->assertSee('<span class="wb-status-pill wb-status-info">header</span>', false);
    $response->assertSee('<span class="wb-status-pill wb-status-info">main</span>', false);
    $response->assertSee('0 page-owned top-level blocks');
    $response->assertSee('Manage Source');
    $response->assertSee('data-wb-page-slot-source-open', false);
    $response->assertSee('data-wb-page-slot-source-target="#slot-source-modal-'.$pageSlot->id.'"', false);
    $response->assertSee('data-wb-page-slot-source-target="#slot-source-modal-'.$disabledSlot->id.'"', false);
    $response->assertSee('id="slot-source-modal-'.$pageSlot->id.'"', false);
    $response->assertSee('id="slot-source-modal-'.$disabledSlot->id.'"', false);
    $this->assertSame(1, substr_count($content, 'id="slot-source-modal-'.$pageSlot->id.'"'));
    $this->assertSame(1, substr_count($content, 'id="slot-source-modal-'.$disabledSlot->id.'"'));
    $this->assertSame(1, substr_count($content, 'data-wb-page-slot-source-target="#slot-source-modal-'.$pageSlot->id.'"'));
    $this->assertSame(1, substr_count($content, 'data-wb-page-slot-source-target="#slot-source-modal-'.$disabledSlot->id.'"'));
    $response->assertSee('Manage Source: Header');
    $response->assertSee('Manage Source: Sidebar');
    $response->assertSee('Choose what this slot should render.');
    $response->assertSee('Current: Page Content');
    $response->assertSee('Current: Disabled');
    $response->assertSee('This slot renders this page\'s own blocks.');
    $response->assertSee('wb-admin-slot-source-picker', false);
    $response->assertSee('wb-admin-slot-source-option', false);
    $response->assertSee('wb-btn-primary is-active', false);
    $response->assertSee('Page-owned blocks are preserved when switching sources.', false);
    $response->assertSee('Save Source');
    $response->assertDontSee('Update Source');
    $response->assertSee('action="'.route('admin.pages.slots.source.update', [$page, $pageSlot]).'"', false);
    $response->assertSee('action="'.route('admin.pages.slots.source.update', [$page, $disabledSlot]).'"', false);
    $response->assertSee('cms/js/admin/page-slot-source-modals.js', false);
    $response->assertSee('Edit Blocks');
    $response->assertSee('Page Blocks');
    $response->assertDontSee('Edit Shared Slot');
    $response->assertDontSee('Edit Page Blocks (Preserved)');
    $response->assertSee('Reusable Header (reusable-header)', false);
    $response->assertDontSee('Reusable Header (reusable-header) | header | docs', false);
    $response->assertDontSee('Switch this slot between page-owned content, a Shared Slot, or disabled output.');
    $response->assertDontSee('<strong>Slot</strong>', false);
    $response->assertDontSee('<strong>Current source</strong>', false);
    $response->assertDontSee('<fieldset', false);
    $response->assertDontSee('<legend', false);
    $response->assertDontSee('wb-card wb-stack wb-gap-1', false);
    $response->assertDontSee('This page\'s own slot blocks render publicly.');
    $response->assertDontSee('Preserved but not currently rendered.');
    $response->assertDontSee('<button type="submit" class="wb-btn wb-btn-secondary wb-btn-sm">Update Source</button>', false);
    $response->assertSee('type="radio"', false);
    $response->assertSee('value="page"', false);
    $response->assertSee('value="shared_slot"', false);
    $response->assertSee('value="disabled"', false);
    $response->assertSee('data-wb-slot-source-helper', false);
    $this->assertNotFalse(strpos($content, '</table>'));
    $this->assertNotFalse(strpos($content, 'id="slot-source-modal-'.$pageSlot->id.'"'));
    $this->assertTrue(strpos($content, '</table>') < strpos($content, 'id="slot-source-modal-'.$pageSlot->id.'"'));
    $this->assertSame(3, substr_count($content, 'data-wb-page-slot-source-modal '));
  }

  #[Test]
  public function shared_slot_selector_only_shows_compatible_active_same_site_shared_slots(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    [$page] = $this->pageWithSlot($header, 'Docs', 'docs');
    $page->update(['settings' => ['public_shell' => 'docs']]);

    $compatible = $this->activeSharedSlotForPage($page, 'Compatible Header', 'compatible-header', 'header', 'docs');
    $this->activeSharedSlotForPage($page, 'Any Header', 'any-header', null, null);
    $this->activeSharedSlotForPage($page, 'Wrong Slot', 'wrong-slot', 'sidebar', 'docs');
    $this->activeSharedSlotForPage($page, 'Wrong Shell', 'wrong-shell', 'header', 'default');
    $this->activeSharedSlotForPage($page, 'Inactive Header', 'inactive-header', 'header', 'docs', false);

    $otherSite = Site::query()->create([
      'name' => 'Other Site',
      'handle' => 'other-site',
      'domain' => 'other.example.test',
      'is_primary' => false,
    ]);

    SharedSlot::query()->create([
      'site_id' => $otherSite->id,
      'name' => 'Other Site Header',
      'handle' => 'other-site-header',
      'slot_name' => 'header',
      'public_shell' => 'docs',
      'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get(route('admin.pages.edit', $page));

    $response->assertOk();
    $response->assertSee('Compatible Header (compatible-header)', false);
    $response->assertSee('Any Header (any-header)', false);
    $response->assertDontSee('Only active Shared Slots from this site with compatible shell and slot rules are listed.');
    $response->assertSee('Manage Source: Header');
    $response->assertDontSee('Wrong Slot (wrong-slot)', false);
    $response->assertDontSee('Wrong Shell (wrong-shell)', false);
    $response->assertDontSee('Inactive Header (inactive-header)', false);
    $response->assertDontSee('Other Site Header (other-site-header)', false);
    $this->assertNotNull($compatible->id);
  }

  #[Test]
  public function updating_slot_source_to_shared_slot_page_content_and_disabled_preserves_page_blocks(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();
    [$page, $pageSlot] = $this->pageWithSlot($header, 'Docs', 'docs');
    $page->update(['settings' => ['public_shell' => 'docs']]);

    $pageBlock = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $headerType->id,
      'type' => 'header',
      'source_type' => 'static',
      'slot' => 'header',
      'slot_type_id' => $header->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $sharedSlot = $this->activeSharedSlotForPage($page, 'Reusable Header', 'reusable-header', 'header', 'docs');
    $sharedSourcePage = Page::query()->create([
      'site_id' => $page->site_id,
      'title' => 'Shared Source',
      'slug' => 'shared-source',
      'status' => Page::STATUS_DRAFT,
      'page_type' => Page::TYPE_SHARED_SLOT_SOURCE,
      'settings' => ['shared_slot_id' => $sharedSlot->id, 'public_shell' => 'docs'],
    ]);
    $sharedSourceBlock = Block::query()->create([
      'page_id' => $sharedSourcePage->id,
      'block_type_id' => $headerType->id,
      'type' => 'header',
      'source_type' => 'static',
      'slot' => 'header',
      'slot_type_id' => $header->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $toShared = $this->actingAs($user)->put(route('admin.pages.slots.source.update', [$page, $pageSlot]), [
      'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
      'shared_slot_id' => $sharedSlot->id,
    ]);

    $toShared->assertRedirect(route('admin.pages.edit', $page));
    $this->assertDatabaseHas('wbcms_page_slots', [
      'id' => $pageSlot->id,
      'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
      'shared_slot_id' => $sharedSlot->id,
    ]);
    $this->assertDatabaseHas('wbcms_blocks', ['id' => $pageBlock->id, 'page_id' => $page->id]);
    $this->assertDatabaseHas('wbcms_blocks', ['id' => $sharedSourceBlock->id, 'page_id' => $sharedSourcePage->id]);

    $toDisabled = $this->actingAs($user)->put(route('admin.pages.slots.source.update', [$page, $pageSlot]), [
      'source_type' => PageSlot::SOURCE_TYPE_DISABLED,
      'shared_slot_id' => $sharedSlot->id,
    ]);

    $toDisabled->assertRedirect(route('admin.pages.edit', $page));
    $this->assertDatabaseHas('wbcms_page_slots', [
      'id' => $pageSlot->id,
      'source_type' => PageSlot::SOURCE_TYPE_DISABLED,
      'shared_slot_id' => null,
    ]);
    $this->assertDatabaseHas('wbcms_blocks', ['id' => $pageBlock->id, 'page_id' => $page->id]);

    $toPage = $this->actingAs($user)->put(route('admin.pages.slots.source.update', [$page, $pageSlot]), [
      'source_type' => PageSlot::SOURCE_TYPE_PAGE,
      'shared_slot_id' => $sharedSlot->id,
    ]);

    $toPage->assertRedirect(route('admin.pages.edit', $page));
    $this->assertDatabaseHas('wbcms_page_slots', [
      'id' => $pageSlot->id,
      'source_type' => PageSlot::SOURCE_TYPE_PAGE,
      'shared_slot_id' => null,
    ]);
    $this->assertDatabaseHas('wbcms_blocks', ['id' => $pageBlock->id, 'page_id' => $page->id]);
  }

  #[Test]
  public function assigning_incompatible_or_unauthorized_shared_slots_is_rejected_and_incompatible_assignments_warn_in_admin(): void
  {
    $this->seedFoundation();

    $siteAdmin = User::factory()->siteAdmin()->create();
    $editor = User::factory()->editor()->create();
    $header = $this->slotType('header', 'Header', 1);
    [$page, $pageSlot] = $this->pageWithSlot($header, 'Docs', 'docs');
    $page->update(['settings' => ['public_shell' => 'docs']]);
    $siteAdmin->sites()->sync([$page->site_id]);
    $editor->sites()->sync([$page->site_id]);

    $inactive = $this->activeSharedSlotForPage($page, 'Inactive Header', 'inactive-header', 'header', 'docs', false);
    $wrongShell = $this->activeSharedSlotForPage($page, 'Wrong Shell', 'wrong-shell', 'header', 'default');
    $wrongSlot = $this->activeSharedSlotForPage($page, 'Wrong Slot', 'wrong-slot', 'sidebar', 'docs');

    $otherSite = Site::query()->create([
      'name' => 'Other Site',
      'handle' => 'other-site',
      'domain' => 'other.example.test',
      'is_primary' => false,
    ]);
    $crossSite = SharedSlot::query()->create([
      'site_id' => $otherSite->id,
      'name' => 'Cross Site',
      'handle' => 'cross-site',
      'slot_name' => 'header',
      'public_shell' => 'docs',
      'is_active' => true,
    ]);

    foreach ([$inactive, $wrongShell, $wrongSlot, $crossSite] as $sharedSlot) {
      $response = $this->actingAs($siteAdmin)
        ->from(route('admin.pages.edit', $page))
        ->put(route('admin.pages.slots.source.update', [$page, $pageSlot]), [
          'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
          'shared_slot_id' => $sharedSlot->id,
        ]);

      $response->assertRedirect(route('admin.pages.edit', $page));
      $response->assertSessionHasErrors('shared_slot_id');
    }

    $page->update(['status' => Page::STATUS_PUBLISHED]);

    $this->actingAs($editor)
      ->put(route('admin.pages.slots.source.update', [$page, $pageSlot]), [
        'source_type' => PageSlot::SOURCE_TYPE_DISABLED,
      ])
      ->assertForbidden();

    $pageSlot->update([
      'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
      'shared_slot_id' => $wrongShell->id,
    ]);
    $wrongShell->update(['public_shell' => 'default']);

    $warningResponse = $this->actingAs($siteAdmin)->get(route('admin.pages.edit', $page->fresh()));

    $warningResponse->assertOk();
    $warningResponse->assertSee('This Shared Slot is no longer compatible because its Page Layout no longer matches this page.');
    $warningResponse->assertSee('Manage Source');
  }

  #[Test]
  public function failed_slot_source_update_reopens_the_matching_slot_source_modal_on_page_edit(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    [$page, $pageSlot] = $this->pageWithSlot($header, 'Docs', 'docs');
    $page->update(['settings' => ['public_shell' => 'docs']]);

    $wrongShell = $this->activeSharedSlotForPage($page, 'Wrong Shell', 'wrong-shell', 'header', 'default');

    $response = $this->followingRedirects()->actingAs($user)
      ->from(route('admin.pages.edit', $page))
      ->put(route('admin.pages.slots.source.update', [$page, $pageSlot]), [
        'slot_id' => $pageSlot->id,
        'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
        'shared_slot_id' => $wrongShell->id,
      ]);

    $response->assertOk();
    $response->assertSee('This slot source update needs attention.');
    $response->assertSee('Shared Slot Page Layout must match the page Page Layout.');
    $response->assertSee('Manage Source: Header');
    $response->assertSee('Manage Source');
    $response->assertSee('class="wb-modal wb-modal-lg is-open" id="slot-source-modal-'.$pageSlot->id.'"', false);
  }

  #[Test]
  public function source_modal_shows_short_empty_shared_slot_message_and_create_link_when_allowed(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = $this->slotType('header', 'Header', 1);
    [$page] = $this->pageWithSlot($header, 'Docs', 'docs');
    $page->update(['settings' => ['public_shell' => 'docs']]);

    $response = $this->actingAs($user)->get(route('admin.pages.edit', $page));

    $response->assertOk();
    $response->assertSee('No compatible Shared Slots are available.');
    $response->assertSee(route('admin.shared-slots.create', ['site' => $page->site_id]), false);
  }
}
