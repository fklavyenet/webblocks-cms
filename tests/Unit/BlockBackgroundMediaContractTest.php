<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Models\Block;

class BlockBackgroundMediaContractTest extends TestCase
{
  #[Test]
  public function it_maps_background_media_to_the_native_webblocks_ui_contract(): void
  {
    $block = $this->backgroundBlock([
      'background_position' => 'bottom',
      'background_overlay' => 'medium',
    ]);

    $this->assertSame(
      'wb-background-media wb-background-media--overlay-medium',
      $block->publicBackgroundMediaClass()
    );
    $this->assertSame(
      "--wb-background-media-image: url('/media/section.webp'); --wb-background-media-position: bottom;",
      $block->publicBackgroundMediaStyle()
    );
  }

  #[Test]
  public function soft_overlay_uses_the_native_primitive_default(): void
  {
    $block = $this->backgroundBlock([]);

    $this->assertSame('wb-background-media', $block->publicBackgroundMediaClass());
  }

  private function backgroundBlock(array $settings): Block
  {
    $block = new class extends Block
    {
      public function publicBackgroundMediaUrl(): ?string
      {
        return '/media/section.webp';
      }
    };
    $block->settings = json_encode($settings, JSON_UNESCAPED_SLASHES);

    return $block;
  }
}
