<?php

namespace Tests\Feature;

use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\PublicSearchIndex;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockTranslationWriter;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;

class SearchTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function published_page_is_indexed_but_non_public_workflow_pages_are_not(): void
  {
    [$site, $locale, $slotType, $plainTextType] = $this->seedSearchFoundation();
    $published = $this->pageWithText($site, $locale, $slotType, $plainTextType, 'Published', 'published-page', Page::STATUS_PUBLISHED, 'Alpha content');
    $draft = $this->pageWithText($site, $locale, $slotType, $plainTextType, 'Draft', 'draft-page', Page::STATUS_DRAFT, 'Draft content');
    $review = $this->pageWithText($site, $locale, $slotType, $plainTextType, 'Review', 'review-page', Page::STATUS_IN_REVIEW, 'Review content');
    $archived = $this->pageWithText($site, $locale, $slotType, $plainTextType, 'Archived', 'archived-page', Page::STATUS_ARCHIVED, 'Archived content');

    app(PublicSearchIndexer::class)->rebuild();

    $this->assertDatabaseHas('public_search_index', ['page_id' => $published->id, 'locale_id' => $locale->id]);
    $this->assertDatabaseMissing('public_search_index', ['page_id' => $draft->id]);
    $this->assertDatabaseMissing('public_search_index', ['page_id' => $review->id]);
    $this->assertDatabaseMissing('public_search_index', ['page_id' => $archived->id]);
  }

  #[Test]
  public function moving_page_out_of_published_removes_search_rows(): void
  {
    [$site, $locale, $slotType, $plainTextType] = $this->seedSearchFoundation();
    $page = $this->pageWithText($site, $locale, $slotType, $plainTextType, 'Published', 'published-page', Page::STATUS_PUBLISHED, 'Alpha content');

    app(PublicSearchIndexer::class)->rebuild();
    $this->assertDatabaseHas('public_search_index', ['page_id' => $page->id]);

    $page->update(['status' => Page::STATUS_DRAFT]);

    $this->assertDatabaseMissing('public_search_index', ['page_id' => $page->id]);
  }

  #[Test]
  public function locale_and_site_scoping_create_separate_rows_only_for_available_public_translations(): void
  {
    [$site, $locale, $slotType, $plainTextType] = $this->seedSearchFoundation();
    $turkish = Locale::query()->create([
      'code' => 'tr',
      'name' => 'Turkish',
      'is_default' => false,
      'is_enabled' => true,
    ]);
    $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

    $otherSite = Site::query()->create([
      'name' => 'Other Site',
      'handle' => 'other-site',
      'domain' => 'other.example.test',
      'is_primary' => false,
    ]);
    $otherSite->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);

    $page = $this->pageWithText($site, $locale, $slotType, $plainTextType, 'English Title', 'english-title', Page::STATUS_PUBLISHED, 'English body');
    PageTranslation::query()->create([
      'page_id' => $page->id,
      'site_id' => $site->id,
      'locale_id' => $turkish->id,
      'name' => 'Turkce Baslik',
      'slug' => 'turkce-baslik',
      'path' => '/p/turkce-baslik',
    ]);
    foreach ($page->blocks as $block) {
      $block->textTranslations()->updateOrCreate(['locale_id' => $turkish->id], ['content' => 'Turkce govde']);
      app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));
    }

    $otherPage = $this->pageWithText($otherSite, $locale, $slotType, $plainTextType, 'Other Title', 'other-title', Page::STATUS_PUBLISHED, 'Other body');

    app(PublicSearchIndexer::class)->rebuild();

    $this->assertDatabaseHas('public_search_index', ['page_id' => $page->id, 'locale_id' => $locale->id, 'url' => '/p/english-title']);
    $this->assertDatabaseHas('public_search_index', ['page_id' => $page->id, 'locale_id' => $turkish->id, 'url' => '/tr/p/turkce-baslik']);
    $this->assertDatabaseHas('public_search_index', ['page_id' => $otherPage->id, 'site_id' => $otherSite->id]);
  }

  #[Test]
  public function rebuild_uses_page_translation_name_and_indexes_published_docs_shell_pages(): void
  {
    [$site, $locale, $slotType, $plainTextType] = $this->seedSearchFoundation();

    $page = $this->pageWithText($site, $locale, $slotType, $plainTextType, 'Foundation Draft Label', 'foundation', Page::STATUS_PUBLISHED, 'Foundation body copy', [
      'public_shell' => 'docs',
    ]);

    $page->translations()->where('locale_id', $locale->id)->update([
      'name' => 'Foundation',
    ]);

    $result = app(PublicSearchIndexer::class)->rebuild();

    $this->assertSame(1, $result->indexed);
    $this->assertSame(0, $result->skipped);
    $this->assertDatabaseHas('public_search_index', [
      'page_id' => $page->id,
      'locale_id' => $locale->id,
      'title' => 'Foundation',
      'url' => '/p/foundation',
    ]);
  }

  #[Test]
  public function rebuild_reports_skipped_locales_when_published_translations_are_missing(): void
  {
    [$site, $locale, $slotType, $plainTextType] = $this->seedSearchFoundation();
    $turkish = Locale::query()->create([
      'code' => 'tr',
      'name' => 'Turkish',
      'is_default' => false,
      'is_enabled' => true,
    ]);
    $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

    $this->pageWithText($site, $locale, $slotType, $plainTextType, 'Foundation', 'foundation', Page::STATUS_PUBLISHED, 'Foundation body copy');

    $result = app(PublicSearchIndexer::class)->rebuild();

    $this->assertSame(1, $result->indexed);
    $this->assertSame(1, $result->skipped);
  }

  #[Test]
  public function shared_slot_content_is_indexed_only_for_compatible_consuming_pages_and_hidden_source_pages_are_excluded(): void
  {
    [$site, $locale, $slotType, $plainTextType] = $this->seedSearchFoundation();
    $page = $this->pageWithText($site, $locale, $slotType, $plainTextType, 'Consumer', 'consumer', Page::STATUS_PUBLISHED, 'Page owned');
    $sharedSlot = SharedSlot::query()->create([
      'site_id' => $site->id,
      'name' => 'Reusable Main',
      'handle' => 'reusable-main',
      'slot_name' => 'main',
      'public_shell' => 'default',
      'is_active' => true,
    ]);
    $page->slots()->first()->update([
      'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
      'shared_slot_id' => $sharedSlot->id,
    ]);

    $sourcePage = app(SharedSlotSourcePageManager::class)->ensureFor($sharedSlot);
    $sharedBlock = Block::query()->create([
      'page_id' => $sourcePage->id,
      'type' => 'plain_text',
      'block_type_id' => $plainTextType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $sharedBlock->textTranslations()->create(['locale_id' => $locale->id, 'content' => 'Shared searchable phrase']);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($sharedBlock->fresh(['textTranslations']));
    app(SharedSlotSourcePageManager::class)->rebuildAssignments($sharedSlot);

    app(PublicSearchIndexer::class)->rebuild();

    $indexed = PublicSearchIndex::query()->where('page_id', $page->id)->firstOrFail();
    $this->assertStringContainsString('Shared searchable phrase', $indexed->content);
    $this->assertDatabaseMissing('public_search_index', ['page_id' => $sourcePage->id]);
  }

  private function seedSearchFoundation(): array
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $this->seed(BlockTypeSeeder::class);

    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $locale = Locale::query()->where('is_default', true)->firstOrFail();
    $slotType = SlotType::query()->updateOrCreate(
      ['slug' => 'main'],
      ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
    );
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

    return [$site, $locale, $slotType, $plainTextType];
  }

  private function pageWithText(Site $site, Locale $locale, SlotType $slotType, BlockType $plainTextType, string $title, string $slug, string $status, string $content, array $settings = ['public_shell' => 'default']): Page
  {
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => $title,
      'slug' => $slug,
      'status' => $status,
      'settings' => $settings,
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $locale->id],
      ['site_id' => $site->id, 'name' => $title, 'slug' => $slug, 'path' => $slug === 'home' ? '/' : '/p/'.$slug],
    );

    $page->slots()->create([
      'slot_type_id' => $slotType->id,
      'source_type' => PageSlot::SOURCE_TYPE_PAGE,
      'sort_order' => 0,
    ]);

    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'plain_text',
      'block_type_id' => $plainTextType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $block->textTranslations()->create(['locale_id' => $locale->id, 'content' => $content]);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

    return $page->fresh(['blocks', 'slots']);
  }
}
