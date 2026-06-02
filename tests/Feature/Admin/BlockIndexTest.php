<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;

class BlockIndexTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    $this->seed(FoundationSiteLocaleSeeder::class);
    $this->seed(BlockTypeSeeder::class);
  }

  #[Test]
  public function super_admin_can_access_the_blocks_index(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
      ->get(route('admin.blocks.index'))
      ->assertOk();
  }

  #[Test]
  public function site_admin_cannot_access_the_blocks_index(): void
  {
    $user = User::factory()->siteAdmin()->create();

    $this->actingAs($user)
      ->get(route('admin.blocks.index'))
      ->assertForbidden();
  }

  #[Test]
  public function editor_cannot_access_the_blocks_index(): void
  {
    $user = User::factory()->editor()->create();

    $this->actingAs($user)
      ->get(route('admin.blocks.index'))
      ->assertForbidden();
  }

  #[Test]
  public function site_admin_can_edit_slot_blocks_even_though_blocks_index_is_forbidden(): void
  {
    $user = User::factory()->siteAdmin()->create();
    $mainSlot = $this->slotType('main', 'Main', 1);
    $codeType = $this->blockType('code');
    $site = $this->defaultSite();
    $user->sites()->sync([$site->id]);

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Getting Started',
      'slug' => 'getting-started',
      'status' => 'draft',
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $this->defaultLocale()->id],
      ['site_id' => $site->id, 'name' => 'Getting Started', 'slug' => 'getting-started', 'path' => '/p/getting-started'],
    );

    $pageSlot = $page->slots()->create([
      'slot_type_id' => $mainSlot->id,
      'sort_order' => 0,
    ]);

    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'code',
      'block_type_id' => $codeType->id,
      'source_type' => $codeType->source_type ?? 'static',
      'slot' => $mainSlot->slug,
      'slot_type_id' => $mainSlot->id,
      'sort_order' => 0,
      'title' => 'Install command',
      'content' => 'composer install',
      'status' => 'published',
      'is_system' => false,
    ]);

    $this->actingAs($user)
      ->get(route('admin.blocks.index'))
      ->assertForbidden();

    $this->actingAs($user)
      ->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'edit' => $block->id]))
      ->assertOk()
      ->assertSee('Edit Block: Code (Getting Started / Main)')
      ->assertSee('id="slot-block-editor-modal"', false)
      ->assertSee('data-wb-admin-autoload-overlay', false);
  }

  #[Test]
  public function blocks_index_uses_the_shared_listing_filters_toolbar(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.blocks.index'));

    $response->assertOk();
    $response->assertSee('data-admin-listing-filters', false);
    $response->assertSee('data-admin-listing-filters-search', false);
    $response->assertSee('data-admin-listing-filters-fields', false);
    $response->assertSee('data-admin-listing-filters-actions', false);
    $response->assertSee('Search', false);
    $response->assertSee('Site', false);
    $response->assertSee('Page', false);
    $response->assertSee('Block Type', false);
    $response->assertSee('Status', false);
    $response->assertSee('Locale', false);
  }

  #[Test]
  public function search_filter_limits_blocks_by_block_page_and_translation_content(): void
  {
    $user = User::factory()->superAdmin()->create();
    $mainSlot = $this->slotType('main', 'Main', 1);
    $headerType = $this->blockType('header');
    $richTextType = $this->blockType('rich-text');
    [$matchingPage] = $this->pageWithBlock('Search Match Page', 'search-match-page', $mainSlot, $headerType, [
      'title' => 'Matching block title',
    ]);
    [$translatedPage, $translatedBlock] = $this->pageWithBlock('Translated Search Page', 'translated-search-page', $mainSlot, $richTextType, [
      'title' => 'Fallback title',
      'content' => 'Fallback content',
    ]);
    $translatedBlock->textTranslations()->create([
      'locale_id' => $this->defaultLocale()->id,
      'title' => 'Translated match title',
      'content' => 'Translated match body',
    ]);
    [$otherPage] = $this->pageWithBlock('Other Page', 'other-page', $mainSlot, $headerType, [
      'title' => 'Different title',
    ]);

    $byBlock = $this->actingAs($user)->get(route('admin.blocks.index', ['search' => 'Matching block']));
    $byBlock->assertOk();
    $byBlock->assertSee('href="'.route('admin.blocks.edit', $matchingPage->blocks()->firstOrFail()).'"', false);
    $byBlock->assertDontSee('href="'.route('admin.blocks.edit', $otherPage->blocks()->firstOrFail()).'"', false);

    $byPage = $this->actingAs($user)->get(route('admin.blocks.index', ['search' => 'Translated Search Page']));
    $byPage->assertOk();
    $byPage->assertSee('href="'.route('admin.blocks.edit', $translatedBlock).'"', false);
    $byPage->assertDontSee('href="'.route('admin.blocks.edit', $otherPage->blocks()->firstOrFail()).'"', false);

    $byTranslation = $this->actingAs($user)->get(route('admin.blocks.index', ['search' => 'Translated match body']));
    $byTranslation->assertOk();
    $byTranslation->assertSee('href="'.route('admin.blocks.edit', $translatedBlock).'"', false);
    $byTranslation->assertDontSee('href="'.route('admin.blocks.edit', $otherPage->blocks()->firstOrFail()).'"', false);
  }

  #[Test]
  public function site_filter_limits_blocks_to_the_selected_site(): void
  {
    $user = User::factory()->superAdmin()->create();
    $mainSlot = $this->slotType('main', 'Main', 1);
    $headerType = $this->blockType('header');
    [$firstPage, $firstBlock] = $this->pageWithBlock('Alpha Page', 'alpha-page', $mainSlot, $headerType, ['title' => 'Alpha block']);
    $secondSite = Site::query()->create(['name' => 'Second Site', 'handle' => 'second-site', 'domain' => 'second.test']);
    $secondSite->locales()->syncWithoutDetaching([$this->defaultLocale()->id => ['is_enabled' => true]]);
    [, $secondBlock] = $this->pageWithBlock('Beta Page', 'beta-page', $mainSlot, $headerType, ['title' => 'Beta block'], $secondSite);

    $response = $this->actingAs($user)->get(route('admin.blocks.index', ['site' => $firstPage->site_id]));

    $response->assertOk();
    $response->assertSee('href="'.route('admin.blocks.edit', $firstBlock).'"', false);
    $response->assertDontSee('href="'.route('admin.blocks.edit', $secondBlock).'"', false);
  }

  #[Test]
  public function page_filter_limits_blocks_to_the_selected_page(): void
  {
    $user = User::factory()->superAdmin()->create();
    $mainSlot = $this->slotType('main', 'Main', 1);
    $headerType = $this->blockType('header');
    [$firstPage, $firstBlock] = $this->pageWithBlock('Page One', 'page-one', $mainSlot, $headerType, ['title' => 'Page one block']);
    [, $secondBlock] = $this->pageWithBlock('Page Two', 'page-two', $mainSlot, $headerType, ['title' => 'Page two block']);

    $response = $this->actingAs($user)->get(route('admin.blocks.index', ['page_id' => $firstPage->id]));

    $response->assertOk();
    $response->assertSee('href="'.route('admin.blocks.edit', $firstBlock).'"', false);
    $response->assertDontSee('href="'.route('admin.blocks.edit', $secondBlock).'"', false);
  }

  #[Test]
  public function block_type_filter_limits_blocks_to_the_selected_type(): void
  {
    $user = User::factory()->superAdmin()->create();
    $mainSlot = $this->slotType('main', 'Main', 1);
    $headerType = $this->blockType('header');
    $richTextType = $this->blockType('rich-text');
    [, $headerBlock] = $this->pageWithBlock('Header Page', 'header-page', $mainSlot, $headerType, ['title' => 'Header block']);
    [, $richTextBlock] = $this->pageWithBlock('Rich Page', 'rich-page', $mainSlot, $richTextType, ['title' => 'Rich block']);

    $response = $this->actingAs($user)->get(route('admin.blocks.index', ['block_type_id' => $headerType->id]));

    $response->assertOk();
    $response->assertSee('href="'.route('admin.blocks.edit', $headerBlock).'"', false);
    $response->assertDontSee('href="'.route('admin.blocks.edit', $richTextBlock).'"', false);
  }

  #[Test]
  public function status_filter_limits_blocks_to_the_selected_status(): void
  {
    $user = User::factory()->superAdmin()->create();
    $mainSlot = $this->slotType('main', 'Main', 1);
    $headerType = $this->blockType('header');
    [, $draftBlock] = $this->pageWithBlock('Draft Page', 'draft-page', $mainSlot, $headerType, [
      'title' => 'Draft block',
      'status' => 'draft',
    ]);
    [, $publishedBlock] = $this->pageWithBlock('Published Page', 'published-page', $mainSlot, $headerType, [
      'title' => 'Published block',
      'status' => 'published',
    ]);

    $response = $this->actingAs($user)->get(route('admin.blocks.index', ['status' => 'draft']));

    $response->assertOk();
    $response->assertSee('href="'.route('admin.blocks.edit', $draftBlock).'"', false);
    $response->assertDontSee('href="'.route('admin.blocks.edit', $publishedBlock).'"', false);
  }

  #[Test]
  public function locale_filter_limits_blocks_to_those_with_matching_translation_rows(): void
  {
    $user = User::factory()->superAdmin()->create();
    $mainSlot = $this->slotType('main', 'Main', 1);
    $richTextType = $this->blockType('rich-text');
    $spanish = Locale::query()->create(['code' => 'es', 'name' => 'Spanish', 'is_default' => false, 'is_enabled' => true]);
    Site::query()->each(fn (Site $site) => $site->locales()->syncWithoutDetaching([$spanish->id => ['is_enabled' => true]]));
    [, $matchingBlock] = $this->pageWithBlock('Locale Match Page', 'locale-match-page', $mainSlot, $richTextType, ['title' => 'Locale match']);
    [, $otherBlock] = $this->pageWithBlock('Locale Miss Page', 'locale-miss-page', $mainSlot, $richTextType, ['title' => 'Locale miss']);
    $matchingBlock->textTranslations()->create([
      'locale_id' => $spanish->id,
      'title' => 'Bloque en espanol',
      'content' => 'Contenido localizado',
    ]);

    $response = $this->actingAs($user)->get(route('admin.blocks.index', ['locale' => 'es']));

    $response->assertOk();
    $response->assertSee('href="'.route('admin.blocks.edit', $matchingBlock).'"', false);
    $response->assertDontSee('href="'.route('admin.blocks.edit', $otherBlock).'"', false);
  }

  #[Test]
  public function pagination_preserves_active_filters(): void
  {
    $user = User::factory()->superAdmin()->create();
    $mainSlot = $this->slotType('main', 'Main', 1);
    $headerType = $this->blockType('header');

    for ($index = 1; $index <= 16; $index++) {
      $this->pageWithBlock('Filtered Page '.$index, 'filtered-page-'.$index, $mainSlot, $headerType, [
        'title' => 'Paginated filter block '.$index,
      ]);
    }

    $response = $this->actingAs($user)->get(route('admin.blocks.index', ['search' => 'Paginated filter block']));
    $expectedPageTwoUrl = str_replace('&', '&amp;', route('admin.blocks.index', ['search' => 'Paginated filter block', 'page' => 2]));

    $response->assertOk();
    $response->assertSee('href="'.$expectedPageTwoUrl.'"', false);
  }

  #[Test]
  public function reset_link_points_back_to_the_unfiltered_blocks_index(): void
  {
    $user = User::factory()->superAdmin()->create();
    $mainSlot = $this->slotType('main', 'Main', 1);
    $headerType = $this->blockType('header');
    $this->pageWithBlock('Reset Page', 'reset-page', $mainSlot, $headerType, ['title' => 'Reset block']);

    $response = $this->actingAs($user)->get(route('admin.blocks.index', ['search' => 'Reset']));

    $response->assertOk();
    $response->assertSee('href="'.route('admin.blocks.index').'" class="wb-btn wb-btn-secondary">Reset</a>', false);
  }

  private function slotType(string $slug, string $name, int $sortOrder): SlotType
  {
    return SlotType::query()->updateOrCreate(
      ['slug' => $slug],
      ['name' => $name, 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => true],
    );
  }

  private function blockType(string $slug): BlockType
  {
    return BlockType::query()->where('slug', $slug)->firstOrFail();
  }

  private function defaultSite(): Site
  {
    return Site::query()->where('is_primary', true)->firstOrFail();
  }

  private function defaultLocale(): Locale
  {
    return Locale::query()->where('is_default', true)->firstOrFail();
  }

  private function pageWithBlock(string $title, string $slug, SlotType $slotType, BlockType $blockType, array $blockOverrides = [], ?Site $site = null): array
  {
    $site ??= $this->defaultSite();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => $title,
      'slug' => $slug,
      'status' => 'published',
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $this->defaultLocale()->id],
      ['site_id' => $site->id, 'name' => $title, 'slug' => $slug, 'path' => '/p/'.$slug],
    );

    $block = Block::query()->create(array_merge([
      'page_id' => $page->id,
      'parent_id' => null,
      'type' => $blockType->slug,
      'block_type_id' => $blockType->id,
      'source_type' => $blockType->source_type ?? 'static',
      'slot' => $slotType->slug,
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'title' => $title.' Block',
      'subtitle' => null,
      'content' => null,
      'url' => null,
      'asset_id' => null,
      'variant' => null,
      'meta' => null,
      'settings' => null,
      'status' => 'published',
      'is_system' => false,
    ], $blockOverrides));

    return [$page, $block];
  }
}
