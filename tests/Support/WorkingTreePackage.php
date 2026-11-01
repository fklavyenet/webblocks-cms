<?php

namespace WebBlocks\Cms\Tests\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

/** Reuse immutable Git objects; never writes the checkout's index or object store. */
final class WorkingTreePackage
{
  public static function build(string $repository, string $scratch, string $archive): void
  {
    $objects = self::run(['git', 'rev-parse', '--path-format=absolute', '--git-path', 'objects'], $repository);
    $directory = $scratch.'/git-fixture';
    if (! mkdir($directory.'/objects', 0777, true) && ! is_dir($directory.'/objects')) {
      throw new RuntimeException('Unable to create the isolated package fixture.');
    }
    $environment = [
      'GIT_INDEX_FILE' => $directory.'/index',
      'GIT_OBJECT_DIRECTORY' => $directory.'/objects',
      'GIT_ALTERNATE_OBJECT_DIRECTORIES' => $objects,
    ];
    self::run(['git', 'read-tree', 'HEAD'], $repository, $environment);
    // Stage tracked edits/deletions only into the disposable index. The reviewed
    // runtime manifest also includes new files that are not yet Git-tracked.
    self::run(['git', 'add', '-u', '--', '.'], $repository, $environment);
    $review = json_decode(file_get_contents($repository.'/resources/contracts/inventory-review.json'), true, flags: JSON_THROW_ON_ERROR);
    $paths = array_keys($review['sources']);
    foreach (array_chunk($paths, 200) as $chunk) {
      $chunk = array_values(array_filter($chunk, fn ($path) => is_file($repository.'/'.$path)));
      if ($chunk !== []) {
        self::run(['git', 'add', '--', ...$chunk], $repository, $environment);
      }
    }
    $tree = self::run(['git', 'write-tree'], $repository, $environment);
    self::run(['bash', $repository.'/scripts/release/build-package.sh', $archive, $tree], $repository, $environment);
  }

  private static function run(array $command, string $directory, array $environment = []): string
  {
    $process = new Process($command, $directory, $environment, timeout: 120);
    $process->run();
    if (! $process->isSuccessful()) {
      throw new RuntimeException('Package fixture command failed: '.$process->getErrorOutput().$process->getOutput());
    }

    return trim($process->getOutput());
  }
}
