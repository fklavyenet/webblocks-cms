<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Database\Seeders\IconCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;

class NavigationTreeEditorTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    $this->seed(FoundationSiteLocaleSeeder::class);
    $this->seed(IconCatalogSeeder::class);
  }

  private function createPageForSite(Site $site, string $title, string $slug): Page
  {
    return Page::query()->create([
      'site_id' => $site->id,
      'title' => $title,
      'slug' => $slug,
      'status' => 'published',
    ]);
  }

  #[Test]
  public function admin_navigation_items_screen_loads_with_menu_filtering(): void
  {
    $user = User::factory()->create();
    $siteId = Site::query()->where('is_primary', true)->value('id');
    $home = Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published']);
    $about = Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published']);

    NavigationItem::create([
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Primary Home Link',
      'link_type' => NavigationItem::LINK_PAGE,
      'page_id' => $home->id,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    NavigationItem::create([
      'menu_key' => NavigationItem::MENU_FOOTER,
      'title' => 'Footer About Link',
      'link_type' => NavigationItem::LINK_PAGE,
      'page_id' => $about->id,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $response = $this->actingAs($user)->get(route('admin.navigation.index', ['menu_key' => 'footer']));

    $response->assertOk();
    $response->assertSee('Navigation Items');
    $response->assertSee('Manage site menus, dropdowns, and footer links.');
    $response->assertSee('data-admin-listing-filters', false);
    $response->assertSee('>Site</label>', false);
    $response->assertSee('>Menu</label>', false);
    $response->assertDontSee('Idle');
    $response->assertSee('Footer About Link');
    $response->assertDontSee('Primary Home Link');
    $response->assertSee('site_id='.$siteId);
    $response->assertSee('menu_key=footer');
    $response->assertSee('modal=create-item');
    $response->assertSee('modal=create-group');
    $response->assertSee('aria-controls="navigationCreateItemModal"', false);
    $response->assertSee('aria-controls="navigationCreateGroupModal"', false);
  }

  #[Test]
  public function add_navigation_item_opens_in_a_modal_instead_of_a_drawer(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.navigation.index', [
      'site_id' => Site::query()->where('is_primary', true)->value('id'),
      'menu_key' => NavigationItem::MENU_DOCS,
      'modal' => 'create-item',
    ]));

    $response->assertOk();
    $response->assertSee('id="navigationCreateItemModal"', false);
    $response->assertSee('class="wb-modal wb-modal-lg"', false);
    $response->assertSee('data-wb-admin-autoload-overlay', false);
    $response->assertDontSee('class="wb-modal wb-modal-lg is-open"', false);
    $response->assertDontSee('wb-drawer', false);
    $response->assertSee('Parent Group', false);
    $response->assertSee('Groups render as collapsible parent sections and can contain child navigation items.', false);
  }

  #[Test]
  public function creating_a_normal_navigation_item_works(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $page = $this->createPageForSite($site, 'Docs Overview', 'docs-overview');

    $response = $this->actingAs($user)->post(route('admin.navigation.store'), [
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'link_type' => NavigationItem::LINK_PAGE,
      'page_id' => $page->id,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $response->assertRedirect(route('admin.navigation.index', ['site_id' => $site->id, 'menu_key' => NavigationItem::MENU_DOCS]));
    $this->assertDatabaseHas('navigation_items', [
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'page_id' => $page->id,
      'link_type' => NavigationItem::LINK_PAGE,
      'title' => 'Docs Overview',
    ]);
  }

  #[Test]
  public function creating_a_group_navigation_item_works(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();

    $response = $this->actingAs($user)->post(route('admin.navigation.store'), [
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'title' => 'Patterns',
      'link_type' => NavigationItem::LINK_GROUP,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $response->assertRedirect(route('admin.navigation.index', ['site_id' => $site->id, 'menu_key' => NavigationItem::MENU_DOCS]));
    $this->assertDatabaseHas('navigation_items', [
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'title' => 'Patterns',
      'link_type' => NavigationItem::LINK_GROUP,
      'page_id' => null,
      'url' => null,
    ]);
  }

  #[Test]
  public function creating_a_group_navigation_item_with_an_icon_persists_the_icon(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();

    $response = $this->actingAs($user)->post(route('admin.navigation.store'), [
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'title' => 'Patterns',
      'link_type' => NavigationItem::LINK_GROUP,
      'icon' => 'layout',
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $response->assertRedirect(route('admin.navigation.index', ['site_id' => $site->id, 'menu_key' => NavigationItem::MENU_DOCS]));
    $this->assertDatabaseHas('navigation_items', [
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'title' => 'Patterns',
      'link_type' => NavigationItem::LINK_GROUP,
      'icon' => 'layout',
    ]);
  }

  #[Test]
  public function editing_a_group_item_icon_persists_and_reopens_selected(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $group = NavigationItem::query()->create([
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'title' => 'Patterns',
      'link_type' => NavigationItem::LINK_GROUP,
      'icon' => 'layers',
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $this->actingAs($user)->put(route('admin.navigation.update', $group), [
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'title' => 'Patterns',
      'link_type' => NavigationItem::LINK_GROUP,
      'icon' => 'layout',
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ])->assertRedirect(route('admin.navigation.index', ['site_id' => $site->id, 'menu_key' => NavigationItem::MENU_DOCS]));

    $this->assertDatabaseHas('navigation_items', [
      'id' => $group->id,
      'icon' => 'layout',
    ]);

    $response = $this->actingAs($user)->get(route('admin.navigation.index', [
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'modal' => 'edit-item',
      'navigation' => $group->id,
    ]));

    $response->assertOk();
    $response->assertSee('id="navigationEditModal-'.$group->id.'"', false);
    $response->assertSee('<option value="layout" selected>Layout</option>', false);
    $response->assertDontSee('<option value="layers" selected>Layers</option>', false);
  }

  #[Test]
  public function navigation_icon_picker_only_lists_active_navigation_context_icons(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();

    IconCatalogItem::query()->updateOrCreate([
      'source' => 'webblocks-ui',
      'slug' => 'images',
    ], [
      'label' => 'Images',
      'css_class' => 'wb-icon-images',
      'contexts' => ['navigation'],
      'categories' => ['media'],
      'is_active' => true,
      'sort_order' => 999,
    ]);
    IconCatalogItem::query()->updateOrCreate([
      'source' => 'webblocks-ui',
      'slug' => 'hidden-icon',
    ], [
      'label' => 'Hidden Icon',
      'css_class' => 'wb-icon-hidden-icon',
      'contexts' => ['navigation'],
      'categories' => ['navigation'],
      'is_active' => false,
      'sort_order' => 1000,
    ]);
    IconCatalogItem::query()->updateOrCreate([
      'source' => 'webblocks-ui',
      'slug' => 'marketing-only',
    ], [
      'label' => 'Marketing Only',
      'css_class' => 'wb-icon-marketing-only',
      'contexts' => ['marketing'],
      'categories' => ['marketing'],
      'is_active' => true,
      'sort_order' => 1001,
    ]);

    $response = $this->actingAs($user)->get(route('admin.navigation.index', [
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'modal' => 'create-group',
    ]));

    $response->assertOk();
    $response->assertSee('value="images"', false);
    $response->assertSee('>Images</option>', false);
    $response->assertDontSee('value="hidden-icon"', false);
    $response->assertDontSee('value="marketing-only"', false);
  }

  #[Test]
  public function navigation_item_icon_validation_rejects_invalid_slugs(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();

    $response = $this->actingAs($user)
      ->from(route('admin.navigation.index', ['site_id' => $site->id, 'menu_key' => NavigationItem::MENU_DOCS, 'modal' => 'create-group']))
      ->post(route('admin.navigation.store'), [
        'site_id' => $site->id,
        'menu_key' => NavigationItem::MENU_DOCS,
        'title' => 'Patterns',
        'link_type' => NavigationItem::LINK_GROUP,
        'icon' => 'not-a-real-icon',
        'visibility' => NavigationItem::VISIBILITY_VISIBLE,
        '_navigation_modal' => 'navigationCreateGroupModal',
      ]);

    $response->assertRedirect(route('admin.navigation.index', ['site_id' => $site->id, 'menu_key' => NavigationItem::MENU_DOCS, 'modal' => 'create-group']));
    $response->assertSessionHasErrors(['icon']);
  }

  #[Test]
  public function nesting_a_child_item_under_a_group_works(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $page = $this->createPageForSite($site, 'Overview', 'overview');
    $group = NavigationItem::query()->create([
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'title' => 'Patterns',
      'link_type' => NavigationItem::LINK_GROUP,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $response = $this->actingAs($user)->post(route('admin.navigation.store'), [
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'parent_id' => $group->id,
      'link_type' => NavigationItem::LINK_PAGE,
      'page_id' => $page->id,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $response->assertRedirect(route('admin.navigation.index', ['site_id' => $site->id, 'menu_key' => NavigationItem::MENU_DOCS]));
    $this->assertDatabaseHas('navigation_items', [
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'page_id' => $page->id,
      'parent_id' => $group->id,
    ]);
  }

  #[Test]
  public function create_request_rejects_non_group_and_circular_parent_relationships(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $page = $this->createPageForSite($site, 'Overview', 'overview');
    $leaf = NavigationItem::query()->create([
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'title' => 'Leaf',
      'link_type' => NavigationItem::LINK_PAGE,
      'page_id' => $page->id,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);
    $group = NavigationItem::query()->create([
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'title' => 'Patterns',
      'link_type' => NavigationItem::LINK_GROUP,
      'position' => 2,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);
    $child = NavigationItem::query()->create([
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_DOCS,
      'parent_id' => $group->id,
      'title' => 'Child',
      'link_type' => NavigationItem::LINK_GROUP,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $nonGroupParent = $this->actingAs($user)
      ->from(route('admin.navigation.index', ['site_id' => $site->id, 'menu_key' => NavigationItem::MENU_DOCS, 'modal' => 'create-item']))
      ->post(route('admin.navigation.store'), [
        'site_id' => $site->id,
        'menu_key' => NavigationItem::MENU_DOCS,
        'parent_id' => $leaf->id,
        'title' => 'Nested wrong',
        'link_type' => NavigationItem::LINK_CUSTOM_URL,
        'url' => '/docs/wrong',
        'visibility' => NavigationItem::VISIBILITY_VISIBLE,
        '_navigation_modal' => 'navigationCreateItemModal',
      ]);

    $nonGroupParent->assertRedirect(route('admin.navigation.index', ['site_id' => $site->id, 'menu_key' => NavigationItem::MENU_DOCS, 'modal' => 'create-item']));
    $nonGroupParent->assertSessionHasErrors(['parent_id' => 'Only navigation groups can be selected as a parent.']);

    $circular = $this->actingAs($user)
      ->from(route('admin.navigation.index', ['site_id' => $site->id, 'menu_key' => NavigationItem::MENU_DOCS, 'modal' => 'edit-item', 'navigation' => $group->id]))
      ->put(route('admin.navigation.update', $group), [
        'site_id' => $site->id,
        'menu_key' => NavigationItem::MENU_DOCS,
        'parent_id' => $child->id,
        'title' => 'Patterns',
        'link_type' => NavigationItem::LINK_GROUP,
        'visibility' => NavigationItem::VISIBILITY_VISIBLE,
        '_navigation_modal' => 'navigationEditModal-'.$group->id,
      ]);

    $circular->assertRedirect(route('admin.navigation.index', ['site_id' => $site->id, 'menu_key' => NavigationItem::MENU_DOCS, 'modal' => 'edit-item', 'navigation' => $group->id]));
    $circular->assertSessionHasErrors(['parent_id' => 'A navigation item cannot be moved under its own child tree.']);
  }

  #[Test]
  public function navigation_writes_respect_site_scoping_for_site_admins_and_editors(): void
  {
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $otherSite = Site::query()->create([
      'name' => 'Secondary Site',
      'handle' => 'secondary-site',
      'domain' => 'secondary.example.test',
      'is_primary' => false,
    ]);
    $allowedPage = $this->createPageForSite($site, 'Allowed', 'allowed');
    $forbiddenPage = $this->createPageForSite($otherSite, 'Forbidden', 'forbidden');

    $siteAdmin = User::factory()->siteAdmin()->create();
    $siteAdmin->sites()->sync([$site->id]);
    $editor = User::factory()->editor()->create();
    $editor->sites()->sync([$site->id]);

    $this->actingAs($siteAdmin)
      ->post(route('admin.navigation.store'), [
        'site_id' => $site->id,
        'menu_key' => NavigationItem::MENU_DOCS,
        'link_type' => NavigationItem::LINK_PAGE,
        'page_id' => $allowedPage->id,
        'visibility' => NavigationItem::VISIBILITY_VISIBLE,
      ])
      ->assertRedirect(route('admin.navigation.index', ['site_id' => $site->id, 'menu_key' => NavigationItem::MENU_DOCS]));

    $this->actingAs($editor)
      ->post(route('admin.navigation.store'), [
        'site_id' => $site->id,
        'menu_key' => NavigationItem::MENU_DOCS,
        'link_type' => NavigationItem::LINK_PAGE,
        'page_id' => $allowedPage->id,
        'visibility' => NavigationItem::VISIBILITY_VISIBLE,
      ])
      ->assertRedirect(route('admin.navigation.index', ['site_id' => $site->id, 'menu_key' => NavigationItem::MENU_DOCS]));

    $this->assertSame(2, NavigationItem::query()->where('site_id', $site->id)->where('menu_key', NavigationItem::MENU_DOCS)->count());

    $this->actingAs($siteAdmin)
      ->post(route('admin.navigation.store'), [
        'site_id' => $otherSite->id,
        'menu_key' => NavigationItem::MENU_DOCS,
        'link_type' => NavigationItem::LINK_PAGE,
        'page_id' => $forbiddenPage->id,
        'visibility' => NavigationItem::VISIBILITY_VISIBLE,
      ])
      ->assertForbidden();

    $this->actingAs($editor)
      ->post(route('admin.navigation.store'), [
        'site_id' => $otherSite->id,
        'menu_key' => NavigationItem::MENU_DOCS,
        'link_type' => NavigationItem::LINK_PAGE,
        'page_id' => $forbiddenPage->id,
        'visibility' => NavigationItem::VISIBILITY_VISIBLE,
      ])
      ->assertForbidden();

    $this->assertSame(0, NavigationItem::query()->where('site_id', $otherSite->id)->count());
  }

  #[Test]
  public function reorder_endpoint_updates_parent_and_position_for_valid_payload(): void
  {
    $user = User::factory()->create();

    $group = NavigationItem::create([
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Group',
      'link_type' => NavigationItem::LINK_GROUP,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $about = NavigationItem::create([
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'About',
      'link_type' => NavigationItem::LINK_CUSTOM_URL,
      'url' => '/about',
      'position' => 2,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $contact = NavigationItem::create([
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Contact',
      'link_type' => NavigationItem::LINK_CUSTOM_URL,
      'url' => '/contact',
      'position' => 3,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $response = $this->actingAs($user)->postJson(route('admin.navigation.reorder'), [
      'menu_key' => 'primary',
      'items' => [
        ['id' => $contact->id, 'parent_id' => null, 'position' => 1],
        ['id' => $group->id, 'parent_id' => null, 'position' => 2],
        ['id' => $about->id, 'parent_id' => $group->id, 'position' => 1],
      ],
    ]);

    $response->assertOk();
    $response->assertJson([
      'ok' => true,
      'message' => 'Saved',
      'menu_key' => 'primary',
    ]);

    $this->assertDatabaseHas('navigation_items', ['id' => $contact->id, 'parent_id' => null, 'position' => 1]);
    $this->assertDatabaseHas('navigation_items', ['id' => $group->id, 'parent_id' => null, 'position' => 2]);
    $this->assertDatabaseHas('navigation_items', ['id' => $about->id, 'parent_id' => $group->id, 'position' => 1]);
  }

  #[Test]
  public function reorder_endpoint_rejects_cycles(): void
  {
    $user = User::factory()->create();

    $parent = NavigationItem::create([
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Parent',
      'link_type' => NavigationItem::LINK_GROUP,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $child = NavigationItem::create([
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Child',
      'link_type' => NavigationItem::LINK_CUSTOM_URL,
      'url' => '/child',
      'parent_id' => $parent->id,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $response = $this->actingAs($user)->postJson(route('admin.navigation.reorder'), [
      'menu_key' => 'primary',
      'items' => [
        ['id' => $parent->id, 'parent_id' => $child->id, 'position' => 1],
        ['id' => $child->id, 'parent_id' => $parent->id, 'position' => 1],
      ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['items']);
  }

  #[Test]
  public function reorder_endpoint_rejects_cross_menu_parent_mixing(): void
  {
    $user = User::factory()->create();

    $primary = NavigationItem::create([
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Primary',
      'link_type' => NavigationItem::LINK_GROUP,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $footer = NavigationItem::create([
      'menu_key' => NavigationItem::MENU_FOOTER,
      'title' => 'Footer',
      'link_type' => NavigationItem::LINK_GROUP,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $response = $this->actingAs($user)->postJson(route('admin.navigation.reorder'), [
      'menu_key' => 'primary',
      'items' => [
        ['id' => $primary->id, 'parent_id' => $footer->id, 'position' => 1],
      ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['items']);
  }

  #[Test]
  public function reorder_endpoint_rejects_depth_above_three_levels(): void
  {
    $user = User::factory()->create();

    $levelOne = NavigationItem::create([
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Level 1',
      'link_type' => NavigationItem::LINK_GROUP,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $levelTwo = NavigationItem::create([
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Level 2',
      'link_type' => NavigationItem::LINK_GROUP,
      'parent_id' => $levelOne->id,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $levelThree = NavigationItem::create([
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Level 3',
      'link_type' => NavigationItem::LINK_GROUP,
      'parent_id' => $levelTwo->id,
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $levelFour = NavigationItem::create([
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Level 4',
      'link_type' => NavigationItem::LINK_GROUP,
      'position' => 2,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $response = $this->actingAs($user)->postJson(route('admin.navigation.reorder'), [
      'menu_key' => 'primary',
      'items' => [
        ['id' => $levelOne->id, 'parent_id' => null, 'position' => 1],
        ['id' => $levelTwo->id, 'parent_id' => $levelOne->id, 'position' => 1],
        ['id' => $levelThree->id, 'parent_id' => $levelTwo->id, 'position' => 1],
        ['id' => $levelFour->id, 'parent_id' => $levelThree->id, 'position' => 1],
      ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['items']);
  }

  #[Test]
  public function reorder_endpoint_rejects_nesting_under_non_group_items(): void
  {
    $user = User::factory()->create();

    $leaf = NavigationItem::create([
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Leaf',
      'link_type' => NavigationItem::LINK_CUSTOM_URL,
      'url' => '/leaf',
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $child = NavigationItem::create([
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Child',
      'link_type' => NavigationItem::LINK_CUSTOM_URL,
      'url' => '/child',
      'position' => 2,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $response = $this->actingAs($user)->postJson(route('admin.navigation.reorder'), [
      'menu_key' => 'primary',
      'items' => [
        ['id' => $leaf->id, 'parent_id' => null, 'position' => 1],
        ['id' => $child->id, 'parent_id' => $leaf->id, 'position' => 1],
      ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['items']);
  }
}
