<?php

namespace Tests\Feature\Admin;

use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NavigationTreeEditorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_navigation_items_screen_loads_with_menu_filtering(): void
    {
        $user = User::factory()->create();
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
        $response->assertSee('Footer About Link');
        $response->assertDontSee('Primary Home Link');
        $response->assertSee('Add Item');
        $response->assertSee('Add Group');
    }

    #[Test]
    public function reorder_endpoint_updates_parent_and_position_for_valid_payload(): void
    {
        $user = User::factory()->create();

        $home = NavigationItem::create([
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'title' => 'Home',
            'link_type' => NavigationItem::LINK_CUSTOM_URL,
            'url' => '/',
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
                ['id' => $home->id, 'parent_id' => null, 'position' => 2],
                ['id' => $about->id, 'parent_id' => $home->id, 'position' => 1],
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'message' => 'Saved',
            'menu_key' => 'primary',
        ]);

        $this->assertDatabaseHas('navigation_items', ['id' => $contact->id, 'parent_id' => null, 'position' => 1]);
        $this->assertDatabaseHas('navigation_items', ['id' => $home->id, 'parent_id' => null, 'position' => 2]);
        $this->assertDatabaseHas('navigation_items', ['id' => $about->id, 'parent_id' => $home->id, 'position' => 1]);
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
}
