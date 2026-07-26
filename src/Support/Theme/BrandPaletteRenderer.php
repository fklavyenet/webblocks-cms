<?php

namespace WebBlocks\Cms\Support\Theme;

use WebBlocks\Cms\Models\Site;

/**
 * Turns a site's brand palette into the single `<style id="wb-public-brand">`
 * block the public layout emits.
 *
 * The renderer writes token declarations and the two typography rules only —
 * it is not a general CSS injection point. Values are produced by
 * {@see BrandPalette}, which already constrains them to hex colours and a
 * narrow font-stack character set.
 */
class BrandPaletteRenderer
{
  public function render(?Site $site): string
  {
    if (! $site) {
      return '';
    }

    $palette = $site->brandPalette();

    if ($palette->isEmpty()) {
      return '';
    }

    $light = array_merge($palette->lightTokens(), $palette->fontTokens());
    $dark = $palette->darkTokens();
    $fonts = $palette->fontTokens();

    $blocks = [];

    if ($light !== []) {
      $blocks[] = 'body[data-wb-public-theme]{'.self::declarations($light).'}';
    }

    if ($dark !== []) {
      $darkDeclarations = self::declarations($dark);

      $blocks[] = 'html[data-mode="dark"] body[data-wb-public-theme]{'.$darkDeclarations.'}';
      $blocks[] = '@media (prefers-color-scheme:dark){html[data-mode="auto"] body[data-wb-public-theme]{'
        .$darkDeclarations.'}}';
    }

    if (isset($fonts['--wb-public-font-body'])) {
      $blocks[] = 'body.wb-public-body{font-family:var(--wb-public-font-body);}';
    }

    if (isset($fonts['--wb-public-font-heading'])) {
      $blocks[] = 'main :is(h1,h2,h3,h4),.wb-promo-title,.wb-content-title'
        .'{font-family:var(--wb-public-font-heading);}';
    }

    return implode('', $blocks);
  }

  /**
   * @param  array<string, string>  $tokens
   */
  private static function declarations(array $tokens): string
  {
    $declarations = '';

    foreach ($tokens as $name => $value) {
      $declarations .= $name.':'.$value.';';
    }

    return $declarations;
  }
}
