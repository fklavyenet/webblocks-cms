<?php

namespace WebBlocks\Cms\Support\Theme;

/**
 * Reads the font families a site actually ships, so the Appearance tab can
 * offer a list instead of asking an operator to type a font stack from memory.
 *
 * The source of truth is the site CSS asset: a family is available to visitors
 * only if an `@font-face` rule loads it there (or it is a system stack the CMS
 * ships as a fallback choice).
 */
class InstalledFonts
{
  /**
   * Stacks that need no webfont because every target platform already has a
   * face for them.
   *
   * @var array<string, string>
   */
  public const SYSTEM_STACKS = [
    'system-ui, sans-serif' => 'System sans',
    'Georgia, "Times New Roman", serif' => 'System serif',
    'ui-monospace, SFMono-Regular, Menlo, monospace' => 'System monospace',
  ];

  /**
   * Families declared through `@font-face` in the given CSS.
   *
   * @return list<string>
   */
  public static function fromCss(?string $css): array
  {
    if (! is_string($css) || trim($css) === '') {
      return [];
    }

    if (preg_match_all('/@font-face\s*\{[^}]*\}/i', $css, $blocks) === false) {
      return [];
    }

    $families = [];

    foreach ($blocks[0] as $block) {
      if (preg_match('/font-family\s*:\s*([^;]+);/i', $block, $match) !== 1) {
        continue;
      }

      $family = trim($match[1]);
      $family = trim($family, "\"' \t\n\r");

      if ($family === '' || in_array($family, $families, true)) {
        continue;
      }

      $families[] = $family;
    }

    sort($families);

    return $families;
  }

  /**
   * Options for a font picker: installed families first, then system stacks.
   * Keys are the stack that gets stored, values are what the operator reads.
   *
   * @return array<string, string>
   */
  public static function options(?string $css, string $generic = 'sans-serif'): array
  {
    $options = [];

    foreach (self::fromCss($css) as $family) {
      $stack = str_contains($family, ' ')
        ? '"'.$family.'", '.$generic
        : $family.', '.$generic;

      $options[$stack] = $family;
    }

    return $options + self::SYSTEM_STACKS;
  }
}
