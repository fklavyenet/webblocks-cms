<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\Theme\BrandPalette;

/**
 * Brand palette derivation: four operator colours in, the full public token set
 * out, in both light and dark mode. See docs/brand-palette.md.
 */
class BrandPaletteTest extends TestCase
{
  private function configuredPalette(): BrandPalette
  {
    return BrandPalette::fromFields([
      'brand_accent' => '#6a0f25',
      'brand_accent_secondary' => '#c7795b',
      'brand_surface' => '#fffdf7',
      'brand_text' => '#2d2d2d',
      'brand_font_heading' => 'Libre Baskerville, Georgia, serif',
      'brand_font_body' => 'IBM Plex Sans, system-ui, sans-serif',
    ]);
  }

  #[Test]
  public function an_unconfigured_palette_is_empty_and_emits_nothing(): void
  {
    $palette = BrandPalette::fromFields([]);

    $this->assertTrue($palette->isEmpty());
    $this->assertSame([], $palette->lightTokens());
    $this->assertSame([], $palette->darkTokens());
    $this->assertSame([], $palette->fontTokens());
    $this->assertNull($palette->accentContrast());
  }

  #[Test]
  public function light_tokens_use_the_operator_colours_verbatim_where_the_role_is_direct(): void
  {
    $tokens = $this->configuredPalette()->lightTokens();

    $this->assertSame('#6a0f25', $tokens['--wb-public-accent']);
    $this->assertSame('#fffdf7', $tokens['--wb-public-page-bg']);
    $this->assertSame('#2d2d2d', $tokens['--wb-public-text']);
    $this->assertSame('#c7795b', $tokens['--wb-public-tone-accent-value']);
  }

  #[Test]
  public function hover_and_active_states_are_progressively_darker_than_the_accent(): void
  {
    $tokens = $this->configuredPalette()->lightTokens();

    $accent = $this->luminance($tokens['--wb-public-accent']);
    $hover = $this->luminance($tokens['--wb-public-accent-hover']);
    $active = $this->luminance($tokens['--wb-public-accent-active']);

    $this->assertLessThan($accent, $hover);
    $this->assertLessThan($hover, $active);
  }

  #[Test]
  public function the_accent_foreground_is_the_readable_choice_for_a_dark_brand_colour(): void
  {
    $tokens = $this->configuredPalette()->lightTokens();

    // Bordeaux is dark, so cream must win over the near-black text colour.
    $this->assertSame('#fffdf7', $tokens['--wb-public-accent-on']);
  }

  #[Test]
  public function the_accent_foreground_flips_for_a_light_brand_colour(): void
  {
    $tokens = BrandPalette::fromFields([
      'brand_accent' => '#ffe066',
      'brand_surface' => '#ffffff',
      'brand_text' => '#111111',
    ])->lightTokens();

    $this->assertSame('#111111', $tokens['--wb-public-accent-on']);
  }

  #[Test]
  public function accent_text_is_darkened_until_it_reads_on_the_page_background(): void
  {
    $tokens = BrandPalette::fromFields([
      'brand_accent' => '#ffc107',
      'brand_surface' => '#ffffff',
      'brand_text' => '#111111',
    ])->lightTokens();

    $this->assertNotSame('#ffc107', $tokens['--wb-public-accent-text']);
    $this->assertGreaterThanOrEqual(4.5, $this->contrast($tokens['--wb-public-accent-text'], '#ffffff'));
  }

  #[Test]
  public function dark_mode_is_derived_without_a_second_palette(): void
  {
    $tokens = $this->configuredPalette()->darkTokens();

    $this->assertLessThan(
      $this->luminance('#333333'),
      $this->luminance($tokens['--wb-public-page-bg']),
      'The dark page background should be near-black.'
    );
    $this->assertSame('#fffdf7', $tokens['--wb-public-text']);
    $this->assertGreaterThanOrEqual(
      4.5,
      $this->contrast($tokens['--wb-public-accent'], $tokens['--wb-public-page-bg']),
      'The dark-mode accent must clear body-text contrast against the dark page.'
    );
  }

  #[Test]
  public function an_inverse_pair_is_available_for_filled_bands(): void
  {
    $tokens = $this->configuredPalette()->lightTokens();

    $this->assertArrayHasKey('--wb-public-inverse-surface', $tokens);
    $this->assertGreaterThanOrEqual(
      4.5,
      $this->contrast($tokens['--wb-public-inverse-text'], $tokens['--wb-public-inverse-surface'])
    );
  }

  #[Test]
  public function a_partial_palette_only_emits_the_roles_it_can_derive(): void
  {
    $tokens = BrandPalette::fromFields(['brand_accent' => '#0055ff'])->lightTokens();

    $this->assertArrayHasKey('--wb-public-accent', $tokens);
    $this->assertArrayNotHasKey('--wb-public-page-bg', $tokens);
    $this->assertArrayNotHasKey('--wb-public-text', $tokens);
  }

  #[Test]
  public function shorthand_hex_is_expanded_and_junk_is_rejected(): void
  {
    $this->assertSame('#aabbcc', BrandPalette::normalizeColour('#ABC'));
    $this->assertSame('#6a0f25', BrandPalette::normalizeColour('  #6A0F25 '));
    $this->assertNull(BrandPalette::normalizeColour('red'));
    $this->assertNull(BrandPalette::normalizeColour('#12345'));
    $this->assertNull(BrandPalette::normalizeColour('#6a0f25;}body{display:none'));
    $this->assertNull(BrandPalette::normalizeColour(null));
  }

  #[Test]
  public function font_stacks_cannot_escape_the_declaration(): void
  {
    $this->assertSame(
      'Libre Baskerville, Georgia, serif',
      BrandPalette::normalizeFontStack('  Libre   Baskerville,  Georgia,  serif ')
    );
    $this->assertNull(BrandPalette::normalizeFontStack('Arial;}body{display:none}'));
    $this->assertNull(BrandPalette::normalizeFontStack('url(https://evil.test/x.css)'));
    $this->assertNull(BrandPalette::normalizeFontStack(str_repeat('a', 181)));
    $this->assertNull(BrandPalette::normalizeFontStack('   '));
  }

  #[Test]
  public function the_accent_contrast_ratio_is_reported_for_the_admin_warning(): void
  {
    $this->assertGreaterThan(4.5, $this->configuredPalette()->accentContrast());

    $weak = BrandPalette::fromFields([
      'brand_accent' => '#ffe066',
      'brand_surface' => '#ffffff',
    ]);

    $this->assertLessThan(4.5, $weak->accentContrast());
  }

  private function contrast(string $a, string $b): float
  {
    $la = $this->luminance($a);
    $lb = $this->luminance($b);

    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
  }

  private function luminance(string $colour): float
  {
    $channels = [
      (int) hexdec(substr($colour, 1, 2)),
      (int) hexdec(substr($colour, 3, 2)),
      (int) hexdec(substr($colour, 5, 2)),
    ];

    $linear = array_map(static function (int $channel): float {
      $value = $channel / 255;

      return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, $channels);

    return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
  }
}
