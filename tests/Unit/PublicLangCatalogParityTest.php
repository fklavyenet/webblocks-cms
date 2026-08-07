<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The visitor-facing catalogs (blocks, public, validation) are what a public
 * page renders in a site's own languages, and a locale a site enables without a
 * catalog silently falls back to English -- which is how a fully translated
 * French page kept English form labels. Every catalog directory the package
 * ships must therefore carry the same keys as English, and be complete before
 * it ships at all: half a catalog reads as a broken translation, not a missing
 * one.
 *
 * The admin catalog is deliberately not covered here. It follows the admin UI
 * language list in AdminLocaleResolver, which is a separate, larger commitment
 * than translating what a site's visitors read.
 */
class PublicLangCatalogParityTest extends TestCase
{
  private const PUBLIC_CATALOGS = ['blocks.php', 'public.php', 'validation.php'];

  #[Test]
  public function every_shipped_locale_carries_the_full_visitor_facing_catalog(): void
  {
    $missing = [];

    foreach ($this->shippedLocales() as $locale) {
      foreach (self::PUBLIC_CATALOGS as $catalog) {
        $path = $this->langPath($locale.'/'.$catalog);

        if (! is_file($path)) {
          $missing[] = $locale.'/'.$catalog;

          continue;
        }

        $keys = $this->dotKeys(require $path);
        $expected = $this->dotKeys(require $this->langPath('en/'.$catalog));

        $this->assertSame($expected, $keys, $locale.'/'.$catalog.' keys must match en.');
      }
    }

    $this->assertSame([], $missing, 'These shipped locales are missing a visitor-facing catalog.');
  }

  /**
   * @return list<string>
   */
  private function shippedLocales(): array
  {
    $locales = [];

    foreach (scandir($this->langPath('')) ?: [] as $entry) {
      if ($entry !== '.' && $entry !== '..' && is_dir($this->langPath($entry))) {
        $locales[] = $entry;
      }
    }

    sort($locales);

    $this->assertContains('en', $locales, 'English is the fallback every other catalog is compared against.');

    return $locales;
  }

  private function langPath(string $suffix): string
  {
    return rtrim(dirname(__DIR__, 2).'/resources/lang/'.$suffix, '/');
  }

  /**
   * @return list<string>
   */
  private function dotKeys(array $values, string $prefix = ''): array
  {
    $keys = [];

    foreach ($values as $key => $value) {
      $dotted = $prefix === '' ? (string) $key : $prefix.'.'.$key;

      if (is_array($value)) {
        $keys = array_merge($keys, $this->dotKeys($value, $dotted));

        continue;
      }

      $keys[] = $dotted;
    }

    sort($keys);

    return $keys;
  }
}
