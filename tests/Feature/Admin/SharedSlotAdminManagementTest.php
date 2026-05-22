<?php

namespace Tests\Feature\Admin;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\SharedSlotBlock;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use App\Models\User;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SharedSlotAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private function seedFoundation(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);
    }

    private function defaultSite(): Site
    {
        return Site::query()->where('is_primary', true)->firstOrFail();
    }

    private function secondarySite(): Site
    {
        return Site::query()->firstOrCreate(
            ['handle' => 'secondary-site'],
            ['name' => 'Secondary Site', 'domain' => 'secondary.example.test', 'is_primary' => false],
        );
    }

    private function siteAdminFor(Site $site): User
    {
        $user = User::factory()->siteAdmin()->create();
        $user->sites()->sync([$site->id]);

        return $user;
    }

    private function editorFor(Site $site): User
    {
        $user = User::factory()->editor()->create();
        $user->sites()->sync([$site->id]);

        return $user;
    }

    private function slotType(string $slug, string $name, int $sortOrder): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => true],
        );
    }

    private function sharedSlotFor(Site $site, array $attributes = []): SharedSlot
    {
        return SharedSlot::query()->create([
            'site_id' => $site->id,
            'name' => $attributes['name'] ?? 'Reusable Header',
            'handle' => $attributes['handle'] ?? 'reusable-header',
            'slot_name' => $attributes['slot_name'] ?? 'header',
            'public_shell' => $attributes['public_shell'] ?? 'docs',
            'is_active' => $attributes['is_active'] ?? true,
        ]);
    }

    private function pageFor(Site $site, string $slug, string $status = Page::STATUS_PUBLISHED, ?string $shell = 'docs'): Page
    {
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => $status,
            'settings' => ['public_shell' => $shell],
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => ucfirst(str_replace('-', ' ', $slug)), 'slug' => $slug, 'path' => $slug === 'home' ? '/' : '/p/'.$slug],
        );

        return $page;
    }

    #[Test]
    public function super_admin_can_list_shared_slots(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $this->sharedSlotFor($site, ['name' => 'Primary Header']);
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.shared-slots.index'));

        $response->assertOk();
        $response->assertSee('Shared Slots');
        $response->assertSee('Primary Header');
        $response->assertSee('Page Layout');
        $response->assertDontSee('Public Shell');
    }

    #[Test]
    public function shared_slot_forms_show_page_layout_wording_instead_of_public_shell(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $sharedSlot = $this->sharedSlotFor($site);
        $user = User::factory()->superAdmin()->create();

        $create = $this->actingAs($user)->get(route('admin.shared-slots.create'));
        $create->assertOk();
        $create->assertSee('Page Layout');
        $create->assertSee('Any Page Layout');
        $create->assertSee('Leave empty to keep this Shared Slot generic across any Page Layout.');
        $create->assertDontSee('Public Shell');

        $edit = $this->actingAs($user)->get(route('admin.shared-slots.edit', $sharedSlot));
        $edit->assertOk();
        $edit->assertSee('Page Layout:');
        $edit->assertSee('Docs Layout');
        $edit->assertDontSee('Public Shell');
    }

    #[Test]
    public function shared_slots_index_loads_an_informative_empty_state_when_schema_is_missing(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();

        Schema::dropIfExists('shared_slot_blocks');
        Schema::dropIfExists('shared_slots');

        $response = $this->actingAs($user)->get(route('admin.shared-slots.index'));

        $response->assertOk();
        $response->assertSee('Shared Slots are not ready yet');
        $response->assertSee('Run the latest migrations before using Shared Slot admin screens in this environment.');
    }

    #[Test]
    public function shared_slot_admin_actions_redirect_cleanly_when_shared_slot_schema_is_missing(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();

        Schema::dropIfExists('shared_slot_blocks');
        Schema::dropIfExists('shared_slots');

        $this->actingAs($user)
            ->get(route('admin.shared-slots.create'))
            ->assertRedirect(route('admin.shared-slots.index'))
            ->assertSessionHasErrors('shared_slots');

        $this->actingAs($user)
            ->from(route('admin.shared-slots.create'))
            ->post(route('admin.shared-slots.store'), [
                'site_id' => $this->defaultSite()->id,
                'name' => 'Header',
                'handle' => 'header',
                'slot_name' => 'header',
                'public_shell' => 'docs',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.shared-slots.create'))
            ->assertSessionHasErrors('shared_slots');

        $this->actingAs($user)
            ->get(route('admin.shared-slots.edit', ['shared_slot' => 999]))
            ->assertRedirect(route('admin.shared-slots.index'))
            ->assertSessionHasErrors('shared_slots');

        $this->actingAs($user)
            ->delete(route('admin.shared-slots.destroy', ['shared_slot' => 999]))
            ->assertRedirect(route('admin.shared-slots.index'))
            ->assertSessionHasErrors('shared_slots');

        $this->actingAs($user)
            ->get(route('admin.shared-slots.blocks.edit', ['shared_slot' => 999]))
            ->assertRedirect(route('admin.shared-slots.index'))
            ->assertSessionHasErrors('shared_slots');
    }

    #[Test]
    public function site_admin_can_list_only_shared_slots_for_assigned_sites(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $otherSite = $this->secondarySite();
        $this->sharedSlotFor($site, ['name' => 'Visible Slot']);
        $this->sharedSlotFor($otherSite, ['name' => 'Hidden Slot', 'handle' => 'hidden-slot']);
        $user = $this->siteAdminFor($site);

        $response = $this->actingAs($user)->get(route('admin.shared-slots.index'));

        $response->assertOk();
        $response->assertSee('Visible Slot');
        $response->assertDontSee('Hidden Slot');
    }

    #[Test]
    public function editor_can_list_and_edit_shared_slot_blocks_but_cannot_create_or_delete_shared_slots(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $sharedSlot = $this->sharedSlotFor($site);
        $editor = $this->editorFor($site);

        $index = $this->actingAs($editor)->get(route('admin.shared-slots.index'));
        $index->assertOk();
        $index->assertSee($sharedSlot->name);
        $index->assertDontSee('New Shared Slot');

        $this->actingAs($editor)
            ->get(route('admin.shared-slots.blocks.edit', $sharedSlot))
            ->assertOk();

        $this->actingAs($editor)
            ->get(route('admin.shared-slots.create'))
            ->assertForbidden();

        $this->actingAs($editor)
            ->delete(route('admin.shared-slots.destroy', $sharedSlot))
            ->assertForbidden();
    }

    #[Test]
    public function create_shared_slot_validates_required_fields(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)
            ->from(route('admin.shared-slots.create'))
            ->post(route('admin.shared-slots.store'), [
                'site_id' => '',
                'name' => '',
                'handle' => '',
            ]);

        $response->assertRedirect(route('admin.shared-slots.create'));
        $response->assertSessionHasErrors(['name', 'handle']);
    }

    #[Test]
    public function handle_is_unique_per_site_but_can_repeat_across_different_sites(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $otherSite = $this->secondarySite();
        $this->sharedSlotFor($site, ['handle' => 'global-header']);
        $user = User::factory()->superAdmin()->create();

        $duplicate = $this->actingAs($user)
            ->from(route('admin.shared-slots.create'))
            ->post(route('admin.shared-slots.store'), [
                'site_id' => $site->id,
                'name' => 'Another Header',
                'handle' => 'global-header',
                'slot_name' => 'header',
                'public_shell' => 'docs',
                'is_active' => '1',
            ]);

        $duplicate->assertRedirect(route('admin.shared-slots.create'));
        $duplicate->assertSessionHasErrors('handle');

        $allowed = $this->actingAs($user)->post(route('admin.shared-slots.store'), [
            'site_id' => $otherSite->id,
            'name' => 'Site Two Header',
            'handle' => 'global-header',
            'slot_name' => 'header',
            'public_shell' => 'docs',
            'is_active' => '1',
        ]);

        $sharedSlot = SharedSlot::query()->where('site_id', $otherSite->id)->where('handle', 'global-header')->firstOrFail();
        $allowed->assertRedirect(route('admin.shared-slots.edit', $sharedSlot));
    }

    #[Test]
    public function users_cannot_create_or_edit_shared_slots_for_unauthorized_sites(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $otherSite = $this->secondarySite();
        $user = $this->siteAdminFor($site);
        $sharedSlot = $this->sharedSlotFor($site);

        $create = $this->actingAs($user)
            ->from(route('admin.shared-slots.create'))
            ->post(route('admin.shared-slots.store'), [
                'site_id' => $otherSite->id,
                'name' => 'Cross Site Slot',
                'handle' => 'cross-site-slot',
                'slot_name' => 'main',
                'public_shell' => 'default',
                'is_active' => '1',
            ]);

        $create->assertRedirect(route('admin.shared-slots.create'));
        $create->assertSessionHasErrors('site_id');

        $update = $this->actingAs($user)
            ->from(route('admin.shared-slots.edit', $sharedSlot))
            ->put(route('admin.shared-slots.update', $sharedSlot), [
                'site_id' => $otherSite->id,
                'name' => 'Moved Slot',
                'handle' => 'moved-slot',
                'slot_name' => 'main',
                'public_shell' => 'default',
                'is_active' => '1',
            ]);

        $update->assertRedirect(route('admin.shared-slots.edit', $sharedSlot));
        $update->assertSessionHasErrors('site_id');
    }

    #[Test]
    public function update_shared_slot_metadata_and_status_work(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $sharedSlot = $this->sharedSlotFor($site);
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->put(route('admin.shared-slots.update', $sharedSlot), [
            'site_id' => $site->id,
            'name' => 'Updated Shared Header',
            'handle' => 'updated-shared-header',
            'slot_name' => 'footer',
            'public_shell' => 'default',
            'is_active' => '0',
        ]);

        $response->assertRedirect(route('admin.shared-slots.edit', $sharedSlot));

        $sharedSlot->refresh();
        $this->assertSame('Updated Shared Header', $sharedSlot->name);
        $this->assertSame('updated-shared-header', $sharedSlot->handle);
        $this->assertSame('footer', $sharedSlot->slot_name);
        $this->assertSame('default', $sharedSlot->public_shell);
        $this->assertFalse($sharedSlot->is_active);
    }

    #[Test]
    public function unreferenced_shared_slot_can_be_deleted_safely(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $sharedSlot = $this->sharedSlotFor($site);
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->delete(route('admin.shared-slots.destroy', $sharedSlot));

        $response->assertRedirect(route('admin.shared-slots.index', ['site' => $site->id]));
        $this->assertDatabaseMissing('shared_slots', ['id' => $sharedSlot->id]);
    }

    #[Test]
    public function referenced_shared_slot_cannot_be_deleted_and_page_slot_reference_remains_intact(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $sharedSlot = $this->sharedSlotFor($site);
        $slotType = $this->slotType('header', 'Header', 1);
        $page = $this->pageFor($site, 'home');
        $pageSlot = PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
            'shared_slot_id' => $sharedSlot->id,
            'sort_order' => 0,
        ]);
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->delete(route('admin.shared-slots.destroy', $sharedSlot));

        $response->assertRedirect(route('admin.shared-slots.edit', $sharedSlot));
        $response->assertSessionHasErrors('shared_slot');
        $this->assertDatabaseHas('shared_slots', ['id' => $sharedSlot->id]);
        $this->assertDatabaseHas('page_slots', ['id' => $pageSlot->id, 'shared_slot_id' => $sharedSlot->id]);
    }

    #[Test]
    public function edit_blocks_screen_loads_for_allowed_shared_slot(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $sharedSlot = $this->sharedSlotFor($site);
        $user = $this->siteAdminFor($site);

        $response = $this->actingAs($user)->get(route('admin.shared-slots.blocks.edit', $sharedSlot));

        $response->assertOk();
        $response->assertSee('Edit Shared Slot Blocks');
        $response->assertSee($sharedSlot->name);
    }

    #[Test]
    public function adding_a_block_to_a_shared_slot_creates_the_expected_shared_slot_block_relationship(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $sharedSlot = $this->sharedSlotFor($site, ['slot_name' => 'header']);
        $headerSlotType = $this->slotType('header', 'Header', 1);
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'shared_slot_id' => $sharedSlot->id,
            'page_id' => Page::query()->where('page_type', Page::TYPE_SHARED_SLOT_SOURCE)->where('settings->shared_slot_id', $sharedSlot->id)->value('id') ?: Page::query()->create([
                'site_id' => $site->id,
                'title' => 'Temp Shared Slot Source',
                'slug' => 'temp-shared-slot-source',
                'page_type' => Page::TYPE_SHARED_SLOT_SOURCE,
                'status' => Page::STATUS_DRAFT,
                'settings' => ['shared_slot_id' => $sharedSlot->id],
            ])->id,
            'parent_id' => null,
            'block_type_id' => $plainTextType->id,
            'slot_type_id' => $headerSlotType->id,
            'sort_order' => 0,
            'text' => 'Shared slot intro text',
            'status' => 'published',
        ]);

        $response->assertRedirect(route('admin.shared-slots.blocks.edit', $sharedSlot));

        $block = Block::query()->where('page_id', Page::query()->where('page_type', Page::TYPE_SHARED_SLOT_SOURCE)->where('settings->shared_slot_id', $sharedSlot->id)->value('id'))->firstOrFail();
        $assignment = SharedSlotBlock::query()->where('shared_slot_id', $sharedSlot->id)->where('block_id', $block->id)->first();

        $this->assertNotNull($assignment);
        $this->assertNull($assignment->parent_id);
        $this->assertSame(0, $assignment->sort_order);
    }

    #[Test]
    public function shared_slot_block_create_route_redirects_hidden_source_pages_back_to_the_shared_slot_editor(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $sharedSlot = $this->sharedSlotFor($site, ['slot_name' => 'sidebar']);
        $sidebarSlotType = $this->slotType('sidebar', 'Sidebar', 2);
        $brandType = BlockType::query()->where('slug', 'sidebar-brand')->firstOrFail();
        $sourcePage = app(SharedSlotSourcePageManager::class)->ensureFor($sharedSlot);
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.blocks.create', [
            'page_id' => $sourcePage->id,
            'slot_type_id' => $sidebarSlotType->id,
            'block_type_id' => $brandType->id,
        ]));

        $response->assertRedirect(route('admin.shared-slots.blocks.edit', [
            'shared_slot' => $sharedSlot,
            'block_type_id' => $brandType->id,
            'picker' => 1,
        ]));
    }

    #[Test]
    public function creating_updating_and_deleting_blocks_from_shared_slot_context_stay_in_that_editor(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $sharedSlot = $this->sharedSlotFor($site, ['slot_name' => 'sidebar']);
        $sidebarSlotType = $this->slotType('sidebar', 'Sidebar', 2);
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
        $sourcePage = app(SharedSlotSourcePageManager::class)->ensureFor($sharedSlot);
        $user = User::factory()->superAdmin()->create();

        $create = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $sourcePage->id,
            'slot_type_id' => $sidebarSlotType->id,
            'block_type_id' => $plainTextType->id,
            'parent_id' => null,
            'sort_order' => 0,
            'text' => 'Shared sidebar copy',
            'status' => 'published',
        ]);

        $create->assertRedirect(route('admin.shared-slots.blocks.edit', $sharedSlot));

        $block = Block::query()
            ->where('page_id', $sourcePage->id)
            ->where('type', 'plain_text')
            ->firstOrFail();

        $this->actingAs($user)->put(route('admin.blocks.update', $block), [
            'page_id' => $sourcePage->id,
            'slot_type_id' => $sidebarSlotType->id,
            'block_type_id' => $plainTextType->id,
            'parent_id' => null,
            'sort_order' => 0,
            'text' => 'Updated shared sidebar copy',
            'status' => 'published',
        ])->assertRedirect(route('admin.shared-slots.blocks.edit', $sharedSlot));

        $this->actingAs($user)
            ->delete(route('admin.blocks.destroy', $block))
            ->assertRedirect(route('admin.shared-slots.blocks.edit', $sharedSlot));
    }

    #[Test]
    public function recursive_delete_in_shared_slot_context_removes_only_the_selected_shared_slot_subtree(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $otherSite = $this->secondarySite();
        $sharedSlot = $this->sharedSlotFor($site, ['slot_name' => 'main']);
        $otherSharedSlot = $this->sharedSlotFor($otherSite, ['name' => 'Other Shared Slot', 'handle' => 'other-shared-slot', 'slot_name' => 'main']);
        $mainSlotType = $this->slotType('main', 'Main', 2);
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
        $sourcePage = app(SharedSlotSourcePageManager::class)->ensureFor($sharedSlot);
        $otherSourcePage = app(SharedSlotSourcePageManager::class)->ensureFor($otherSharedSlot);
        $user = User::factory()->superAdmin()->create();

        $parent = Block::query()->create([
            'page_id' => $sourcePage->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        $child = Block::query()->create([
            'page_id' => $sourcePage->id,
            'parent_id' => $parent->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 0,
            'content' => 'Shared child',
            'status' => 'published',
            'is_system' => false,
        ]);
        $sibling = Block::query()->create([
            'page_id' => $sourcePage->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 1,
            'content' => 'Shared sibling',
            'status' => 'published',
            'is_system' => false,
        ]);
        $otherSharedBlock = Block::query()->create([
            'page_id' => $otherSourcePage->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 0,
            'content' => 'Other shared slot block',
            'status' => 'published',
            'is_system' => false,
        ]);
        app(SharedSlotSourcePageManager::class)->rebuildAssignments($sharedSlot);
        app(SharedSlotSourcePageManager::class)->rebuildAssignments($otherSharedSlot);

        $response = $this->actingAs($user)
            ->delete(route('admin.blocks.destroy', $parent), [
                'shared_slot_id' => $sharedSlot->id,
                'delete_descendants' => '1',
            ]);

        $response->assertRedirect(route('admin.shared-slots.blocks.edit', $sharedSlot));
        $response->assertSessionHas('status', 'Block and nested child blocks deleted.');
        $this->assertDatabaseMissing('blocks', ['id' => $parent->id]);
        $this->assertDatabaseMissing('blocks', ['id' => $child->id]);
        $this->assertDatabaseHas('blocks', ['id' => $sibling->id]);
        $this->assertDatabaseHas('blocks', ['id' => $otherSharedBlock->id]);
    }

    #[Test]
    public function users_cannot_forge_shared_slot_context_for_a_source_page_from_another_site(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $otherSite = $this->secondarySite();
        $localSharedSlot = $this->sharedSlotFor($site, ['slot_name' => 'header']);
        $foreignSharedSlot = $this->sharedSlotFor($otherSite, ['name' => 'Foreign Slot', 'handle' => 'foreign-slot', 'slot_name' => 'header']);
        $headerSlotType = $this->slotType('header', 'Header', 1);
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
        $foreignSourcePage = app(SharedSlotSourcePageManager::class)->ensureFor($foreignSharedSlot);
        $user = $this->siteAdminFor($site);

        $this->actingAs($user)
            ->post(route('admin.blocks.store'), [
                'shared_slot_id' => $localSharedSlot->id,
                'page_id' => $foreignSourcePage->id,
                'slot_type_id' => $headerSlotType->id,
                'block_type_id' => $plainTextType->id,
                'parent_id' => null,
                'sort_order' => 0,
                'text' => 'Forged copy',
                'status' => 'published',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function shared_slot_block_order_and_nesting_persist(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $sharedSlot = $this->sharedSlotFor($site, ['slot_name' => 'main']);
        $mainSlotType = $this->slotType('main', 'Main', 2);
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
        $user = User::factory()->superAdmin()->create();

        $sourcePage = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Shared Slot Source',
            'slug' => 'shared-slot-source',
            'page_type' => Page::TYPE_SHARED_SLOT_SOURCE,
            'status' => Page::STATUS_DRAFT,
            'settings' => ['shared_slot_id' => $sharedSlot->id],
        ]);

        PageSlot::query()->create([
            'page_id' => $sourcePage->id,
            'slot_type_id' => $mainSlotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 0,
        ]);

        $parent = Block::query()->create([
            'page_id' => $sourcePage->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        $child = Block::query()->create([
            'page_id' => $sourcePage->id,
            'parent_id' => $parent->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        $sibling = Block::query()->create([
            'page_id' => $sourcePage->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 1,
            'status' => 'published',
            'is_system' => false,
        ]);

        SharedSlotBlock::query()->create(['shared_slot_id' => $sharedSlot->id, 'block_id' => $parent->id, 'parent_id' => null, 'sort_order' => 0]);
        $parentAssignment = SharedSlotBlock::query()->firstWhere('block_id', $parent->id);
        SharedSlotBlock::query()->create(['shared_slot_id' => $sharedSlot->id, 'block_id' => $child->id, 'parent_id' => $parentAssignment->id, 'sort_order' => 0]);
        SharedSlotBlock::query()->create(['shared_slot_id' => $sharedSlot->id, 'block_id' => $sibling->id, 'parent_id' => null, 'sort_order' => 1]);

        $response = $this->actingAs($user)->postJson(route('admin.shared-slots.blocks.reorder', $sharedSlot), [
            'blocks' => [$sibling->id, $parent->id],
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $rootAssignments = SharedSlotBlock::query()
            ->where('shared_slot_id', $sharedSlot->id)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->pluck('block_id')
            ->all();
        $childAssignment = SharedSlotBlock::query()->where('shared_slot_id', $sharedSlot->id)->where('block_id', $child->id)->firstOrFail();

        $this->assertSame([$sibling->id, $parent->id], $rootAssignments);
        $this->assertNotNull($childAssignment->parent_id);
    }

    #[Test]
    public function public_rendering_still_works_after_admin_created_shared_slot_content_is_added(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $headerSlotType = $this->slotType('header', 'Header', 1);
        $sharedSlot = $this->sharedSlotFor($site, ['slot_name' => 'header', 'public_shell' => 'docs']);
        $page = $this->pageFor($site, 'home', Page::STATUS_PUBLISHED, 'docs');
        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $headerSlotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
            'shared_slot_id' => $sharedSlot->id,
            'sort_order' => 0,
        ]);
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'shared_slot_id' => $sharedSlot->id,
            'page_id' => Page::query()->where('page_type', Page::TYPE_SHARED_SLOT_SOURCE)->where('settings->shared_slot_id', $sharedSlot->id)->value('id') ?: Page::query()->create([
                'site_id' => $site->id,
                'title' => 'Admin Shared Slot Source',
                'slug' => 'admin-shared-slot-source',
                'page_type' => Page::TYPE_SHARED_SLOT_SOURCE,
                'status' => Page::STATUS_DRAFT,
                'settings' => ['shared_slot_id' => $sharedSlot->id, 'public_shell' => 'docs'],
            ])->id,
            'parent_id' => null,
            'block_type_id' => $plainTextType->id,
            'slot_type_id' => $headerSlotType->id,
            'sort_order' => 0,
            'text' => 'Admin created shared header text',
            'status' => 'published',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Admin created shared header text', false);
    }

    #[Test]
    public function existing_page_owned_slot_admin_and_public_behavior_remains_unchanged(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $mainSlotType = $this->slotType('main', 'Main', 2);
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
        $page = $this->pageFor($site, 'about', Page::STATUS_PUBLISHED, 'default');
        $pageSlot = PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $mainSlotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 0,
        ]);
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 0,
            'content' => 'Page owned content',
            'status' => 'published',
            'is_system' => false,
        ]);
        $user = User::factory()->superAdmin()->create();

        $adminResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot]));
        $publicResponse = $this->get('/p/about');

        $adminResponse->assertOk();
        $adminResponse->assertSee('Blocks');
        $publicResponse->assertOk();
        $publicResponse->assertSee('Page owned content', false);
        $this->assertDatabaseMissing('shared_slot_blocks', ['block_id' => $block->id]);
    }

    #[Test]
    public function shared_slot_delete_all_blocks_action_deletes_only_that_shared_slot_tree_and_records_revision(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $sharedSlot = $this->sharedSlotFor($site, ['slot_name' => 'main']);
        $mainSlotType = $this->slotType('main', 'Main', 1);
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
        $user = User::factory()->superAdmin()->create();

        $sourcePage = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Shared Slot Source',
            'slug' => 'shared-slot-source',
            'page_type' => Page::TYPE_SHARED_SLOT_SOURCE,
            'status' => Page::STATUS_DRAFT,
            'settings' => ['shared_slot_id' => $sharedSlot->id],
        ]);

        PageSlot::query()->create([
            'page_id' => $sourcePage->id,
            'slot_type_id' => $mainSlotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 0,
        ]);

        $parent = Block::query()->create([
            'page_id' => $sourcePage->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        $child = Block::query()->create([
            'page_id' => $sourcePage->id,
            'parent_id' => $parent->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        $sibling = Block::query()->create([
            'page_id' => $sourcePage->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 1,
            'status' => 'published',
            'is_system' => false,
        ]);

        SharedSlotBlock::query()->create(['shared_slot_id' => $sharedSlot->id, 'block_id' => $parent->id, 'parent_id' => null, 'sort_order' => 0]);
        $parentAssignment = SharedSlotBlock::query()->firstWhere('block_id', $parent->id);
        SharedSlotBlock::query()->create(['shared_slot_id' => $sharedSlot->id, 'block_id' => $child->id, 'parent_id' => $parentAssignment->id, 'sort_order' => 0]);
        SharedSlotBlock::query()->create(['shared_slot_id' => $sharedSlot->id, 'block_id' => $sibling->id, 'parent_id' => null, 'sort_order' => 1]);

        $view = $this->actingAs($user)->get(route('admin.shared-slots.blocks.edit', ['shared_slot' => $sharedSlot, 'delete_all' => 1]));

        $view->assertOk();
        $view->assertSee('Delete All Blocks');
        $view->assertSee('Shared Slot: '.$sharedSlot->name);
        $view->assertSee('Top-level blocks:</strong> 2', false);
        $view->assertSee('Nested descendants:</strong> 1', false);

        $response = $this->actingAs($user)->delete(route('admin.shared-slots.blocks.destroy-all', $sharedSlot), [
            'confirm_delete_all_blocks' => '1',
        ]);

        $response->assertRedirect(route('admin.shared-slots.blocks.edit', $sharedSlot));
        $response->assertSessionHas('status', 'Deleted all blocks from Main.');
        $this->assertDatabaseMissing('blocks', ['id' => $parent->id]);
        $this->assertDatabaseMissing('blocks', ['id' => $child->id]);
        $this->assertDatabaseMissing('blocks', ['id' => $sibling->id]);
        $this->assertDatabaseHas('shared_slot_revisions', [
            'shared_slot_id' => $sharedSlot->id,
            'source_event' => 'block_deleted',
            'label' => 'Shared Slot blocks deleted',
        ]);
    }
}
