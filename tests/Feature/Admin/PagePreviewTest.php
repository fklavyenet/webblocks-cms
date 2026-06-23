<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;

class PagePreviewTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function admin_page_preview_uses_the_canonical_member_action_url(): void
  {
    $page = $this->pageWithMainSlot(Page::STATUS_DRAFT, 'preview-url');

    $this->assertSame('/webadmin/pages/'.$page->id.'/preview', route('admin.pages.preview', $page, false));
  }

  #[Test]
  public function authorized_admin_can_preview_draft_page_with_draft_blocks_without_logging_a_visit(): void
  {
    $page = $this->pageWithMainSlot(Page::STATUS_DRAFT, 'draft-preview');
    $this->plainTextBlock($page, 'Draft-only preview content', Page::STATUS_DRAFT);
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.pages.preview', $page));

    $response->assertOk();
    $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $response->assertSee('Preview mode');
    $response->assertSee('this page is not public unless it is published');
    $response->assertSee('Draft-only preview content');
    $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    $this->assertDatabaseCount('visitor_events', 0);
    $this->assertSame(Page::STATUS_DRAFT, $page->fresh()->status);
  }

  #[Test]
  public function page_preview_renders_in_review_and_published_pages(): void
  {
    $user = User::factory()->superAdmin()->create();

    foreach ([Page::STATUS_IN_REVIEW, Page::STATUS_PUBLISHED] as $status) {
      $page = $this->pageWithMainSlot($status, 'preview-'.$status);
      $this->plainTextBlock($page, 'Preview content for '.$status, Page::STATUS_DRAFT);

      $this->actingAs($user)
        ->get(route('admin.pages.preview', $page))
        ->assertOk()
        ->assertSee('Preview content for '.$status);
    }
  }

  #[Test]
  public function site_scoped_admin_can_only_preview_assigned_site_pages(): void
  {
    $site = $this->defaultSite();
    $otherSite = Site::query()->create([
      'name' => 'Other Site',
      'handle' => 'other',
      'domain' => 'other.test',
      'is_primary' => false,
    ]);
    $otherSite->locales()->syncWithoutDetaching([
      $this->defaultLocale()->id => ['is_enabled' => true],
    ]);
    $allowed = $this->pageWithMainSlot(Page::STATUS_DRAFT, 'allowed-preview', $site);
    $outside = $this->pageWithMainSlot(Page::STATUS_DRAFT, 'outside-preview', $otherSite);
    $editor = User::factory()->editor()->create();
    $editor->sites()->sync([$site->id]);

    $this->actingAs($editor)
      ->get(route('admin.pages.preview', $allowed))
      ->assertOk();

    $this->actingAs($editor)
      ->get(route('admin.pages.preview', $outside))
      ->assertForbidden();
  }

  #[Test]
  public function public_routes_still_do_not_render_draft_pages_or_draft_blocks(): void
  {
    $draftPage = $this->pageWithMainSlot(Page::STATUS_DRAFT, 'public-draft-preview');
    $this->plainTextBlock($draftPage, 'Public route must not expose draft pages', Page::STATUS_DRAFT);
    $publishedPage = $this->pageWithMainSlot(Page::STATUS_PUBLISHED, 'published-filter-preview');
    $this->plainTextBlock($publishedPage, 'Public route must not expose draft blocks', Page::STATUS_DRAFT);

    $this->get(route('pages.show', $draftPage->slug))
      ->assertNotFound();

    $this->get(route('pages.show', $publishedPage->slug))
      ->assertOk()
      ->assertDontSee('Public route must not expose draft blocks');
  }

  #[Test]
  public function page_edit_screen_includes_preview_action_for_drafts(): void
  {
    $page = $this->pageWithMainSlot(Page::STATUS_DRAFT, 'edit-preview-link');
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
      ->get(route('admin.pages.edit', $page))
      ->assertOk()
      ->assertSee('href="'.route('admin.pages.preview', $page).'"', false)
      ->assertSee('target="_blank"', false)
      ->assertSee('Preview');
  }

  private function defaultSite(): Site
  {
    return Site::query()->where('is_primary', true)->firstOrFail();
  }

  private function defaultLocale(): Locale
  {
    return Locale::query()->where('is_default', true)->firstOrFail();
  }

  private function mainSlotType(): SlotType
  {
    return SlotType::query()->updateOrCreate(
      ['slug' => 'main'],
      ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
    );
  }

  private function plainTextBlockType(): BlockType
  {
    return BlockType::query()->updateOrCreate(
      ['slug' => 'plain_text'],
      ['name' => 'Plain Text', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1],
    );
  }

  private function pageWithMainSlot(string $status, string $slug, ?Site $site = null): Page
  {
    $site ??= $this->defaultSite();
    $slotType = $this->mainSlotType();

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => str($slug)->headline()->toString(),
      'slug' => $slug,
      'status' => $status,
    ]);

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
    ]);

    return $page;
  }

  private function plainTextBlock(Page $page, string $content, string $status): Block
  {
    $slotType = $this->mainSlotType();

    return Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $this->plainTextBlockType()->id,
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'content' => $content,
      'status' => $status,
      'is_system' => false,
    ]);
  }
}
