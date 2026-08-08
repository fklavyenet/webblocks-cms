<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Models\Block;

/**
 * The admin field and the API contract both offer four overlay levels, but the
 * class mapping lived in two separate match arms that had drifted: the slide arm
 * collapsed `medium` onto strong, and the slider-root arm had no `medium` case
 * at all so it fell through to no overlay. The same stored word produced three
 * different renderings depending on where it was set, and authors who picked
 * `medium` silently shipped a full-strength scrim.
 *
 * WebBlocks UI 2.22.0 ships wb-slider-overlay-medium, so every documented level
 * now has a distinct class. These tests hold the two surfaces to one scale.
 */
class SliderOverlayScaleTest extends TestCase
{
  private function slide(?string $overlay): Block
  {
    $block = new Block;
    $block->settings = $overlay === null ? [] : ['background_overlay' => $overlay];

    return $block;
  }

  private function sliderRoot(?string $overlay): Block
  {
    $block = new Block;
    $block->settings = $overlay === null ? [] : ['overlay' => $overlay];

    return $block;
  }

  /**
   * @return array<string, array{0: string, 1: string}>
   */
  public static function documentedLevels(): array
  {
    return [
      'none' => ['none', 'wb-slider-overlay-none'],
      'soft' => ['soft', 'wb-slider-overlay-soft'],
      'medium' => ['medium', 'wb-slider-overlay-medium'],
      'strong' => ['strong', 'wb-slider-overlay-strong'],
    ];
  }

  #[Test]
  #[DataProvider('documentedLevels')]
  public function a_slide_maps_each_documented_level_to_its_own_class(string $overlay, string $expected): void
  {
    $this->assertSame($expected, $this->slide($overlay)->slideBackgroundOverlayClass());
  }

  #[Test]
  #[DataProvider('documentedLevels')]
  public function a_slider_root_maps_each_documented_level_to_its_own_class(string $overlay, string $expected): void
  {
    $this->assertSame($expected, $this->sliderRoot($overlay)->sliderOverlayClass());
  }

  #[Test]
  #[DataProvider('documentedLevels')]
  public function the_slider_root_and_a_slide_agree_on_every_level(string $overlay, string $expected): void
  {
    $this->assertSame(
      $this->sliderRoot($overlay)->sliderOverlayClass(),
      $this->slide($overlay)->slideBackgroundOverlayClass(),
      "The slider root and a slide must render '{$overlay}' identically."
    );
    $this->assertSame($expected, $this->slide($overlay)->slideBackgroundOverlayClass());
  }

  #[Test]
  public function medium_is_no_longer_an_alias_for_strong(): void
  {
    $this->assertNotSame(
      $this->slide('strong')->slideBackgroundOverlayClass(),
      $this->slide('medium')->slideBackgroundOverlayClass()
    );
  }

  #[Test]
  public function the_legacy_dark_alias_still_resolves_to_strong(): void
  {
    $this->assertSame('wb-slider-overlay-strong', $this->sliderRoot('dark')->sliderOverlayClass());
  }

  #[Test]
  public function an_unset_slide_overlay_leaves_the_slider_in_control(): void
  {
    $this->assertNull($this->slide(null)->slideBackgroundOverlayClass());
  }
}
