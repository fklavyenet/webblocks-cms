<?php

namespace Tests\Feature\Admin;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PageSlot;
use App\Models\Site;
use App\Models\SlotType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function defaultSite(): Site
    {
        return Site::query()->where('is_primary', true)->firstOrFail();
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

    private function slotType(string $slug = 'main', string $name = 'Main', int $sortOrder = 1): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => true],
        );
    }

    private function sectionBlockType(): BlockType
    {
        return BlockType::query()->firstOrCreate(
            ['slug' => 'section'],
            ['name' => 'Section', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1],
        );
    }

    private function columnsBlockType(): BlockType
    {
        return BlockType::query()->firstOrCreate(
            ['slug' => 'columns'],
            ['name' => 'Columns', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 2],
        );
    }

    private function columnItemBlockType(): BlockType
    {
        return BlockType::query()->firstOrCreate(
            ['slug' => 'column_item'],
            ['name' => 'Column Item', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 3],
        );
    }

    private function pageFor(Site $site, string $status = Page::STATUS_DRAFT, string $slug = 'about'): Page
    {
        return Page::create([
            'site_id' => $site->id,
            'title' => ucfirst($slug),
            'slug' => $slug,
            'status' => $status,
        ]);
    }

    #[Test]
    public function page_updates_create_a_revision_and_show_revision_history_link(): void
    {
        $site = $this->defaultSite();
        $user = $this->siteAdminFor($site);
        $main = $this->slotType();
        $page = $this->pageFor($site);
        $slot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->put(route('admin.pages.update', $page), [
            'site_id' => $site->id,
            'title' => 'About Updated',
            'slug' => 'about-updated',
            'slots' => [
                ['id' => $slot->id, 'slot_type_id' => $main->id],
            ],
        ]);

        $response->assertRedirect(route('admin.pages.edit', $page));
        $this->assertDatabaseHas('page_revisions', [
            'page_id' => $page->id,
            'created_by' => $user->id,
            'label' => 'Page updated',
        ]);

        $edit = $this->actingAs($user)->get(route('admin.pages.edit', $page));
        $edit->assertOk();
        $edit->assertSee('Revision History');
        $edit->assertSee(route('admin.pages.revisions.index', $page), false);
    }

    #[Test]
    public function page_translation_updates_create_a_revision(): void
    {
        $site = $this->defaultSite();
        $user = $this->siteAdminFor($site);
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
        $page = $this->pageFor($site, Page::STATUS_DRAFT);

        $response = $this->actingAs($user)->post(route('admin.pages.translations.store', [$page, $turkish]), [
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
        ]);

        $response->assertRedirect(route('admin.pages.edit', $page));
        $this->assertDatabaseHas('page_revisions', [
            'page_id' => $page->id,
            'created_by' => $user->id,
            'label' => 'Translation added',
        ]);
    }

    #[Test]
    public function block_updates_create_a_revision(): void
    {
        $site = $this->defaultSite();
        $user = $this->siteAdminFor($site);
        $main = $this->slotType();
        $sectionType = $this->sectionBlockType();
        $page = $this->pageFor($site);
        $slot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);
        $block = Block::create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Hero',
            'content' => 'Original content',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->put(route('admin.blocks.update', $block), [
            'page_id' => $page->id,
            'parent_id' => null,
            'block_type_id' => $sectionType->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Hero updated',
            'content' => 'Updated content',
            'status' => 'published',
            '_slot_block_mode' => 'edit',
            '_slot_block_id' => $block->id,
        ]);

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $slot]));
        $this->assertDatabaseHas('page_revisions', [
            'page_id' => $page->id,
            'created_by' => $user->id,
            'label' => 'Block updated',
        ]);
    }

    #[Test]
    public function editors_can_view_revision_history_but_cannot_restore(): void
    {
        $site = $this->defaultSite();
        $editor = $this->editorFor($site);
        $siteAdmin = $this->siteAdminFor($site);
        $page = $this->pageFor($site);

        PageRevision::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'created_by' => $siteAdmin->id,
            'label' => 'Manual seed revision',
            'reason' => 'Seeded for access test.',
            'snapshot' => ['schema_version' => 1],
        ]);

        $history = $this->actingAs($editor)->get(route('admin.pages.revisions.index', $page));
        $history->assertOk();
        $history->assertSee('Revision History');
        $history->assertSee('View only');
        $history->assertDontSee('Restore this page revision?');

        $revision = $page->revisions()->firstOrFail();

        $this->actingAs($editor)
            ->post(route('admin.pages.revisions.restore', [$page, $revision]))
            ->assertForbidden();
    }

    #[Test]
    public function revision_history_is_denied_outside_site_scope(): void
    {
        $primarySite = $this->defaultSite();
        $secondarySite = Site::query()->create([
            'name' => 'Campaign Site',
            'handle' => 'campaign-site',
            'domain' => 'campaign.example.test',
            'is_primary' => false,
        ]);
        $user = $this->siteAdminFor($primarySite);
        $page = $this->pageFor($secondarySite);

        $this->actingAs($user)
            ->get(route('admin.pages.revisions.index', $page))
            ->assertForbidden();
    }

    #[Test]
    public function restoring_a_revision_restores_page_translations_slots_and_blocks_and_creates_safety_entries(): void
    {
        $site = $this->defaultSite();
        $user = $this->siteAdminFor($site);
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
        $main = $this->slotType('main', 'Main', 1);
        $sidebar = $this->slotType('sidebar', 'Sidebar', 2);
        $sectionType = $this->sectionBlockType();
        $columnsType = $this->columnsBlockType();
        $columnItemType = $this->columnItemBlockType();

        $page = $this->pageFor($site, Page::STATUS_PUBLISHED, 'about');
        $page->translations()->create([
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);
        PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);
        $columns = Block::create([
            'page_id' => $page->id,
            'type' => 'columns',
            'block_type_id' => $columnsType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Columns block',
            'content' => 'Original columns',
            'status' => 'published',
            'is_system' => false,
        ]);
        $child = Block::create([
            'page_id' => $page->id,
            'parent_id' => $columns->id,
            'type' => 'column_item',
            'block_type_id' => $columnItemType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Column child',
            'content' => 'Child content',
            'status' => 'published',
            'is_system' => false,
        ]);
        $child->textTranslations()->create([
            'locale_id' => $turkish->id,
            'title' => 'Sutun cocuk',
            'content' => 'Cocuk icerik',
        ]);

        $captured = $this->actingAs($user)->post(route('admin.pages.workflow', $page), [
            'action' => 'archive',
        ]);

        $captured->assertRedirect(route('admin.pages.edit', $page));
        $revisionToRestore = $page->revisions()->firstOrFail();

        $page->update([
            'title' => 'Changed page title',
            'slug' => 'changed-page-title',
            'status' => Page::STATUS_DRAFT,
        ]);
        $page->translations()->where('locale_id', $turkish->id)->update([
            'name' => 'Degisti',
            'slug' => 'degisti',
            'path' => '/p/degisti',
        ]);
        $page->slots()->delete();
        PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $sidebar->id,
            'sort_order' => 0,
        ]);
        $page->blocks()->delete();
        $replacement = Block::create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'sidebar',
            'slot_type_id' => $sidebar->id,
            'sort_order' => 0,
            'title' => 'Replacement block',
            'content' => 'Replacement content',
            'status' => 'draft',
            'is_system' => false,
        ]);
        $replacement->textTranslations()->create([
            'locale_id' => $turkish->id,
            'title' => 'Yedek',
            'content' => 'Yedek icerik',
        ]);

        $restore = $this->actingAs($user)->post(route('admin.pages.revisions.restore', [$page, $revisionToRestore]));

        $restore->assertRedirect(route('admin.pages.edit', $page));
        $restore->assertSessionHas('status', 'Page revision restored successfully.');

        $page = $page->fresh()->load(['translations', 'slots', 'blocks.textTranslations']);

        $this->assertSame('About', $page->getRawOriginal('title'));
        $this->assertSame('about', $page->getRawOriginal('slug'));
        $this->assertSame(Page::STATUS_ARCHIVED, $page->status);
        $this->assertSame('Hakkinda', $page->translations->firstWhere('locale_id', $turkish->id)?->name);
        $this->assertCount(1, $page->slots);
        $this->assertSame($main->id, $page->slots->first()->slot_type_id);
        $this->assertSame(2, $page->blocks->count());
        $this->assertSame(['Columns block', 'Column child'], $page->blocks->sortBy('sort_order')->pluck('title')->values()->all());
        $restoredChild = $page->blocks->firstWhere('title', 'Column child');
        $this->assertNotNull($restoredChild);
        $this->assertSame('Sutun cocuk', $restoredChild->textTranslations->firstWhere('locale_id', $turkish->id)?->title);
        $this->assertDatabaseHas('page_revisions', [
            'page_id' => $page->id,
            'label' => 'Pre-restore safety snapshot',
        ]);
        $this->assertDatabaseHas('page_revisions', [
            'page_id' => $page->id,
            'label' => 'Revision restored',
            'restored_from_page_revision_id' => $revisionToRestore->id,
        ]);

        $history = $this->actingAs($user)->get(route('admin.pages.revisions.index', $page));
        $history->assertOk();
        $history->assertSee('Restored from revision #'.$revisionToRestore->id);
    }
}
