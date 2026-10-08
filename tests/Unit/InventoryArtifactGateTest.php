<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Tests\Support\InventoryReview;

class InventoryArtifactGateTest extends TestCase
{
  public function test_builder_checks_selected_git_tree_and_preserves_historical_builds(): void
  {
    $root = dirname(__DIR__, 2);
    $fixture = sys_get_temp_dir().'/cms-inventory-artifact-'.bin2hex(random_bytes(8));
    mkdir($fixture, 0775, true);
    try {
      foreach (InventoryReview::SOURCE_ROOTS as $directory) {
        mkdir($fixture.'/'.$directory, 0775, true);
        file_put_contents($fixture.'/'.$directory.'/fixture.txt', 'Synthetic source.');
      }
      mkdir($fixture.'/resources/contracts', 0775, true);
      mkdir($fixture.'/src/Support', 0775, true);
      mkdir($fixture.'/tests/Support', 0775, true);
      mkdir($fixture.'/scripts/release', 0775, true);
      file_put_contents($fixture.'/src/Support/WebBlocks.php', "<?php const VERSION = '99.1.0';\n");
      file_put_contents($fixture.'/composer.json', '{}');
      file_put_contents($fixture.'/LICENSE', 'Synthetic license.');
      file_put_contents($fixture.'/.gitattributes', "/tests export-ignore\n/scripts export-ignore\n/.gitattributes export-ignore\n");
      copy($root.'/'.InventoryReview::DOCUMENT, $fixture.'/'.InventoryReview::DOCUMENT);
      copy($root.'/scripts/release/build-package.sh', $fixture.'/scripts/release/build-package.sh');
      $this->executeCommand(['git', 'init', '-q'], $fixture);
      $this->executeCommand(['git', 'config', 'user.name', 'Fixture'], $fixture);
      $this->executeCommand(['git', 'config', 'user.email', 'fixture@example.test'], $fixture);
      $this->commit($fixture, 'Historical source');
      $historical = trim($this->executeCommand(['git', 'rev-parse', 'HEAD'], $fixture)[1]);

      foreach (['InventoryReview.php', 'check-inventory.php'] as $file) {
        copy($root.'/tests/Support/'.$file, $fixture.'/tests/Support/'.$file);
      }
      $this->record($fixture);
      $this->commit($fixture, 'Reviewed source');
      $reviewed = trim($this->executeCommand(['git', 'rev-parse', 'HEAD'], $fixture)[1]);

      // An unreviewed working copy must not affect a valid archived tree.
      file_put_contents($fixture.'/src/Changed.php', '<?php // new field');
      [$status, $output] = $this->executeCommand(['bash', 'scripts/release/build-package.sh', $fixture.'/reviewed.zip', $reviewed], $fixture);
      $this->assertSame(0, $status, $output);
      $zip = new \ZipArchive;
      $this->assertTrue($zip->open($fixture.'/reviewed.zip'));
      $this->assertNotFalse($zip->locateName(InventoryReview::RECORD));
      $this->assertFalse($zip->locateName('tests/Support/check-inventory.php'));
      $zip->close();

      // Commit stale runtime, then make the working-copy review current. The
      // artifact still has to reject the stale selected commit.
      $this->commit($fixture, 'Unreviewed runtime');
      $stale = trim($this->executeCommand(['git', 'rev-parse', 'HEAD'], $fixture)[1]);
      $this->record($fixture, 'Synthetic source has no real authoring behavior.');
      [$status, $output] = $this->executeCommand(['bash', 'scripts/release/build-package.sh', $fixture.'/stale.zip', $stale], $fixture);
      $this->assertNotSame(0, $status, $output);
      $this->assertStringContainsString('Runtime sources changed', $output);
      $this->assertFileDoesNotExist($fixture.'/stale.zip');

      [$status, $output] = $this->executeCommand(['bash', 'scripts/release/build-package.sh', $fixture.'/historical.zip', $historical], $fixture);
      $this->assertSame(0, $status, $output);
    } finally {
      $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($fixture, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
      foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
      }
      rmdir($fixture);
    }
  }

  private function record(string $fixture, ?string $noImpact = null): void
  {
    file_put_contents($fixture.'/'.InventoryReview::RECORD, json_encode(InventoryReview::record($fixture, 'Synthetic review.', $noImpact), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

  private function commit(string $fixture, string $message): void
  {
    $this->executeCommand(['git', 'add', 'src', 'config', 'routes', 'database', 'resources', 'public', 'stubs', 'composer.json', 'LICENSE', '.gitattributes', 'scripts', 'tests'], $fixture);
    [$status, $output] = $this->executeCommand(['git', '-c', 'core.hooksPath=/dev/null', '-c', 'commit.gpgsign=false', 'commit', '-qm', $message], $fixture);
    $this->assertSame(0, $status, $output);
  }

  private function executeCommand(array $command, string $directory): array
  {
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory);
    $this->assertIsResource($process);
    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $output];
  }
}
