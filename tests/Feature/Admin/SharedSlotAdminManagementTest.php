<?php

namespace Tests\Feature\Admin;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\SharedSlot;
use App\Models\SharedSlotBlock;
use App\Models\Site;
use App\Models\SlotType;
use App\Models\User;
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
}
