<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use WebBlocks\Cms\Http\Requests\Admin\BlockRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The slide's Overlay field was written but never read.
 *
 * Slide shares the admin Background Media partial with section, hero, cta and
 * the rest, and BlockRequest stores its `background_overlay` — but the public
 * slide template only consumed the image URL and Background Position. Position
 * worked and Overlay did nothing, on a block type whose whole purpose is text
 * over a photograph.
 *
 * Slides cannot use the wb-background-media primitive the other block types
 * use: their image is a real <img>, and the darkening comes from
 * .wb-slide::after painting var(--wb-slider-overlay). Setting a
 * wb-slider-overlay-* class on the slide redefines that custom property for
 * that slide only, so this needs no CSS of its own.
 */
class SlideBackgroundOverlayTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  /**
   * @return array<string, array{0: string, 1: string}>
   */
  public static function overlayLevels(): array
  {
    return [
      'none clears the slider overlay' => ['none', 'wb-slider-overlay-none'],
      'soft' => ['soft', 'wb-slider-overlay-soft'],
      // The field offers four levels and the slider pattern defines three.
      // medium rounds up: it is a deliberate move away from soft, and rounding
      // down would render the change invisible — the bug being fixed here.
      'medium rounds up to strong' => ['medium', 'wb-slider-overlay-strong'],
      'strong' => ['strong', 'wb-slider-overlay-strong'],
    ];
  }

  #[Test]
  #[DataProvider('overlayLevels')]
  public function a_slide_renders_the_overlay_class_for_its_setting(string $setting, string $expectedClass): void
  {
    $html = $this->renderSlide(['background_overlay' => $setting]);

    $this->assertStringContainsString($expectedClass, $html);
  }

  #[Test]
  public function an_untouched_slide_leaves_the_slider_overlay_in_charge(): void
  {
    // BlockRequest only stores this key for a non-default choice, so "no key"
    // is the normal state. Emitting a class here would silently override the
    // slider's own overlay on every existing slide.
    $html = $this->renderSlide([]);

    $this->assertStringNotContainsString('wb-slider-overlay-', $html);
  }

  #[Test]
  public function an_unrecognized_setting_falls_back_to_the_slider_overlay(): void
  {
    $html = $this->renderSlide(['background_overlay' => 'dramatic']);

    $this->assertStringNotContainsString('wb-slider-overlay-', $html);
  }

  #[Test]
  public function the_overlay_does_not_depend_on_the_slide_having_an_image(): void
  {
    // Unlike wb-background-media, .wb-slide::after paints unconditionally, so a
    // slide with a colour background still honours the setting.
    $html = $this->renderSlide(['background_overlay' => 'strong']);

    $this->assertStringNotContainsString('<img', $html);
    $this->assertStringContainsString('wb-slider-overlay-strong', $html);
  }

  #[Test]
  public function background_position_still_renders_alongside_it(): void
  {
    // Position was the half of this form that already worked; it has to keep
    // working now that the other half reads from the same settings blob.
    $media = Media::query()->create([
      'disk' => 'public', 'path' => 'media/slide.jpg', 'filename' => 'slide.jpg',
      'mime_type' => 'image/jpeg', 'kind' => Media::KIND_IMAGE, 'visibility' => 'public',
    ]);

    $html = $this->renderSlide(
      ['background_overlay' => 'none', 'background_position' => 'top'],
      $media->id,
    );

    $this->assertStringContainsString('wb-slider-overlay-none', $html);
    $this->assertStringContainsString('media/slide.jpg', $html);
    $this->assertStringContainsString('object-position: top;', $html);
  }

  #[Test]
  public function a_slide_stores_an_explicit_soft_choice_that_other_block_types_drop(): void
  {
    // Elsewhere `soft` is the rendered default, so storing it would be noise.
    // On a slide the absent key means "inherit", so soft has to be storable or
    // a slider set to strong could never have one softer slide.
    $this->assertSame('soft', $this->storedOverlayFor(isSlide: true, overlay: 'soft'));
    $this->assertNull($this->storedOverlayFor(isSlide: false, overlay: 'soft'));

    // Inherit posts an empty value, which is stored on neither.
    $this->assertNull($this->storedOverlayFor(isSlide: true, overlay: ''));

    // The levels that were already storable stay storable on both.
    $this->assertSame('strong', $this->storedOverlayFor(isSlide: true, overlay: 'strong'));
    $this->assertSame('strong', $this->storedOverlayFor(isSlide: false, overlay: 'strong'));
  }

  #[Test]
  public function only_the_slide_form_turns_the_overlay_field_into_an_override(): void
  {
    $slideForm = (string) file_get_contents(
      dirname(__DIR__, 2).'/resources/views/admin/blocks/types/slide.blade.php'
    );
    $partial = (string) file_get_contents(
      dirname(__DIR__, 2).'/resources/views/admin/blocks/types/partials/background-media-fields.blade.php'
    );
    $request = (string) file_get_contents(
      dirname(__DIR__, 2).'/src/Http/Requests/Admin/BlockRequest.php'
    );

    $this->assertStringContainsString("['overlayInherits' => true]", $slideForm);
    $this->assertStringContainsString('storeSoftOverlay: true', $request);
    $this->assertSame(
      1,
      substr_count($request, 'storeSoftOverlay: true'),
      'Only slide inherits from a slider; another block type opting in would start storing a default it does not need.'
    );

    // Absent flag must keep the previous four-option, soft-default behaviour
    // for section, hero, cta, card and content_header.
    $this->assertStringContainsString('$overlayInherits = ($overlayInherits ?? false) === true;', $partial);
    $this->assertStringContainsString("? '' : 'soft'", $partial);
  }

  /**
   * Calls the persist rule directly. Driving it through validateResolved()
   * would mean satisfying every unrelated rule for two different block types,
   * which tests the rules rather than the one branch that matters here.
   */
  private function storedOverlayFor(bool $isSlide, string $overlay): ?string
  {
    $method = new ReflectionMethod(BlockRequest::class, 'applyBackgroundMediaSettings');

    $settings = $method->invoke(
      new BlockRequest,
      [],
      ['background_overlay' => $overlay],
      $isSlide,
    );

    return $settings['background_overlay'] ?? null;
  }

  /**
   * @param  array<string, mixed>  $settings
   */
  private function renderSlide(array $settings, ?int $mediaId = null): string
  {
    $block = Block::query()->make([
      'type' => 'slide',
      'source_type' => 'static',
      'slot' => 'main',
      'sort_order' => 0,
      'status' => 'published',
      'media_id' => $mediaId,
      'settings' => $settings,
    ]);

    $block->setRelation('children', new Collection);

    return view('webblocks-cms::pages.partials.blocks.slide', [
      'block' => $block,
    ])->render();
  }
}
