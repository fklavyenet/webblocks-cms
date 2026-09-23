<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Support\Blocks\PublicOverlayRegistry;
use WebBlocks\Cms\Tests\TestCase;

class PublicOverlayRegistryTest extends TestCase
{
  #[Test]
  public function viewer_enabled_image_uses_the_gallery_trigger_and_matching_group_viewer(): void
  {
    $this->app->instance('request', Request::create('/article', 'GET'));
    Storage::fake('public');
    $media = new Media([
      'disk' => 'public',
      'path' => 'media/article.jpg',
      'visibility' => 'public',
      'kind' => Media::KIND_IMAGE,
      'mime_type' => 'image/jpeg',
      'extension' => 'jpg',
      'filename' => 'article.jpg',
      'width' => 800,
      'height' => 500,
      'alt_text' => 'Article diagram',
    ]);
    $media->id = 20;
    $block = new Block([
      'type' => 'image',
      'settings' => json_encode(['viewer_enabled' => true, 'viewer_group' => 'article-images']),
      'title' => 'Diagram caption',
    ]);
    $block->id = 10;
    $block->setRelation('media', $media);

    $html = view('webblocks-cms::pages.partials.blocks.image', compact('block'))->render();
    $overlay = app(PublicOverlayRegistry::class)->all()->first();
    $viewerId = app(PublicOverlayRegistry::class)->imageViewerId('article-images');

    $this->assertStringContainsString('class="wb-gallery-trigger"', $html);
    $this->assertStringContainsString('data-wb-gallery-target="#'.$viewerId.'"', $html);
    $this->assertStringContainsString('id="'.$viewerId.'"', $overlay);
    $this->assertStringContainsString('1 / 1', $overlay);
  }

  #[Test]
  public function image_blocks_in_one_group_share_one_gallery_viewer(): void
  {
    $this->app->instance('request', Request::create('/article', 'GET'));
    $registry = app(PublicOverlayRegistry::class);

    $registry->registerImageViewerItem('article-images', 10, [
      'full_url' => '/media/one.jpg',
      'alt' => 'First image',
      'caption' => 'First caption',
      'meta' => '',
      'width' => 1200,
      'height' => 800,
    ], 'en');
    $registry->registerImageViewerItem('article-images', 11, [
      'full_url' => '/media/two.jpg',
      'alt' => 'Second image',
      'caption' => 'Second caption',
      'meta' => '',
      'width' => 1200,
      'height' => 800,
    ], 'en');

    $overlays = $registry->all();

    $this->assertCount(1, $overlays);
    $this->assertStringContainsString('wb-gallery-viewer-image-group-', $overlays->first());
    $this->assertStringContainsString('1 / 2', $overlays->first());
    $this->assertStringContainsString('/media/one.jpg', $overlays->first());
  }

  #[Test]
  public function repeated_registration_of_one_block_does_not_duplicate_the_viewer_item(): void
  {
    $this->app->instance('request', Request::create('/article', 'GET'));
    $registry = app(PublicOverlayRegistry::class);
    $item = [
      'full_url' => '/media/one.jpg',
      'alt' => 'Image',
      'caption' => '',
      'meta' => '',
      'width' => null,
      'height' => null,
    ];

    $registry->registerImageViewerItem('article-images', 10, $item, 'en');
    $registry->registerImageViewerItem('article-images', 10, $item, 'en');

    $this->assertStringContainsString('1 / 1', $registry->all()->first());
  }
}
