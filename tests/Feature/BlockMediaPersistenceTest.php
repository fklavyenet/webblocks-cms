<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Requests\Admin\BlockRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockPayloadWriter;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Media-capable blocks must keep their selected media through an admin save.
 *
 * Regression: validatedData() resolves media_id early, then each block-type
 * branch re-added `asset_id => null` to clear a field it does not use. asset_id
 * is fillable and its setter writes media_id, so fill() applied the trailing
 * null last and wiped the selection. Every affected block looked like its media
 * picker did nothing. Harmless for block types with no media, silent data loss
 * for the ones that have it.
 */
class BlockMediaPersistenceTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  /**
   * @return array<string, array{0: string, 1: bool}>
   */
  public static function mediaCapableBlockTypes(): array
  {
    return [
      'link-list-item thumbnail' => ['link-list-item', false],
      'cta background' => ['cta', true],
      'content_header background' => ['content_header', true],
      'hero background' => ['hero', true],
      'section background' => ['section', true],
      'card background' => ['card', true],
      'image media' => ['image', false],
      // `slide` is media-capable too, but it only validates inside a parent
      // slider, so it is covered by the slider tests rather than here.
    ];
  }

  #[Test]
  #[DataProvider('mediaCapableBlockTypes')]
  public function an_admin_save_keeps_the_selected_media(string $slug, bool $isContainer): void
  {
    [$page, $slotType] = $this->seedPage();
    $blockType = $this->seedBlockType($slug, $isContainer);
    $media = $this->seedImage();

    $data = $this->validatedDataFor([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'block_type_id' => $blockType->id,
      'sort_order' => 0,
      'status' => 'published',
      'title' => 'Example',
      'url' => $slug === 'link-list-item' ? '/guide' : null,
      'asset_id' => $media->id,
    ]);

    $block = app(BlockPayloadWriter::class)->save(new Block, $page, $data, null);

    $this->assertSame(
      $media->id,
      $block->fresh()->media_id,
      $slug.' must keep the media chosen in the admin form.',
    );
  }

  #[Test]
  #[DataProvider('mediaCapableBlockTypes')]
  public function an_admin_save_can_clear_the_selected_media(string $slug, bool $isContainer): void
  {
    [$page, $slotType] = $this->seedPage();
    $blockType = $this->seedBlockType($slug, $isContainer);
    $media = $this->seedImage();

    $block = Block::query()->create([
      'page_id' => $page->id, 'type' => $slug, 'block_type_id' => $blockType->id,
      'source_type' => 'static', 'slot' => $slotType->slug, 'slot_type_id' => $slotType->id,
      'sort_order' => 0, 'status' => 'published', 'title' => 'Example',
      'url' => $slug === 'link-list-item' ? '/guide' : null, 'media_id' => $media->id,
    ]);

    // An emptied picker posts asset_id back as an empty string.
    $data = $this->validatedDataFor([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'block_type_id' => $blockType->id,
      'sort_order' => 0,
      'status' => 'published',
      'title' => 'Example',
      'url' => $slug === 'link-list-item' ? '/guide' : null,
      'asset_id' => '',
    ]);

    app(BlockPayloadWriter::class)->save($block, $page, $data, null);

    $this->assertNull($block->fresh()->media_id, $slug.' must be able to clear its media.');
  }

  #[Test]
  public function block_types_without_media_still_clear_the_media_column(): void
  {
    // The trailing null was there to stop unrelated block types from carrying a
    // media assignment. Removing it for media-capable types must not let media
    // leak onto a type that has no media contract.
    [$page, $slotType] = $this->seedPage();
    $blockType = $this->seedBlockType('rich-text', false);
    $media = $this->seedImage();

    $data = $this->validatedDataFor([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'block_type_id' => $blockType->id,
      'sort_order' => 0,
      'status' => 'published',
      'title' => 'Example',
      'asset_id' => $media->id,
    ]);

    $block = app(BlockPayloadWriter::class)->save(new Block, $page, $data, null);

    $this->assertNull($block->fresh()->media_id, 'A block type without media must not store a media assignment.');
  }

  /**
   * Runs the real admin BlockRequest. Media authorization is faked because
   * scoping media to a user needs the host application's App\Models\User, which
   * a package-only test suite has no access to.
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

    $request = BlockRequest::create('/webadmin/blocks', 'POST', array_filter(
      $payload,
      fn ($value) => $value !== null,
    ));
    $request->setContainer($this->app);
    $request->setRouteResolver(fn () => null);
    $request->validateResolved();

    return $request->validatedData();
  }

  private function seedBlockType(string $slug, bool $isContainer): BlockType
  {
    return BlockType::query()->create([
      'name' => ucfirst($slug),
      'slug' => $slug,
      'category' => 'content',
      'source_type' => 'static',
      'is_system' => false,
      'is_container' => $isContainer,
      'sort_order' => 0,
      'status' => 'published',
    ]);
  }

  private function seedImage(): Media
  {
    return Media::query()->create([
      'disk' => 'public', 'path' => 'media/bg.jpg', 'filename' => 'bg.jpg',
      'mime_type' => 'image/jpeg', 'kind' => Media::KIND_IMAGE, 'visibility' => 'public',
    ]);
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
