<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Tests\TestCase;

class CtaVariantRenderingTest extends TestCase
{
  /**
   * @return array<string, array{0: ?string, 1: string}>
   */
  public static function variants(): array
  {
    return [
      'empty remains default' => [null, 'default'],
      'default' => ['default', 'default'],
      'muted' => ['muted', 'muted'],
      'soft' => ['soft', 'soft'],
      'accent' => ['accent', 'accent'],
      'unknown falls back safely' => ['dramatic', 'default'],
    ];
  }

  #[Test]
  #[DataProvider('variants')]
  public function cta_variants_render_distinct_public_modifier_classes(?string $variant, string $expected): void
  {
    $block = Block::query()->make([
      'type' => 'cta',
      'variant' => $variant,
      'title' => 'Book an appointment',
      'status' => 'published',
    ]);
    $block->setRelation('children', new Collection);

    $html = view('webblocks-cms::pages.partials.blocks.cta', compact('block'))->render();

    $this->assertStringContainsString('wb-public-cta--'.$expected, $html);
  }

  #[Test]
  public function public_css_gives_each_variant_a_distinct_surface_and_tints_media_overlays(): void
  {
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/public/cms/css/public.css');

    $this->assertStringContainsString('.wb-public-cta--muted', $css);
    $this->assertStringContainsString('.wb-public-cta--soft', $css);
    $this->assertStringContainsString('.wb-public-cta--accent', $css);
    $this->assertStringContainsString('.wb-promo.wb-background-media[class*="wb-public-cta--"]', $css);
    $this->assertStringContainsString('--wb-surface: var(--wb-public-cta-surface)', $css);
  }
}
