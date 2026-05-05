<?php

namespace Tests\Feature\Admin;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\SharedSlot;
use App\Models\SharedSlotRevision;
use App\Models\Site;
use App\Models\SlotType;
use App\Models\User;
use App\Support\Blocks\BlockTranslationResolver;
use App\Support\SharedSlots\SharedSlotSourcePageManager;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SharedSlotRevisionTest extends TestCase
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

    private function defaultLocale(): Locale
    {
        return Locale::query()->where('is_default', true)->firstOrFail();
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

    private function sourcePageFor(SharedSlot $sharedSlot): Page
    {
        return app(SharedSlotSourcePageManager::class)->ensureFor($sharedSlot);
    }

    #[Test]
    public function creating_a_shared_slot_creates_an_initial_revision(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->post(route('admin.shared-slots.store'), [
            'site_id' => $site->id,
            'name' => 'Global Header',
            'handle' => 'global-header',
            'slot_name' => 'header',
            'public_shell' => 'docs',
            'is_active' => '1',
        ]);

        $sharedSlot = SharedSlot::query()->where('handle', 'global-header')->firstOrFail();

        $response->assertRedirect(route('admin.shared-slots.edit', $sharedSlot));
        $this->assertDatabaseHas('shared_slot_revisions', [
            'shared_slot_id' => $sharedSlot->id,
            'user_id' => $user->id,
            'source_event' => 'created',
            'label' => 'Shared Slot created',
        ]);
    }

    #[Test]
    public function updating_shared_slot_metadata_creates_a_revision_but_noop_update_does_not(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $sharedSlot = $this->sharedSlotFor($site);
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)->put(route('admin.shared-slots.update', $sharedSlot), [
            'site_id' => $site->id,
            'name' => 'Updated Shared Header',
            'handle' => 'updated-shared-header',
            'slot_name' => 'footer',
            'public_shell' => 'default',
            'is_active' => '1',
        ])->assertRedirect(route('admin.shared-slots.edit', $sharedSlot));

        $this->assertDatabaseHas('shared_slot_revisions', [
            'shared_slot_id' => $sharedSlot->id,
            'source_event' => 'metadata_updated',
        ]);

        $beforeCount = SharedSlotRevision::query()->where('shared_slot_id', $sharedSlot->id)->count();

        $this->actingAs($user)->put(route('admin.shared-slots.update', $sharedSlot), [
            'site_id' => $site->id,
            'name' => 'Updated Shared Header',
            'handle' => 'updated-shared-header',
            'slot_name' => 'footer',
            'public_shell' => 'default',
            'is_active' => '1',
        ])->assertRedirect(route('admin.shared-slots.edit', $sharedSlot));

        $this->assertSame($beforeCount, SharedSlotRevision::query()->where('shared_slot_id', $sharedSlot->id)->count());
    }

    #[Test]
    public function changing_shared_slot_status_creates_a_revision(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $sharedSlot = $this->sharedSlotFor($site);
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)->put(route('admin.shared-slots.update', $sharedSlot), [
            'site_id' => $site->id,
            'name' => $sharedSlot->name,
            'handle' => $sharedSlot->handle,
            'slot_name' => $sharedSlot->slot_name,
            'public_shell' => $sharedSlot->public_shell,
            'is_active' => '0',
        ])->assertRedirect(route('admin.shared-slots.edit', $sharedSlot));

        $this->assertDatabaseHas('shared_slot_revisions', [
            'shared_slot_id' => $sharedSlot->id,
            'source_event' => 'status_updated',
        ]);
    }

    #[Test]
    public function shared_slot_block_mutations_create_revisions_and_snapshot_captures_nested_translated_tree(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
        $sharedSlot = $this->sharedSlotFor($site, ['slot_name' => 'main']);
        $mainSlotType = $this->slotType('main', 'Main', 1);
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
        $user = User::factory()->superAdmin()->create();
        $sourcePage = $this->sourcePageFor($sharedSlot);

        $createParent = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'shared_slot_id' => $sharedSlot->id,
            'page_id' => $sourcePage->id,
            'parent_id' => null,
            'block_type_id' => $sectionType->id,
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 0,
            'title' => 'Parent Section',
            'status' => 'published',
        ]);

        $createParent->assertRedirect(route('admin.shared-slots.blocks.edit', $sharedSlot));
        $parent = Block::query()->where('page_id', $sourcePage->id)->where('type', 'section')->firstOrFail();

        $createChild = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'shared_slot_id' => $sharedSlot->id,
            'page_id' => $sourcePage->id,
            'parent_id' => $parent->id,
            'block_type_id' => $plainTextType->id,
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 0,
            'locale' => 'tr',
            'text' => 'Merhaba dunya',
            'status' => 'published',
        ]);

        $createChild->assertRedirect(route('admin.shared-slots.blocks.edit', ['shared_slot' => $sharedSlot, 'locale' => 'tr']));
        $child = Block::query()->where('page_id', $sourcePage->id)->where('type', 'plain_text')->firstOrFail();

        $this->actingAs($user)->put(route('admin.blocks.update', $child), [
            'shared_slot_id' => $sharedSlot->id,
            'page_id' => $sourcePage->id,
            'parent_id' => $parent->id,
            'block_type_id' => $plainTextType->id,
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 0,
            'locale' => 'tr',
            'text' => 'Guncellenen icerik',
            'status' => 'published',
            '_slot_block_mode' => 'edit',
            '_slot_block_id' => $child->id,
        ])->assertRedirect(route('admin.shared-slots.blocks.edit', ['shared_slot' => $sharedSlot, 'locale' => 'tr']));

        $siblingResponse = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'shared_slot_id' => $sharedSlot->id,
            'page_id' => $sourcePage->id,
            'parent_id' => null,
            'block_type_id' => $plainTextType->id,
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 1,
            'text' => 'Sibling text',
            'status' => 'published',
        ]);

        $siblingResponse->assertRedirect(route('admin.shared-slots.blocks.edit', $sharedSlot));
        $sibling = Block::query()->where('page_id', $sourcePage->id)->where('id', '!=', $child->id)->where('type', 'plain_text')->firstOrFail();

        $this->actingAs($user)->postJson(route('admin.shared-slots.blocks.reorder', $sharedSlot), [
            'blocks' => [$sibling->id, $parent->id],
        ])->assertOk();

        $latestRevision = SharedSlotRevision::query()->where('shared_slot_id', $sharedSlot->id)->latest('id')->firstOrFail();
        $blocks = data_get($latestRevision->snapshot, 'blocks', []);

        $this->assertDatabaseHas('shared_slot_revisions', ['shared_slot_id' => $sharedSlot->id, 'source_event' => 'block_created']);
        $this->assertDatabaseHas('shared_slot_revisions', ['shared_slot_id' => $sharedSlot->id, 'source_event' => 'block_updated']);
        $this->assertDatabaseHas('shared_slot_revisions', ['shared_slot_id' => $sharedSlot->id, 'source_event' => 'blocks_reordered']);
        $this->assertSame('Reusable Header', data_get($latestRevision->snapshot, 'shared_slot.name'));
        $this->assertCount(3, $blocks);
        $snapshotChild = collect($blocks)->firstWhere('snapshot_id', $child->id);
        $this->assertSame($parent->id, $snapshotChild['parent_snapshot_id']);
        $this->assertSame('Guncellenen icerik', collect($snapshotChild['text_translations'])->firstWhere('locale_id', $turkish->id)['content']);
    }

    #[Test]
    public function deleting_a_shared_slot_block_creates_a_revision(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $sharedSlot = $this->sharedSlotFor($site, ['slot_name' => 'main']);
        $mainSlotType = $this->slotType('main', 'Main', 1);
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
        $user = User::factory()->superAdmin()->create();
        $sourcePage = $this->sourcePageFor($sharedSlot);

        $block = Block::query()->create([
            'page_id' => $sourcePage->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        app(SharedSlotSourcePageManager::class)->rebuildAssignments($sharedSlot);

        $this->actingAs($user)
            ->delete(route('admin.blocks.destroy', $block), ['shared_slot_id' => $sharedSlot->id])
            ->assertRedirect(route('admin.shared-slots.blocks.edit', $sharedSlot));

        $this->assertDatabaseHas('shared_slot_revisions', [
            'shared_slot_id' => $sharedSlot->id,
            'source_event' => 'block_deleted',
        ]);
    }

    #[Test]
    public function editors_can_view_shared_slot_revision_history_but_cannot_restore(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $sharedSlot = $this->sharedSlotFor($site);
        $siteAdmin = $this->siteAdminFor($site);
        $editor = $this->editorFor($site);

        SharedSlotRevision::query()->create([
            'shared_slot_id' => $sharedSlot->id,
            'site_id' => $site->id,
            'user_id' => $siteAdmin->id,
            'source_event' => 'metadata_updated',
            'label' => 'Seed revision',
            'summary' => 'Seeded for access test.',
            'snapshot' => ['schema_version' => 1, 'shared_slot' => ['name' => $sharedSlot->name]],
        ]);

        $history = $this->actingAs($editor)->get(route('admin.shared-slots.revisions.index', $sharedSlot));

        $history->assertOk();
        $history->assertSee('Revision History');
        $history->assertSee('View only');

        $revision = $sharedSlot->revisions()->firstOrFail();

        $this->actingAs($editor)
            ->post(route('admin.shared-slots.revisions.restore', [$sharedSlot, $revision]))
            ->assertForbidden();
    }

    #[Test]
    public function shared_slot_revision_history_is_denied_outside_site_scope(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $otherSite = $this->secondarySite();
        $sharedSlot = $this->sharedSlotFor($otherSite);
        $user = $this->siteAdminFor($site);

        $this->actingAs($user)
            ->get(route('admin.shared-slots.revisions.index', $sharedSlot))
            ->assertForbidden();
    }

    #[Test]
    public function shared_slot_revision_history_redirects_cleanly_when_revision_table_is_missing(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $sharedSlot = $this->sharedSlotFor($site);
        $user = User::factory()->superAdmin()->create();

        Schema::dropIfExists('shared_slot_revisions');

        $response = $this->actingAs($user)->get(route('admin.shared-slots.revisions.index', $sharedSlot));

        $response->assertRedirect(route('admin.shared-slots.edit', $sharedSlot));
        $response->assertSessionHasErrors('revisions');
    }

    #[Test]
    public function shared_slot_revision_routes_redirect_cleanly_when_shared_slot_schema_is_missing(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();

        Schema::dropIfExists('shared_slot_revisions');
        Schema::dropIfExists('shared_slot_blocks');
        Schema::dropIfExists('shared_slots');

        $response = $this->actingAs($user)->get(route('admin.shared-slots.revisions.index', ['shared_slot' => 999]));

        $response->assertRedirect(route('admin.shared-slots.index'));
        $response->assertSessionHasErrors('shared_slots');
    }

    #[Test]
    public function restoring_a_shared_slot_revision_restores_metadata_and_block_tree_and_keeps_references_intact(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
        $sharedSlot = $this->sharedSlotFor($site, ['slot_name' => 'header', 'public_shell' => 'docs']);
        $headerSlotType = $this->slotType('header', 'Header', 1);
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
        $user = User::factory()->superAdmin()->create();
        $sourcePage = $this->sourcePageFor($sharedSlot);
        $page = $this->pageFor($site, 'home', Page::STATUS_PUBLISHED, 'docs');
        $page->translations()->updateOrCreate(
            ['locale_id' => $turkish->id],
            ['site_id' => $site->id, 'name' => 'Ana Sayfa', 'slug' => 'ana-sayfa', 'path' => '/tr'],
        );
        $pageSlot = PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $headerSlotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
            'shared_slot_id' => $sharedSlot->id,
            'sort_order' => 0,
        ]);

        $parent = Block::query()->create([
            'page_id' => $sourcePage->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $headerSlotType->id,
            'sort_order' => 0,
            'title' => 'Original section',
            'status' => 'published',
            'is_system' => false,
        ]);
        $child = Block::query()->create([
            'page_id' => $sourcePage->id,
            'parent_id' => $parent->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $headerSlotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        $child->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'content' => 'Hello world',
        ]);
        $child->textTranslations()->create([
            'locale_id' => $turkish->id,
            'content' => 'Merhaba dunya',
        ]);
        app(SharedSlotSourcePageManager::class)->rebuildAssignments($sharedSlot);

        $this->actingAs($user)->put(route('admin.shared-slots.update', $sharedSlot), [
            'site_id' => $site->id,
            'name' => 'Original Header',
            'handle' => 'original-header',
            'slot_name' => 'header',
            'public_shell' => 'docs',
            'is_active' => '1',
        ]);

        $revisionToRestore = SharedSlotRevision::query()->where('shared_slot_id', $sharedSlot->id)->latest('id')->firstOrFail();
        $originalSharedSlotId = $sharedSlot->id;
        $originalSourcePageId = $sourcePage->id;

        $sharedSlot->update([
            'name' => 'Changed Header',
            'handle' => 'changed-header',
            'slot_name' => 'footer',
            'public_shell' => 'default',
            'is_active' => false,
        ]);
        $sourcePage = $this->sourcePageFor($sharedSlot);
        $sourcePage->blocks()->delete();
        Block::query()->create([
            'page_id' => $sourcePage->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'footer',
            'slot_type_id' => $headerSlotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
            'content' => 'Replacement content',
        ]);
        app(SharedSlotSourcePageManager::class)->rebuildAssignments($sharedSlot->fresh());

        $restore = $this->actingAs($user)->post(route('admin.shared-slots.revisions.restore', [$sharedSlot, $revisionToRestore]));

        $restore->assertRedirect(route('admin.shared-slots.edit', $sharedSlot));
        $restore->assertSessionHas('status', 'Shared Slot revision restored successfully.');

        $sharedSlot = $sharedSlot->fresh();
        $sourcePage = $this->sourcePageFor($sharedSlot);
        $resolvedBlocks = $sourcePage->blocks()->with('textTranslations')->orderBy('sort_order')->get();
        $resolvedTurkishBlocks = $resolvedBlocks->map(fn (Block $block) => app(BlockTranslationResolver::class)->resolve($block, $turkish));

        $this->assertSame($originalSharedSlotId, $sharedSlot->id);
        $this->assertSame('Original Header', $sharedSlot->name);
        $this->assertSame('original-header', $sharedSlot->handle);
        $this->assertSame('header', $sharedSlot->slot_name);
        $this->assertSame('docs', $sharedSlot->public_shell);
        $this->assertTrue($sharedSlot->is_active);
        $this->assertSame($originalSourcePageId, $sourcePage->id);
        $this->assertTrue($sourcePage->isSharedSlotSourcePage());
        $this->assertSame($sharedSlot->id, (int) data_get($sourcePage->settings, 'shared_slot_id'));
        $this->assertCount(2, $resolvedBlocks);
        $this->assertSame('Original section', $resolvedTurkishBlocks->firstWhere('type', 'section')?->title);
        $this->assertSame('Merhaba dunya', $resolvedTurkishBlocks->firstWhere('type', 'plain_text')?->content);
        $this->assertSame('Hello world', app(BlockTranslationResolver::class)->resolve($restoredChild = $sourcePage->blocks()->with('textTranslations')->where('type', 'plain_text')->firstOrFail(), $this->defaultLocale())->content);
        $this->assertSame('Merhaba dunya', $restoredChild->textTranslations->firstWhere('locale_id', $turkish->id)?->content);
        $this->assertDatabaseHas('page_slots', ['id' => $pageSlot->id, 'shared_slot_id' => $sharedSlot->id]);
        $this->assertDatabaseHas('shared_slot_revisions', ['shared_slot_id' => $sharedSlot->id, 'label' => 'Pre-restore safety snapshot']);
        $this->assertDatabaseHas('shared_slot_revisions', [
            'shared_slot_id' => $sharedSlot->id,
            'label' => 'Revision restored',
            'restored_from_shared_slot_revision_id' => $revisionToRestore->id,
        ]);

        $public = $this->get('/');
        $public->assertOk();
        $public->assertSee('Hello world');

        $this->get(route('pages.show', $sourcePage->slug))
            ->assertNotFound();
    }

    #[Test]
    public function shared_slot_admin_edit_screen_links_to_revision_history(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $sharedSlot = $this->sharedSlotFor($site);
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.shared-slots.edit', $sharedSlot));

        $response->assertOk();
        $response->assertSee('Revision History');
        $response->assertSee(route('admin.shared-slots.revisions.index', $sharedSlot), false);
    }
}
