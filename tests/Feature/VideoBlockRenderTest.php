<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Regression: the video block's own documented contract (docs/public-block-render-markup.md
 * and the block_contracts "video" entry) promises a native <video> for hosted media, safe
 * embeds for known providers, or an external link fallback for anything else. The renderer
 * used to compute $videoSource as $assetUrl ?: ($embedUrl ? null : $safeUrl), so any URL from
 * an unrecognized host absorbed into $videoSource and rendered a broken <video><source> tag
 * instead of ever reaching the "Open video" link fallback.
 */
class VideoBlockRenderTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function an_unrecognized_provider_url_renders_the_link_fallback_not_a_broken_video_tag(): void
  {
    $html = $this->renderVideo(['url' => 'https://example.com/watch/some-clip']);

    $this->assertStringNotContainsString('<video', $html);
    $this->assertStringContainsString('<a href="https://example.com/watch/some-clip"', $html);
    $this->assertStringContainsString('Open video', $html);
  }

  #[Test]
  public function an_uploaded_media_asset_still_renders_a_native_video_tag(): void
  {
    $media = Media::query()->create([
      'disk' => 'public', 'path' => 'media/clip.mp4', 'filename' => 'clip.mp4',
      'mime_type' => 'video/mp4', 'kind' => Media::KIND_VIDEO, 'visibility' => 'public',
    ]);

    $html = $this->renderVideo(['media_id' => $media->id]);

    $this->assertStringContainsString('<video controls preload="metadata">', $html);
    $this->assertStringContainsString('media/clip.mp4', $html);
    $this->assertStringNotContainsString('Open video', $html);
  }

  #[Test]
  public function a_youtube_url_still_renders_the_iframe_embed(): void
  {
    $html = $this->renderVideo(['url' => 'https://www.youtube.com/watch?v=abc123']);

    $this->assertStringNotContainsString('<video', $html);
    $this->assertStringContainsString('<iframe', $html);
    $this->assertStringContainsString('https://www.youtube.com/embed/abc123', $html);
  }

  #[Test]
  public function a_vimeo_url_still_renders_the_iframe_embed(): void
  {
    $html = $this->renderVideo(['url' => 'https://vimeo.com/76979871']);

    $this->assertStringNotContainsString('<video', $html);
    $this->assertStringContainsString('<iframe', $html);
    $this->assertStringContainsString('https://player.vimeo.com/video/76979871', $html);
  }

  #[Test]
  public function an_asset_wins_over_an_unrecognized_url_when_both_are_set(): void
  {
    $media = Media::query()->create([
      'disk' => 'public', 'path' => 'media/clip.mp4', 'filename' => 'clip.mp4',
      'mime_type' => 'video/mp4', 'kind' => Media::KIND_VIDEO, 'visibility' => 'public',
    ]);

    $html = $this->renderVideo(['media_id' => $media->id, 'url' => 'https://example.com/watch/some-clip']);

    $this->assertStringContainsString('media/clip.mp4', $html);
    $this->assertStringNotContainsString('example.com', $html);
  }

  private function renderVideo(array $attributes): string
  {
    $block = Block::query()->make($attributes + [
      'type' => 'video', 'source_type' => 'static', 'slot' => 'main',
      'sort_order' => 0, 'status' => 'published',
    ]);

    return view('webblocks-cms::pages.partials.blocks.video', [
      'block' => $block,
    ])->render();
  }
}
