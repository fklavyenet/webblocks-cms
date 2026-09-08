<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;

class AdminTranslationCoverageTest extends TestCase
{
  public function test_dynamic_admin_javascript_does_not_embed_english_ui_fallbacks(): void
  {
    $root = dirname(__DIR__, 2).'/public/cms/js/admin';
    $files = ['media-copy.js', 'inline-block-builder.js', 'slot-block-tree.js', 'builder-items.js', 'asset-picker.js', 'gallery-items.js'];
    $forbidden = ['Public URL copied.', 'Copy failed.', 'New Block', 'No blocks yet', 'Collapse child blocks', 'No items yet', 'New Item', 'Collapse item', 'Selected asset', 'Choose a file before uploading.', 'No overlay title', 'Selected image'];

    foreach ($files as $file) {
      $source = (string) file_get_contents($root.'/'.$file);
      foreach ($forbidden as $literal) {
        $this->assertStringNotContainsString($literal, $source, $file.' embeds an English UI fallback.');
      }
    }
  }

  public function test_every_admin_locale_has_every_english_key_and_placeholder(): void
  {
    $root = dirname(__DIR__, 2).'/resources/lang';
    $english = $this->flatten(require $root.'/en/admin.php');

    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $catalogue = $this->flatten(require $root.'/'.$locale.'/admin.php');

      $this->assertSame([], array_keys(array_diff_key($english, $catalogue)), $locale.' is missing English admin translation keys.');
      foreach ($english as $key => $source) {
        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', (string) $source, $sourcePlaceholders);
        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', (string) $catalogue[$key], $translatedPlaceholders);
        sort($sourcePlaceholders[0]);
        sort($translatedPlaceholders[0]);
        $this->assertSame($sourcePlaceholders[0], $translatedPlaceholders[0], $locale.'.'.$key.' does not preserve placeholders.');
      }
    }
  }

  /** @return array<string, mixed> */
  private function flatten(array $items, string $prefix = ''): array
  {
    $flattened = [];

    foreach ($items as $key => $value) {
      $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
      $flattened += is_array($value) ? $this->flatten($value, $path) : [$path => $value];
    }

    return $flattened;
  }
}
