<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PackageDevelopmentBoundaryTest extends TestCase
{
  #[Test]
  public function package_development_files_do_not_depend_on_outer_application_paths(): void
  {
    $packageRoot = dirname(__DIR__, 2);
    $files = [
      $packageRoot.'/composer.json',
      $packageRoot.'/phpunit.xml.dist',
      $packageRoot.'/pint.json',
      $packageRoot.'/tests/TestCase.php',
    ];
    $forbidden = [
      'packages/webblocks-cms',
      'outer app/',
      'project/',
      'plugins/',
      'scripts/',
      'artisan',
      'bootstrap/app.php',
      'Tests\\TestCase',
    ];

    foreach ($files as $file) {
      $contents = (string) file_get_contents($file);

      foreach ($forbidden as $needle) {
        $this->assertStringNotContainsString($needle, $contents, $file.' must be package-local.');
      }
    }
  }
}
