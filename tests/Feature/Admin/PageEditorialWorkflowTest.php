<?php

namespace Tests\Feature\Admin;

use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\Site;
use App\Models\SlotType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageEditorialWorkflowTest extends TestCase
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

    private function slotType(string $slug = 'main', string $name = 'Main', int $sortOrder = 2): SlotType
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
    public function new_page_defaults_to_draft_when_created_from_admin(): void
    {
        $site = $this->defaultSite();
        $user = $this->editorFor($site);
        $main = $this->slotType();

        $response = $this->actingAs($user)->post(route('admin.pages.store'), [
            'site_id' => $site->id,
            'title' => 'Workflow Start',
            'slug' => 'workflow-start',
        ]);

        $page = Page::query()
            ->whereHas('translations', fn ($query) => $query
                ->where('locale_id', $this->defaultLocale()->id)
                ->where('slug', 'workflow-start'))
            ->firstOrFail();

        $response->assertRedirect(route('admin.pages.edit', $page));
        $response->assertSessionHas('status', 'Page saved as draft.');
        $this->assertSame(Page::STATUS_DRAFT, $page->fresh()->status);
        $this->assertNull($page->fresh()->published_at);
        $this->assertNull($page->fresh()->review_requested_at);

        $this->actingAs($user)
            ->post(route('admin.pages.slots.store', $page), ['slot_type_id' => $main->id])
            ->assertRedirect(route('admin.pages.edit', $page));

        $this->assertDatabaseHas('page_slots', ['page_id' => $page->id, 'slot_type_id' => $main->id]);
    }

    #[Test]
    public function editor_can_submit_draft_for_review_but_cannot_publish_or_archive(): void
    {
        $site = $this->defaultSite();
        $user = $this->editorFor($site);
        $page = $this->pageFor($site, Page::STATUS_DRAFT);

        $submit = $this->actingAs($user)->post(route('admin.pages.workflow', $page), [
            'action' => 'submit_review',
        ]);

        $submit->assertRedirect(route('admin.pages.edit', $page));
        $submit->assertSessionHas('status', 'Page submitted for review.');
        $this->assertSame(Page::STATUS_IN_REVIEW, $page->fresh()->status);
        $this->assertNotNull($page->fresh()->review_requested_at);

        $this->actingAs($user)
            ->post(route('admin.pages.workflow', $page), ['action' => 'publish'])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.pages.workflow', $page), ['action' => 'archive'])
            ->assertForbidden();
    }

    #[Test]
    public function editor_can_move_in_review_page_back_to_draft_within_site_scope(): void
    {
        $site = $this->defaultSite();
        $user = $this->editorFor($site);
        $page = $this->pageFor($site, Page::STATUS_IN_REVIEW);

        $response = $this->actingAs($user)->post(route('admin.pages.workflow', $page), [
            'action' => 'restore_draft',
        ]);

        $response->assertRedirect(route('admin.pages.edit', $page));
        $response->assertSessionHas('status', 'Page moved back to draft.');
        $this->assertSame(Page::STATUS_DRAFT, $page->fresh()->status);
        $this->assertNull($page->fresh()->review_requested_at);
    }

    #[Test]
    public function site_admin_can_publish_archive_and_restore_pages_within_assigned_site(): void
    {
        $site = $this->defaultSite();
        $user = $this->siteAdminFor($site);
        $page = $this->pageFor($site, Page::STATUS_DRAFT);

        $publish = $this->actingAs($user)->post(route('admin.pages.workflow', $page), [
            'action' => 'publish',
        ]);

        $publish->assertRedirect(route('admin.pages.edit', $page));
        $publish->assertSessionHas('status', 'Page published.');
        $this->assertSame(Page::STATUS_PUBLISHED, $page->fresh()->status);
        $this->assertNotNull($page->fresh()->published_at);

        $archive = $this->actingAs($user)->post(route('admin.pages.workflow', $page), [
            'action' => 'archive',
        ]);

        $archive->assertRedirect(route('admin.pages.edit', $page));
        $archive->assertSessionHas('status', 'Page archived.');
        $this->assertSame(Page::STATUS_ARCHIVED, $page->fresh()->status);

        $restore = $this->actingAs($user)->post(route('admin.pages.workflow', $page), [
            'action' => 'restore_draft',
        ]);

        $restore->assertRedirect(route('admin.pages.edit', $page));
        $restore->assertSessionHas('status', 'Page moved back to draft.');
        $this->assertSame(Page::STATUS_DRAFT, $page->fresh()->status);
    }

    #[Test]
    public function super_admin_can_publish_pages(): void
    {
        $site = $this->defaultSite();
        $user = User::factory()->superAdmin()->create();
        $page = $this->pageFor($site, Page::STATUS_IN_REVIEW);

        $response = $this->actingAs($user)->post(route('admin.pages.workflow', $page), [
            'action' => 'publish',
        ]);

        $response->assertRedirect(route('admin.pages.edit', $page));
        $response->assertSessionHas('status', 'Page published.');
        $this->assertSame(Page::STATUS_PUBLISHED, $page->fresh()->status);
    }

    #[Test]
    public function workflow_actions_are_denied_for_pages_outside_assigned_site_scope(): void
    {
        $primarySite = $this->defaultSite();
        $secondarySite = Site::query()->create([
            'name' => 'Campaign Site',
            'handle' => 'campaign-site',
            'domain' => 'campaign.example.test',
            'is_primary' => false,
        ]);
        $siteAdmin = $this->siteAdminFor($primarySite);
        $editor = $this->editorFor($primarySite);
        $page = $this->pageFor($secondarySite, Page::STATUS_DRAFT, 'campaign-page');

        $this->actingAs($siteAdmin)
            ->post(route('admin.pages.workflow', $page), ['action' => 'publish'])
            ->assertForbidden();

        $this->actingAs($editor)
            ->post(route('admin.pages.workflow', $page), ['action' => 'submit_review'])
            ->assertForbidden();
    }

    #[Test]
    public function slot_actions_are_denied_for_pages_outside_assigned_site_scope(): void
    {
        $primarySite = $this->defaultSite();
        $secondarySite = Site::query()->create([
            'name' => 'Campaign Site',
            'handle' => 'campaign-site',
            'domain' => 'campaign.example.test',
            'is_primary' => false,
        ]);
        $siteAdmin = $this->siteAdminFor($primarySite);
        $page = $this->pageFor($secondarySite, Page::STATUS_DRAFT, 'campaign-page');
        $slotType = $this->slotType();
        $otherSlotType = $this->slotType('sidebar', 'Sidebar', 3);
        $slot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
        ]);

        $this->actingAs($siteAdmin)
            ->post(route('admin.pages.slots.store', $page), ['slot_type_id' => $otherSlotType->id])
            ->assertForbidden();

        $this->actingAs($siteAdmin)
            ->delete(route('admin.pages.slots.destroy', [$page, $slot]))
            ->assertForbidden();

        $this->actingAs($siteAdmin)
            ->post(route('admin.pages.slots.move-down', [$page, $slot]))
            ->assertForbidden();
    }

    #[Test]
    public function editor_cannot_edit_slots_blocks_or_translations_after_page_leaves_draft(): void
    {
        $site = $this->defaultSite();
        $editor = $this->editorFor($site);
        $page = $this->pageFor($site, Page::STATUS_IN_REVIEW);
        $slotType = $this->slotType();
        $otherSlotType = $this->slotType('sidebar', 'Sidebar', 3);
        $slot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
        ]);
        $sectionType = $this->sectionBlockType();
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        $this->actingAs($editor)
            ->put(route('admin.pages.update', $page), [
                'site_id' => $site->id,
                'title' => 'Changed',
                'slug' => 'about',
            ])
            ->assertForbidden();

        $this->actingAs($editor)
            ->post(route('admin.pages.slots.store', $page), ['slot_type_id' => $otherSlotType->id])
            ->assertForbidden();

        $this->actingAs($editor)
            ->delete(route('admin.pages.slots.destroy', [$page, $slot]))
            ->assertForbidden();

        $this->actingAs($editor)
            ->post(route('admin.pages.slots.move-up', [$page, $slot]))
            ->assertForbidden();

        $this->actingAs($editor)
            ->get(route('admin.pages.slots.blocks', [$page, $slot]))
            ->assertForbidden();

        $this->actingAs($editor)
            ->get(route('admin.pages.translations.create', [$page, $turkish]))
            ->assertForbidden();

        $block = $page->blocks()->create([
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slot->slot_type_id,
            'sort_order' => 0,
            'title' => 'Hero',
            'status' => 'published',
            'is_system' => false,
        ]);

        $this->actingAs($editor)
            ->put(route('admin.blocks.update', $block), [
                'page_id' => $page->id,
                'parent_id' => null,
                'block_type_id' => $sectionType->id,
                'slot_type_id' => $slot->slot_type_id,
                'sort_order' => 0,
                'title' => 'Blocked',
                'content' => 'Blocked',
                'status' => 'published',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function public_routes_only_render_published_pages(): void
    {
        $site = $this->defaultSite();

        foreach ([Page::STATUS_DRAFT, Page::STATUS_IN_REVIEW, Page::STATUS_ARCHIVED] as $status) {
            $page = $this->pageFor($site, $status, 'page-'.$status);

            $this->get(route('pages.show', $page->slug))->assertNotFound();
        }

        $published = $this->pageFor($site, Page::STATUS_PUBLISHED, 'page-published');

        $this->get(route('pages.show', $published->slug))->assertOk();
    }

    #[Test]
    public function published_pages_remain_public_for_enabled_locale_routes(): void
    {
        $site = $this->defaultSite();
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        $page = $this->pageFor($site, Page::STATUS_PUBLISHED, 'about');
        $page->translations()->create([
            'site_id' => $site->id,
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);

        $this->get('/tr/p/hakkinda')->assertOk();
    }

    #[Test]
    public function page_index_and_edit_screen_show_workflow_status_and_role_specific_actions(): void
    {
        $site = $this->defaultSite();
        $editor = $this->editorFor($site);
        $siteAdmin = $this->siteAdminFor($site);
        $page = $this->pageFor($site, Page::STATUS_DRAFT);

        $index = $this->actingAs($editor)->get(route('admin.pages.index'));
        $index->assertOk();
        $index->assertSee('Draft');
        $index->assertSee('In Review');
        $index->assertSee('Archived');
        $index->assertSee('data-admin-listing-filters', false);
        $index->assertSee('data-admin-listing-filters-search', false);
        $index->assertSee('data-admin-listing-filters-fields', false);
        $index->assertSee('data-admin-listing-filters-actions', false);
        $index->assertSee('id="pages_search"', false);
        $index->assertSee('id="pages_status"', false);
        $index->assertSee('id="pages_sort"', false);
        $index->assertSee('id="pages_direction"', false);
        $index->assertSee('Apply', false);

        $editorEdit = $this->actingAs($editor)->get(route('admin.pages.edit', $page));
        $editorEdit->assertOk();
        $editorEdit->assertSee('Submit for Review');
        $editorEdit->assertDontSee('Publish');
        $editorEdit->assertDontSee('Archive');

        $siteAdminEdit = $this->actingAs($siteAdmin)->get(route('admin.pages.edit', $page));
        $siteAdminEdit->assertOk();
        $siteAdminEdit->assertSee('Submit for Review');
        $siteAdminEdit->assertSee('Publish');
    }

    #[Test]
    public function page_index_filters_and_pagination_preserve_query_string_with_compact_summary(): void
    {
        $site = $this->defaultSite();
        $user = User::factory()->superAdmin()->create();

        foreach (range(1, 35) as $index) {
            Page::query()->create([
                'site_id' => $site->id,
                'title' => sprintf('Pattern Page %02d', $index),
                'slug' => sprintf('pattern-page-%02d', $index),
                'status' => $index % 2 === 0 ? Page::STATUS_PUBLISHED : Page::STATUS_DRAFT,
                'page_type' => 'default',
            ]);
        }

        $response = $this->actingAs($user)->get(route('admin.pages.index', [
            'site' => $site->id,
            'search' => 'Pattern Page',
            'status' => Page::STATUS_PUBLISHED,
            'sort' => 'title',
            'direction' => 'asc',
        ]));

        $response->assertOk();
        $response->assertSee('Pattern Page 02');
        $response->assertDontSee('Pattern Page 01');
        $response->assertSee('data-admin-pagination', false);
        $response->assertSee('class="wb-pagination wb-pagination-compact"', false);
        $response->assertSee('aria-label="Pages pagination"', false);
        $response->assertSee('aria-current="page">1</span>', false);
        $response->assertSee('data-admin-pagination-summary', false);
        $response->assertSee('1-15/17', false);
        $response->assertDontSee('Showing 1-15 of 17', false);
        $response->assertSee(e(route('admin.pages.index', [
            'site' => $site->id,
            'search' => 'Pattern Page',
            'status' => Page::STATUS_PUBLISHED,
            'sort' => 'title',
            'direction' => 'asc',
            'page' => 2,
        ])), false);
        $response->assertSee('<span class="wb-pagination-link">Previous</span>', false);

        $pageTwo = $this->actingAs($user)->get(route('admin.pages.index', [
            'site' => $site->id,
            'search' => 'Pattern Page',
            'status' => Page::STATUS_PUBLISHED,
            'sort' => 'title',
            'direction' => 'asc',
            'page' => 2,
        ]));

        $pageTwo->assertOk();
        $pageTwo->assertSee('aria-current="page">2</span>', false);
        $pageTwo->assertSee('16-17/17', false);
        $pageTwo->assertSee(e(route('admin.pages.index', [
            'site' => $site->id,
            'search' => 'Pattern Page',
            'status' => Page::STATUS_PUBLISHED,
            'sort' => 'title',
            'direction' => 'asc',
            'page' => 1,
        ])), false);
    }

    #[Test]
    public function invalid_transition_is_rejected_cleanly(): void
    {
        $site = $this->defaultSite();
        $user = User::factory()->superAdmin()->create();
        $page = $this->pageFor($site, Page::STATUS_DRAFT);

        $response = $this->actingAs($user)
            ->from(route('admin.pages.edit', $page))
            ->post(route('admin.pages.workflow', $page), [
                'action' => 'archive',
            ]);

        $response->assertRedirect(route('admin.pages.edit', $page));
        $response->assertSessionHasErrors('action');
        $this->assertSame(Page::STATUS_DRAFT, $page->fresh()->status);
    }
}
