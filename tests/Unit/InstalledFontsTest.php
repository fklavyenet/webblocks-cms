<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\Theme\BrandPalette;
use WebBlocks\Cms\Support\Theme\InstalledFonts;

/**
 * The Appearance tab offers the families a site actually ships rather than
 * asking an operator to type a font stack from memory. The site CSS asset is
 * the source: a family is available only if an @font-face rule loads it.
 */
class InstalledFontsTest extends TestCase
{
  private const CSS = <<<'CSS'
    @font-face {
      font-family: "Nunito";
      font-weight: 400 900;
      src: url("/site/play/fonts/nunito.woff2") format("woff2");
    }

    @font-face {
      font-family: 'Libre Baskerville';
      font-style: italic;
      src: url("/site/play/fonts/baskerville-italic.woff2") format("woff2");
    }

    @font-face {
      font-family: Nunito;
      font-style: italic;
      src: url("/site/play/fonts/nunito-italic.woff2") format("woff2");
    }

    body { font-family: "Not A Face", sans-serif; }
    CSS;

  #[Test]
  public function it_reads_every_declared_family_once(): void
  {
    $this->assertSame(['Libre Baskerville', 'Nunito'], InstalledFonts::fromCss(self::CSS));
  }

  #[Test]
  public function a_family_used_but_never_loaded_is_not_offered(): void
  {
    $this->assertNotContains('Not A Face', InstalledFonts::fromCss(self::CSS));
  }

  #[Test]
  public function empty_css_yields_no_families(): void
  {
    $this->assertSame([], InstalledFonts::fromCss(null));
    $this->assertSame([], InstalledFonts::fromCss('   '));
    $this->assertSame([], InstalledFonts::fromCss('body { color: red; }'));
  }

  #[Test]
  public function options_quote_multi_word_families_and_append_a_generic(): void
  {
    $options = InstalledFonts::options(self::CSS);

    $this->assertArrayHasKey('"Libre Baskerville", sans-serif', $options);
    $this->assertSame('Libre Baskerville', $options['"Libre Baskerville", sans-serif']);
    $this->assertArrayHasKey('Nunito, sans-serif', $options);
  }

  #[Test]
  public function system_stacks_are_always_offered_so_a_site_without_webfonts_still_has_choices(): void
  {
    $options = InstalledFonts::options(null);

    $this->assertSame(InstalledFonts::SYSTEM_STACKS, $options);
    $this->assertArrayHasKey('system-ui, sans-serif', $options);
  }

  #[Test]
  public function every_offered_stack_survives_palette_validation(): void
  {
    foreach (array_keys(InstalledFonts::options(self::CSS)) as $stack) {
      $this->assertSame(
        $stack,
        BrandPalette::normalizeFontStack($stack),
        'An offered stack must be storable as-is.'
      );
    }
  }
}
