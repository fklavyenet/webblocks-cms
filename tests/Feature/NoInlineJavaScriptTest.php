<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebBlocks\Cms\Tests\TestCase;

class NoInlineJavaScriptTest extends TestCase
{
  #[Test]
  public function blade_views_do_not_embed_executable_javascript(): void
  {
    $views = dirname(__DIR__, 2).'/resources/views';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views));

    foreach ($iterator as $file) {
      if (! $file instanceof SplFileInfo || ! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
      }

      $source = (string) file_get_contents($file->getPathname());
      $relative = str_replace($views.'/', '', $file->getPathname());

      $this->assertDoesNotMatchRegularExpression(
        '/<script\b(?![^>]*\bsrc\s*=)[^>]*>/is',
        $source,
        $relative.' contains an inline executable script.'
      );
      $this->assertDoesNotMatchRegularExpression(
        '/\son[a-z]+\s*=/i',
        $source,
        $relative.' contains an inline event handler.'
      );
    }
  }

  #[Test]
  public function each_extracted_runtime_is_a_package_asset_referenced_by_its_view(): void
  {
    $root = dirname(__DIR__, 2);
    $assets = [
      'backup-restore.js' => 'admin/system/backups/show.blade.php',
      'navigation-form.js' => 'admin/navigation/_form.blade.php',
      'site-export-page-picker.js' => 'admin/site-transfers/partials/export-modal.blade.php',
      'site-import.js' => 'admin/site-transfers/imports/show.blade.php',
      'system-update.js' => 'admin/system/updates.blade.php',
    ];

    foreach ($assets as $asset => $view) {
      $this->assertFileExists($root.'/public/cms/js/admin/'.$asset);
      $this->assertStringContainsString(
        'cms/js/admin/'.$asset,
        (string) file_get_contents($root.'/resources/views/'.$view)
      );
    }
  }

  #[Test]
  public function admin_views_do_not_embed_inline_styles(): void
  {
    $views = dirname(__DIR__, 2).'/resources/views/admin';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views));

    foreach ($iterator as $file) {
      if (! $file instanceof SplFileInfo || ! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
      }

      $source = (string) file_get_contents($file->getPathname());

      $this->assertDoesNotMatchRegularExpression(
        '/\sstyle\s*=/i',
        $source,
        str_replace($views.'/', '', $file->getPathname()).' contains an inline style attribute.'
      );
    }
  }
}
