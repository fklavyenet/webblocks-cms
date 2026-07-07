<?php

namespace Tests\Feature\Admin;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class WebBlocksIconManifestUsageTest extends TestCase
{
  #[Test]
  public function static_cms_icon_classes_match_the_pinned_webblocks_ui_manifest(): void
  {
    $unknown = [];

    foreach ($this->scanRoots() as $root) {
      if (! is_dir($root)) {
        continue;
      }

      $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
      );

      foreach ($files as $file) {
        if (! $file->isFile()) {
          continue;
        }

        $path = $file->getPathname();

        if (str_contains($path, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)
            || str_contains($path, DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR)) {
          continue;
        }

        $contents = file_get_contents($path);

        if ($contents === false || ! preg_match_all('/wb-icon-[a-z0-9-]+/', $contents, $matches, PREG_OFFSET_CAPTURE)) {
          continue;
        }

        foreach ($matches[0] as [$class, $offset]) {
          if ($this->isNonManifestUtilityClass($class) || in_array($class, $this->pinnedManifestIconClasses(), true)) {
            continue;
          }

          $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
          $unknown[$class][] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path).':'.$line;
        }
      }
    }

    ksort($unknown);

    $this->assertSame([], $unknown, 'Unknown wb-icon-* classes should be replaced with pinned WebBlocks UI manifest classes.');
  }

  private function scanRoots(): array
  {
    return [
      base_path('packages/webblocks-cms'),
      base_path('public/cms'),
      base_path('resources/views'),
      base_path('database'),
      base_path('config'),
      base_path('routes'),
      base_path('tests'),
      base_path('docs'),
    ];
  }

  private function isNonManifestUtilityClass(string $class): bool
  {
    return (bool) preg_match('/^(wb-icon-(xl|2xl)|wb-icon-tone(?:-[a-z0-9-]*)?|wb-icon-(hidden-icon|legacy-icon|marketing-only))$/', $class);
  }

  private function pinnedManifestIconClasses(): array
  {
    return [
      'wb-icon-ban',
      'wb-icon-book',
      'wb-icon-book-open',
      'wb-icon-box',
      'wb-icon-check',
      'wb-icon-chevron-down',
      'wb-icon-chevron-left',
      'wb-icon-chevron-right',
      'wb-icon-chevron-up',
      'wb-icon-circle-dot',
      'wb-icon-circle-help',
      'wb-icon-cloud',
      'wb-icon-cookie',
      'wb-icon-copy',
      'wb-icon-download',
      'wb-icon-external-link',
      'wb-icon-eye',
      'wb-icon-eye-off',
      'wb-icon-file',
      'wb-icon-file-archive',
      'wb-icon-file-text',
      'wb-icon-folder',
      'wb-icon-globe',
      'wb-icon-grip-vertical',
      'wb-icon-history',
      'wb-icon-home',
      'wb-icon-image',
      'wb-icon-images',
      'wb-icon-layers',
      'wb-icon-layout',
      'wb-icon-layout-dashboard',
      'wb-icon-layout-grid',
      'wb-icon-list',
      'wb-icon-mail',
      'wb-icon-megaphone',
      'wb-icon-menu',
      'wb-icon-minus',
      'wb-icon-moon',
      'wb-icon-package',
      'wb-icon-palette',
      'wb-icon-panel-left',
      'wb-icon-panel-right',
      'wb-icon-pause',
      'wb-icon-pen-tool',
      'wb-icon-pencil',
      'wb-icon-play',
      'wb-icon-plug',
      'wb-icon-plus',
      'wb-icon-receipt',
      'wb-icon-rocket',
      'wb-icon-rotate-cw',
      'wb-icon-route',
      'wb-icon-search',
      'wb-icon-settings',
      'wb-icon-shield-check',
      'wb-icon-sparkles',
      'wb-icon-star',
      'wb-icon-sun',
      'wb-icon-sun-moon',
      'wb-icon-trash',
      'wb-icon-triangle-alert',
      'wb-icon-upload',
      'wb-icon-video',
      'wb-icon-x',
    ];
  }
}
