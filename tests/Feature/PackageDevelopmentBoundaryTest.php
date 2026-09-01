<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PackageDevelopmentBoundaryTest extends TestCase
{
  #[Test]
  public function upgrading_guide_warns_clone_users_about_the_package_only_transition(): void
  {
    $guide = (string) file_get_contents(dirname(__DIR__, 2).'/UPGRADING.md');

    $this->assertStringContainsString('1.37.0', $guide);
    $this->assertStringContainsString('1.36.1', $guide);
    $this->assertStringContainsString('first release tagged directly from the package-only repository root', $guide);
    $this->assertStringContainsString('Do not assume `git pull` across this transition is safe', $guide);
    $this->assertStringContainsString('`.env`', $guide);
    $this->assertStringContainsString('database', $guide);
    $this->assertStringContainsString('storage/uploads', $guide);
    $this->assertStringContainsString('plugins', $guide);
    $this->assertStringContainsString('project content', $guide);
    $this->assertStringContainsString('Publisher/System Updates', $guide);
  }

  #[Test]
  public function upgrading_guide_documents_the_one_click_update_transition(): void
  {
    $guide = (string) file_get_contents(dirname(__DIR__, 2).'/UPGRADING.md');

    $this->assertStringContainsString('1.41.0', $guide);
    $this->assertStringContainsString('one-click flow', $guide);
    $this->assertStringContainsString('`restored` run status', $guide);
    $this->assertStringContainsString('system/updates/continue', $guide);
    $this->assertStringContainsString('Source-maintained apply mode retired', $guide);
    $this->assertStringContainsString('vendor/fklavyenet/webblocks-cms', $guide);
    $this->assertStringContainsString('server_backup_advisory', $guide);
    $this->assertStringContainsString('statuses.restored', $guide);
  }

  #[Test]
  public function package_archive_excludes_development_only_files(): void
  {
    $attributes = file_get_contents(dirname(__DIR__, 2).'/.gitattributes');

    $this->assertIsString($attributes);

    foreach (['/.editorconfig', '/.github', '/.gitignore', '/CODE_OF_CONDUCT.md', '/CONTRIBUTING.md', '/SECURITY.md', '/SUPPORT.md', '/composer.lock', '/coverage', '/phpunit.xml.dist', '/pint.json', '/scripts', '/tests', '/vendor'] as $path) {
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
      'top-level scripts/',
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

  #[Test]
  public function repository_has_the_exact_reviewed_top_level_tree(): void
  {
    $root = dirname(__DIR__, 2);
    $expected = ['.editorconfig', '.gitattributes', '.github', '.gitignore', '.publisher-client.json', 'CHANGELOG.md', 'CODE_OF_CONDUCT.md', 'CONTRIBUTING.md', 'LICENSE', 'README.md', 'SECURITY.md', 'SUPPORT.md', 'UPGRADING.md', 'composer.json', 'config', 'database', 'docs', 'phpunit.xml.dist', 'pint.json', 'public', 'resources', 'routes', 'scripts', 'src', 'stubs', 'tests'];
    // Exclude gitignored local/runtime artifacts so the assertion reflects the
    // committed tree, not whatever a developer's checkout happens to contain
    // (e.g. a stray storage/ from a local artisan run, coverage/, .DS_Store, or
    // the local-only AGENTS.md and .claude/). Keep this in sync with .gitignore.
    $ignored = ['.', '..', '.git', 'vendor', 'composer.lock', '.phpunit.cache', '.phpunit.result.cache', 'storage', 'coverage', '.DS_Store', 'AGENTS.md', '.claude'];
    $actual = array_values(array_filter(scandir($root) ?: [], fn (string $entry): bool => ! in_array($entry, $ignored, true)));

    sort($expected);
    sort($actual);
    $this->assertSame($expected, $actual);

    foreach (['packages', 'artisan', 'app', 'bootstrap', 'project', 'plugins', '.env', '.env.example'] as $forbidden) {
      $this->assertFileDoesNotExist($root.'/'.$forbidden);
    }
  }
}
