<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use WebBlocks\Cms\Http\Requests\Admin\BlockRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockPayloadWriter;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
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

  #[Test]
  public function saving_the_admin_form_persists_the_chosen_thumbnail(): void
  {
    // Regression: the item branch of validatedData() re-added asset_id => null
    // after media_id was already resolved. asset_id's setter writes media_id and
    // fill() applies it last, so every save wiped the thumbnail back to null and
    // the picker looked like it did nothing.
    $this->seedBlockTypes();
    [$page, $slotType] = $this->seedPage();
    $media = $this->seedImage();
    $itemType = BlockType::query()->where('slug', 'link-list-item')->firstOrFail();

    $data = $this->validatedDataFor([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'block_type_id' => $itemType->id,
      'sort_order' => 0,
      'title' => 'Guide',
      'url' => '/guide',
      'asset_id' => $media->id,
      'status' => 'published',
    ]);

    $this->assertArrayNotHasKey('asset_id', $data, 'asset_id must not survive into the fill payload.');
    $this->assertSame($media->id, $data['media_id']);

    $block = app(BlockPayloadWriter::class)->save(new Block, $page, $data, null);

    $this->assertSame($media->id, $block->fresh()->media_id, 'The saved block must keep the chosen thumbnail.');
  }

  #[Test]
  public function saving_the_admin_form_can_clear_the_thumbnail(): void
  {
    $this->seedBlockTypes();
    [$page, $slotType] = $this->seedPage();
    $media = $this->seedImage();
    $itemType = BlockType::query()->where('slug', 'link-list-item')->firstOrFail();

    $block = Block::query()->create([
      'page_id' => $page->id, 'type' => 'link-list-item', 'block_type_id' => $itemType->id,
      'source_type' => 'static', 'slot' => $slotType->slug, 'slot_type_id' => $slotType->id,
      'sort_order' => 0, 'status' => 'published', 'title' => 'Guide', 'url' => '/guide',
      'media_id' => $media->id,
    ]);

    // An empty picker posts asset_id back as an empty string.
    $data = $this->validatedDataFor([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'block_type_id' => $itemType->id,
      'sort_order' => 0,
      'title' => 'Guide',
      'url' => '/guide',
      'asset_id' => '',
      'status' => 'published',
    ]);

    app(BlockPayloadWriter::class)->save($block, $page, $data, null);

    $this->assertNull($block->fresh()->media_id, 'Clearing the picker must clear the thumbnail.');
  }

  #[Test]
  public function the_media_assignment_rules_have_one_owner(): void
  {
    // Regression: InternalContentPlanService kept a hand-written copy of this
    // list, so adding link-list-item to the operations copy alone still left the
    // plan path rejecting the thumbnail. Same drift the icon list had in 1.40.7.
    $this->assertArrayHasKey('link-list-item', InternalContentApiOperations::DIRECT_MEDIA_KIND_RULES);
    $this->assertSame([Media::KIND_IMAGE], InternalContentApiOperations::DIRECT_MEDIA_KIND_RULES['link-list-item']);
    $this->assertFalse(
      (new ReflectionClass(InternalContentPlanService::class))->hasConstant('DIRECT_MEDIA_KIND_RULES'),
      'InternalContentPlanService must delegate to the canonical media rules instead of keeping a copy.',
    );
  }

  /**
   * Runs the real admin BlockRequest so the media handling in validatedData() is
   * exercised. Media authorization is faked because scoping media to a user
   * needs the host application's App\Models\User, which a package-only test
   * suite has no access to.
   */
  private function validatedDataFor(array $payload): array
  {
    $this->app->instance(AdminAuthorization::class, new class extends AdminAuthorization
    {
      public function normalizeAllowedMediaId($user, ?int $mediaId): ?int
      {
        return $mediaId > 0 ? $mediaId : null;
      }

      public function filterAllowedMediaIds($user, array $mediaIds): array
      {
        return array_values(array_filter(array_map('intval', $mediaIds), fn (int $id): bool => $id > 0));
      }
    });

    $request = BlockRequest::create('/webadmin/blocks', 'POST', $payload);
    $request->setContainer($this->app);
    $request->setRouteResolver(fn () => null);
    $request->validateResolved();

    return $request->validatedData();
  }

  #[Test]
  public function the_list_renders_the_selected_style_modifiers(): void
  {
    $html = $this->renderList(['row_layout' => 'stacked', 'list_frame' => 'cards', 'thumb_size' => 'wide']);

    $this->assertStringContainsString('wb-link-list--stacked', $html);
    $this->assertStringContainsString('wb-link-list--cards', $html);
    $this->assertStringContainsString('wb-link-list--thumb-wide', $html);
  }

  #[Test]
  public function the_list_styles_are_independent(): void
  {
    $stackedOnly = $this->renderList(['row_layout' => 'stacked']);
    $this->assertStringContainsString('wb-link-list--stacked', $stackedOnly);
    $this->assertStringNotContainsString('wb-link-list--cards', $stackedOnly);
    $this->assertStringNotContainsString('wb-link-list--thumb-wide', $stackedOnly);

    $cardsOnly = $this->renderList(['list_frame' => 'cards']);
    $this->assertStringContainsString('wb-link-list--cards', $cardsOnly);
    $this->assertStringNotContainsString('wb-link-list--stacked', $cardsOnly);
    $this->assertStringNotContainsString('wb-link-list--thumb-wide', $cardsOnly);

    $wideThumbOnly = $this->renderList(['thumb_size' => 'wide']);
    $this->assertStringContainsString('wb-link-list--thumb-wide', $wideThumbOnly);
    $this->assertStringNotContainsString('wb-link-list--stacked', $wideThumbOnly);
    $this->assertStringNotContainsString('wb-link-list--cards', $wideThumbOnly);
  }

  #[Test]
  public function the_default_list_stays_unstyled(): void
  {
    // The modifiers are additive: an existing list must keep its current look.
    $html = $this->renderList();

    $this->assertStringContainsString('wb-link-list', $html);
    $this->assertStringNotContainsString('wb-link-list--', $html);
  }

  #[Test]
  public function an_admin_save_persists_the_list_styles(): void
  {
    $this->seedBlockTypes();
    [$page, $slotType] = $this->seedPage();
    $listType = BlockType::query()->where('slug', 'link-list')->firstOrFail();

    $data = $this->validatedDataFor([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'block_type_id' => $listType->id,
      'sort_order' => 0,
      'status' => 'published',
      'row_layout' => 'stacked',
      'list_frame' => 'cards',
      'thumb_size' => 'wide',
    ]);

    $block = app(BlockPayloadWriter::class)->save(new Block, $page, $data, null);
    $settings = json_decode((string) $block->fresh()->getRawOriginal('settings'), true);

    $this->assertSame('stacked', $settings['row_layout'] ?? null);
    $this->assertSame('cards', $settings['list_frame'] ?? null);
    $this->assertSame('wide', $settings['thumb_size'] ?? null);
  }

  #[Test]
  public function an_admin_save_can_return_the_list_to_the_default_style(): void
  {
    $this->seedBlockTypes();
    [$page, $slotType] = $this->seedPage();
    $listType = BlockType::query()->where('slug', 'link-list')->firstOrFail();

    $block = Block::query()->create([
      'page_id' => $page->id, 'type' => 'link-list', 'block_type_id' => $listType->id,
      'source_type' => 'static', 'slot' => $slotType->slug, 'slot_type_id' => $slotType->id,
      'sort_order' => 0, 'status' => 'published',
      'settings' => json_encode(['row_layout' => 'stacked', 'list_frame' => 'cards', 'thumb_size' => 'wide']),
    ]);

    $data = $this->validatedDataFor([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'block_type_id' => $listType->id,
      'sort_order' => 0,
      'status' => 'published',
      'row_layout' => 'index',
      'list_frame' => 'joined',
      'thumb_size' => 'square',
    ]);

    app(BlockPayloadWriter::class)->save($block, $page, $data, null);

    $this->assertNull($block->fresh()->getRawOriginal('settings'));
  }

  private function renderList(array $settings = []): string
  {
    $this->seedBlockTypes();
    [$page, $slotType] = $this->seedPage();

    $listType = BlockType::query()->where('slug', 'link-list')->firstOrFail();
    $itemType = BlockType::query()->where('slug', 'link-list-item')->firstOrFail();

    $list = Block::query()->create([
      'page_id' => $page->id, 'type' => 'link-list', 'block_type_id' => $listType->id,
      'source_type' => 'static', 'slot' => $slotType->slug, 'slot_type_id' => $slotType->id,
      'sort_order' => 0, 'status' => 'published',
      'settings' => $settings === [] ? null : json_encode($settings),
    ]);

    Block::query()->create([
      'page_id' => $page->id, 'type' => 'link-list-item', 'block_type_id' => $itemType->id,
      'parent_id' => $list->id, 'source_type' => 'static', 'slot' => $slotType->slug,
      'slot_type_id' => $slotType->id, 'sort_order' => 0, 'status' => 'published',
      'title' => 'Guide', 'url' => '/guide',
    ]);

    return view('webblocks-cms::pages.partials.blocks.link-list', [
      'block' => $list->fresh(['children.blockType', 'blockType']),
    ])->render();
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
   * Idempotent so a test can render more than one list in the same case.
   *
   * @return array{0: Page, 1: SlotType}
   */
  private function seedPage(): array
  {
    $site = Site::query()->firstOrCreate(['handle' => 'test'], ['name' => 'Test', 'is_primary' => true]);
    Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $slotType = SlotType::query()->firstOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published', 'sort_order' => 0]);
    $page = Page::query()->firstOrCreate(['site_id' => $site->id, 'slug' => 'home'], ['status' => Page::STATUS_DRAFT]);
    PageSlot::query()->firstOrCreate(['page_id' => $page->id, 'slot_type_id' => $slotType->id], ['sort_order' => 0]);

    return [$page, $slotType];
  }
}
