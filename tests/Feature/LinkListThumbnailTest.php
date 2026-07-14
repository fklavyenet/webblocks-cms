<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Tests\TestCase;

/**
 * WebBlocks UI 2.10.0 gives a link list row a dedicated leading column only when
 * the row carries the `wb-link-list-item--media` modifier. Without it a leading
 * thumbnail or icon consumes the main column and pushes the description down.
 */
class LinkListThumbnailTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function a_thumbnail_renders_in_the_leading_column(): void
  {
    $media = $this->seedImage();
    $html = $this->renderItem(['media_id' => $media->id]);

    $this->assertStringContainsString('wb-link-list-item--media', $html);
    $this->assertStringContainsString('wb-link-list-thumb', $html);
    $this->assertStringContainsString('media/guide.jpg', $html);
  }

  #[Test]
  public function a_thumbnail_uses_the_asset_alt_text(): void
  {
    $media = $this->seedImage(['alt_text' => 'Guide cover']);
    $html = $this->renderItem(['media_id' => $media->id]);

    $this->assertStringContainsString('alt="Guide cover"', $html);
  }

  #[Test]
  public function a_thumbnail_without_alt_text_stays_decorative(): void
  {
    // The row link already exposes the title as its accessible name, so an
    // empty alt keeps the thumbnail from repeating it to screen readers.
    $media = $this->seedImage();
    $html = $this->renderItem(['media_id' => $media->id]);

    $this->assertStringContainsString('alt=""', $html);
  }

  #[Test]
  public function an_icon_row_gets_the_leading_column_and_the_icon_class(): void
  {
    $html = $this->renderItem(['settings' => json_encode(['icon_slug' => 'rocket'])]);

    $this->assertStringContainsString('wb-link-list-item--media', $html);
    $this->assertStringContainsString('wb-link-list-icon', $html);
    $this->assertStringContainsString('wb-icon-rocket', $html);
  }

  #[Test]
  public function a_thumbnail_replaces_the_icon_when_both_are_set(): void
  {
    // Both claim the single leading column, so rendering the two together would
    // shift the copy into the description column.
    $media = $this->seedImage();
    $html = $this->renderItem([
      'media_id' => $media->id,
      'settings' => json_encode(['icon_slug' => 'rocket']),
    ]);

    $this->assertStringContainsString('wb-link-list-thumb', $html);
    $this->assertStringNotContainsString('wb-icon-rocket', $html);
  }

  #[Test]
  public function a_row_without_a_leading_visual_keeps_the_base_layout(): void
  {
    $html = $this->renderItem();

    $this->assertStringContainsString('wb-link-list-item', $html);
    $this->assertStringNotContainsString('wb-link-list-item--media', $html);
    $this->assertStringNotContainsString('wb-link-list-thumb', $html);
  }

  #[Test]
  public function a_non_image_asset_does_not_render_a_thumbnail(): void
  {
    $document = Media::query()->create([
      'disk' => 'public', 'path' => 'media/guide.pdf', 'filename' => 'guide.pdf',
      'mime_type' => 'application/pdf', 'kind' => Media::KIND_DOCUMENT, 'visibility' => 'public',
    ]);

    $html = $this->renderItem(['media_id' => $document->id]);

    $this->assertStringNotContainsString('wb-link-list-thumb', $html);
    $this->assertStringNotContainsString('wb-link-list-item--media', $html);
  }

  #[Test]
  public function the_api_accepts_an_image_thumbnail_on_a_link_list_item(): void
  {
    $this->seedBlockTypes();
    $media = $this->seedImage();

    $errors = [];
    $warnings = [];
    $normalized = app(InternalContentApiOperations::class)->normalizeBlock([
      'type' => 'link-list-item',
      'url' => '/guide',
      'media_id' => $media->id,
      'translations' => ['title' => 'Guide'],
    ], 'block', null, $errors, $warnings);

    $this->assertSame([], $errors);
    $this->assertSame($media->id, $normalized['media_id']);
  }

  #[Test]
  public function the_api_rejects_a_non_image_thumbnail_on_a_link_list_item(): void
  {
    $this->seedBlockTypes();
    $document = Media::query()->create([
      'disk' => 'public', 'path' => 'media/guide.pdf', 'filename' => 'guide.pdf',
      'mime_type' => 'application/pdf', 'kind' => Media::KIND_DOCUMENT, 'visibility' => 'public',
    ]);

    $errors = [];
    $warnings = [];
    app(InternalContentApiOperations::class)->normalizeBlock([
      'type' => 'link-list-item',
      'url' => '/guide',
      'media_id' => $document->id,
    ], 'block', null, $errors, $warnings);

    $this->assertContains('block.media_id', array_column($errors, 'path'));
  }

  private function renderItem(array $attributes = []): string
  {
    $this->seedBlockTypes();
    $this->seedIcon();
    [$page, $slotType] = $this->seedPage();

    $itemType = BlockType::query()->where('slug', 'link-list-item')->firstOrFail();
    $block = Block::query()->create($attributes + [
      'page_id' => $page->id, 'type' => 'link-list-item', 'block_type_id' => $itemType->id,
      'source_type' => 'static', 'slot' => $slotType->slug, 'slot_type_id' => $slotType->id,
      'sort_order' => 0, 'status' => 'published', 'title' => 'Guide', 'url' => '/guide',
    ]);

    return view('webblocks-cms::pages.partials.blocks.link-list-item', [
      'block' => $block->fresh(['blockType', 'media']),
    ])->render();
  }

  private function seedImage(array $attributes = []): Media
  {
    return Media::query()->create($attributes + [
      'disk' => 'public', 'path' => 'media/guide.jpg', 'filename' => 'guide.jpg',
      'mime_type' => 'image/jpeg', 'kind' => Media::KIND_IMAGE, 'visibility' => 'public',
    ]);
  }

  private function seedIcon(): void
  {
    IconCatalogItem::query()->firstOrCreate(['slug' => 'rocket'], [
      'source' => 'webblocks-ui',
      'label' => 'Rocket',
      'css_class' => 'wb-icon-rocket',
      'contexts' => ['content'],
      'is_active' => true,
    ]);
  }

  private function seedBlockTypes(): void
  {
    foreach ([
      ['name' => 'Link List', 'slug' => 'link-list', 'is_container' => true],
      ['name' => 'Link List Item', 'slug' => 'link-list-item', 'is_container' => false],
    ] as $index => $definition) {
      BlockType::query()->firstOrCreate(['slug' => $definition['slug']], $definition + [
        'category' => 'navigation',
        'source_type' => 'static',
        'is_system' => false,
        'sort_order' => $index,
        'status' => 'published',
      ]);
    }
  }

  /**
   * @return array{0: Page, 1: SlotType}
   */
  private function seedPage(): array
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $slotType = SlotType::query()->create(['name' => 'Main', 'slug' => 'main', 'status' => 'published', 'sort_order' => 0]);
    $page = Page::query()->create(['site_id' => $site->id, 'slug' => 'home', 'status' => Page::STATUS_DRAFT]);
    PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $slotType->id, 'sort_order' => 0]);

    return [$page, $slotType];
  }
}
