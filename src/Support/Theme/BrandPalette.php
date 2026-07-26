<?php

namespace WebBlocks\Cms\Support\Theme;

/**
 * Derives the full set of public theme tokens from the four brand colours an
 * operator chooses in Sites -> Edit Site -> Appearance.
 *
 * The class is a pure function of its inputs: same colours in, same tokens out.
 * All mixing happens in sRGB and foreground choices use WCAG relative
 * luminance, so nothing depends on browser colour support at render time.
 *
 * See docs/brand-palette.md for the product contract.
 */
class BrandPalette
{
  /**
   * Minimum contrast ratio for body-sized text on its background.
   */
  private const READABLE_CONTRAST = 4.5;

  public function __construct(
    private readonly ?string $accent = null,
    private readonly ?string $accentSecondary = null,
    private readonly ?string $surface = null,
    private readonly ?string $text = null,
    private readonly ?string $fontHeading = null,
    private readonly ?string $fontBody = null,
  ) {}

  /**
   * @param  array<string, string|null>  $fields
   */
  public static function fromFields(array $fields): self
  {
    return new self(
      accent: self::normalizeColour($fields['brand_accent'] ?? null),
      accentSecondary: self::normalizeColour($fields['brand_accent_secondary'] ?? null),
      surface: self::normalizeColour($fields['brand_surface'] ?? null),
      text: self::normalizeColour($fields['brand_text'] ?? null),
      fontHeading: self::normalizeFontStack($fields['brand_font_heading'] ?? null),
      fontBody: self::normalizeFontStack($fields['brand_font_body'] ?? null),
    );
  }

  public function isEmpty(): bool
  {
    return $this->accent === null
      && $this->accentSecondary === null
      && $this->surface === null
      && $this->text === null
      && $this->fontHeading === null
      && $this->fontBody === null;
  }

  /**
   * Light-mode tokens. Only roles whose inputs are present are emitted, so a
   * partially configured palette leaves the rest of the preset intact.
   *
   * @return array<string, string>
   */
  public function lightTokens(): array
  {
    $tokens = [];
    $surface = $this->surface;
    $text = $this->text;
    $accent = $this->accent;

    if ($surface !== null) {
      $tokens['--wb-public-page-bg'] = $surface;
      $tokens['--wb-public-surface'] = self::mix('#ffffff', $surface, 0.60);
    }

    if ($surface !== null && $text !== null) {
      $tokens['--wb-public-surface-muted'] = self::mix($text, $surface, 0.03);
      $tokens['--wb-public-surface-strong'] = self::mix($text, $surface, 0.07);
      $tokens['--wb-public-border'] = self::mix($text, $surface, 0.14);
    }

    if ($text !== null) {
      $tokens['--wb-public-text'] = $text;
      $tokens['--wb-public-muted'] = self::mix($surface ?? '#ffffff', $text, 0.42);
    }

    if ($accent !== null) {
      $base = $surface ?? '#ffffff';

      $tokens['--wb-public-accent'] = $accent;
      $tokens['--wb-public-accent-hover'] = self::darken($accent, 0.12);
      $tokens['--wb-public-accent-active'] = self::darken($accent, 0.20);
      $tokens['--wb-public-accent-on'] = self::readableOn($accent, $base, $text ?? '#1a1a1a');
      $tokens['--wb-public-accent-soft'] = self::mix($accent, $base, 0.14);
      $tokens['--wb-public-accent-softer'] = self::mix($accent, $base, 0.07);
      $tokens['--wb-public-accent-text'] = self::ensureContrast($accent, $base);
      $tokens['--wb-public-tone-brand'] = $accent;
      $tokens['--wb-public-accent-ring-rgb'] = self::rgbChannels($accent);
    }

    if ($this->accentSecondary !== null) {
      $tokens['--wb-public-tone-accent-value'] = $this->accentSecondary;
      $tokens['--wb-public-accent-border'] = self::mix(
        $this->accentSecondary,
        $surface ?? '#ffffff',
        0.55
      );
    }

    if ($text !== null) {
      // Inverse pair: a filled dark band plus the foreground that reads on it.
      $tokens['--wb-public-inverse-surface'] = $accent !== null
        ? self::mix($accent, self::darken($text, 0.35), 0.08)
        : self::darken($text, 0.35);
      $tokens['--wb-public-inverse-text'] = $surface ?? '#ffffff';
    }

    return $tokens;
  }

  /**
   * Dark-mode tokens derived from the same four inputs with swapped roles.
   *
   * @return array<string, string>
   */
  public function darkTokens(): array
  {
    $tokens = [];
    $surface = $this->surface;
    $text = $this->text;
    $accent = $this->accent;

    if ($accent === null && $surface === null && $text === null) {
      return $tokens;
    }

    // A near-black page, warmed by the brand accent so dark mode still reads
    // as the same brand rather than a generic grey.
    $pageBg = $accent !== null
      ? self::mix($accent, '#0d0d0d', 0.10)
      : '#111111';

    $tokens['--wb-public-page-bg'] = $pageBg;
    $tokens['--wb-public-surface'] = self::mix('#ffffff', $pageBg, 0.06);
    $tokens['--wb-public-surface-muted'] = self::mix('#ffffff', $pageBg, 0.09);
    $tokens['--wb-public-surface-strong'] = self::mix('#ffffff', $pageBg, 0.13);
    $tokens['--wb-public-border'] = self::mix('#ffffff', $pageBg, 0.20);

    $foreground = $surface ?? '#f5f5f5';
    $tokens['--wb-public-text'] = $foreground;
    $tokens['--wb-public-muted'] = self::mix($pageBg, $foreground, 0.35);

    if ($accent !== null) {
      // Lighten the accent — toward the secondary when there is one — until it
      // clears body-text contrast against the dark page.
      $target = $this->accentSecondary ?? '#ffffff';
      $darkAccent = self::ensureContrast($accent, $pageBg, $target);

      $tokens['--wb-public-accent'] = $darkAccent;
      $tokens['--wb-public-accent-hover'] = self::mix($target, $darkAccent, 0.18);
      $tokens['--wb-public-accent-active'] = self::darken($darkAccent, 0.12);
      $tokens['--wb-public-accent-on'] = self::readableOn($darkAccent, $foreground, $pageBg);
      $tokens['--wb-public-accent-soft'] = self::mix($darkAccent, $pageBg, 0.18);
      $tokens['--wb-public-accent-softer'] = self::mix($darkAccent, $pageBg, 0.10);
      $tokens['--wb-public-accent-border'] = self::mix($darkAccent, $pageBg, 0.35);
      $tokens['--wb-public-accent-text'] = $darkAccent;
      $tokens['--wb-public-tone-brand'] = $darkAccent;
      $tokens['--wb-public-accent-ring-rgb'] = self::rgbChannels($darkAccent);
    }

    if ($this->accentSecondary !== null) {
      $tokens['--wb-public-tone-accent-value'] = $this->accentSecondary;
    }

    $tokens['--wb-public-inverse-surface'] = self::mix('#ffffff', $pageBg, 0.10);
    $tokens['--wb-public-inverse-text'] = $foreground;

    return $tokens;
  }

  /**
   * @return array<string, string>
   */
  public function fontTokens(): array
  {
    $tokens = [];

    if ($this->fontHeading !== null) {
      $tokens['--wb-public-font-heading'] = $this->fontHeading;
    }

    if ($this->fontBody !== null) {
      $tokens['--wb-public-font-body'] = $this->fontBody;
    }

    return $tokens;
  }

  /**
   * Contrast ratio of the accent against the page background, for the admin
   * readability warning. Null when either input is missing.
   */
  public function accentContrast(): ?float
  {
    if ($this->accent === null || $this->surface === null) {
      return null;
    }

    return round(self::contrast($this->accent, $this->surface), 2);
  }

  public static function normalizeColour(mixed $value): ?string
  {
    if (! is_string($value)) {
      return null;
    }

    $value = strtolower(trim($value));

    if ($value === '') {
      return null;
    }

    if (preg_match('/^#([0-9a-f]{3})$/', $value, $matches) === 1) {
      [$r, $g, $b] = str_split($matches[1]);

      return '#'.$r.$r.$g.$g.$b.$b;
    }

    return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : null;
  }

  /**
   * Font stacks are written straight into a declaration, so the accepted
   * character set is deliberately narrow: no braces, semicolons, parentheses,
   * or `url(` can survive.
   */
  public static function normalizeFontStack(mixed $value): ?string
  {
    if (! is_string($value)) {
      return null;
    }

    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

    if ($value === '' || mb_strlen($value) > 180) {
      return null;
    }

    return preg_match('/^[A-Za-z0-9 ,\'"._-]+$/', $value) === 1 ? $value : null;
  }

  // ------------------------------------------------------------------ colour

  /**
   * Mix `$colour` into `$base` at `$amount` (0..1).
   */
  private static function mix(string $colour, string $base, float $amount): string
  {
    $amount = max(0.0, min(1.0, $amount));
    [$r1, $g1, $b1] = self::channels($colour);
    [$r2, $g2, $b2] = self::channels($base);

    return self::hex(
        (int) round($r1 * $amount + $r2 * (1 - $amount)),
        (int) round($g1 * $amount + $g2 * (1 - $amount)),
        (int) round($b1 * $amount + $b2 * (1 - $amount)),
    );
  }

  private static function darken(string $colour, float $amount): string
  {
    return self::mix('#000000', $colour, $amount);
  }

  /**
   * Pick whichever candidate reads better on `$background`.
   */
  private static function readableOn(string $background, string $light, string $dark): string
  {
    return self::contrast($light, $background) >= self::contrast($dark, $background)
      ? $light
      : $dark;
  }

  /**
   * Step `$colour` until it clears body-text contrast against `$background`.
   *
   * When the caller supplies a `$target` (usually the site's secondary accent)
   * the colour moves toward it first, so a brand keeps its own hues wherever
   * that is enough. If the target cannot carry it far enough the colour then
   * moves toward white or black — whichever the background calls for — which
   * always converges.
   */
  private static function ensureContrast(string $colour, string $background, ?string $target = null): string
  {
    $candidate = $colour;

    if (self::contrast($candidate, $background) >= self::READABLE_CONTRAST) {
      return $candidate;
    }

    if ($target !== null) {
      for ($step = 0; $step < 12; $step++) {
        $candidate = self::mix($target, $candidate, 0.12);

        if (self::contrast($candidate, $background) >= self::READABLE_CONTRAST) {
          return $candidate;
        }
      }
    }

    $extreme = self::luminance($background) < 0.18 ? '#ffffff' : '#000000';

    for ($step = 0; $step < 24; $step++) {
      $candidate = self::mix($extreme, $candidate, 0.10);

      if (self::contrast($candidate, $background) >= self::READABLE_CONTRAST) {
        return $candidate;
      }
    }

    return $candidate;
  }

  private static function contrast(string $a, string $b): float
  {
    $la = self::luminance($a);
    $lb = self::luminance($b);
    $lighter = max($la, $lb);
    $darker = min($la, $lb);

    return ($lighter + 0.05) / ($darker + 0.05);
  }

  private static function luminance(string $colour): float
  {
    $linear = array_map(static function (int $channel): float {
      $value = $channel / 255;

      return $value <= 0.03928
        ? $value / 12.92
        : (($value + 0.055) / 1.055) ** 2.4;
    }, self::channels($colour));

    return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
  }

  private static function rgbChannels(string $colour): string
  {
    return implode(' ', self::channels($colour));
  }

  /**
   * @return array{int, int, int}
   */
  private static function channels(string $colour): array
  {
    $normalized = self::normalizeColour($colour) ?? '#000000';

    return [
      (int) hexdec(substr($normalized, 1, 2)),
      (int) hexdec(substr($normalized, 3, 2)),
      (int) hexdec(substr($normalized, 5, 2)),
    ];
  }

  private static function hex(int $r, int $g, int $b): string
  {
    return sprintf(
      '#%02x%02x%02x',
      max(0, min(255, $r)),
      max(0, min(255, $g)),
      max(0, min(255, $b)),
    );
  }
}
