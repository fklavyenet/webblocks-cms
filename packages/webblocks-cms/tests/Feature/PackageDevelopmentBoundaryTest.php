<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PackageDevelopmentBoundaryTest extends TestCase
{
  #[Test]
  public function upgrading_guide_warns_clone_users_before_the_package_only_transition(): void
  {
    $guide = (string) file_get_contents(dirname(__DIR__, 2).'/UPGRADING.md');

    $this->assertStringContainsString('1.36.1', $guide);
    $this->assertStringContainsString('does not perform the repository transition', $guide);
    $this->assertStringContainsString('Do not assume `git pull` across that transition is safe', $guide);
    $this->assertStringContainsString('`.env`', $guide);
    $this->assertStringContainsString('database', $guide);
    $this->assertStringContainsString('storage/uploads', $guide);
    $this->assertStringContainsString('plugins', $guide);
    $this->assertStringContainsString('project content', $guide);
    $this->assertStringContainsString('Publisher/System Updates', $guide);
  }

  #[Test]
  public function package_archive_excludes_development_only_files(): void
  {
    $attributes = file_get_contents(dirname(__DIR__, 2).'/.gitattributes');

    $this->assertIsString($attributes);

    foreach (['/.editorconfig', '/.github', '/.gitignore', '/CODE_OF_CONDUCT.md', '/CONTRIBUTING.md', '/DEVELOPMENT.md', '/SECURITY.md', '/composer.lock', '/coverage', '/phpunit.xml.dist', '/pint.json', '/scripts', '/tests', '/vendor'] as $path) {
      $this->assertStringContainsString($path.' export-ignore', $attributes);
    }
  }

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
