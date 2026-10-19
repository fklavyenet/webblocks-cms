<?php

declare(strict_types=1);

use WebBlocks\Cms\Tests\Support\NodeDependencyGuard;

require __DIR__.'/NodeDependencyGuard.php';

$root = dirname(__DIR__, 2);
if (count($argv) === 2 && str_starts_with($argv[1], '--root=')) {
  $root = substr($argv[1], strlen('--root='));
} elseif (count($argv) !== 1) {
  fwrite(STDERR, "Usage: php tests/Support/check-node-dependencies.php [--root=checkout]\n");
  exit(2);
}

try {
  $errors = NodeDependencyGuard::errors($root);
} catch (Throwable $exception) {
  fwrite(STDERR, $exception->getMessage().PHP_EOL);
  exit(2);
}
if ($errors !== []) {
  fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
  exit(1);
}

echo 'Node/npm dependency check passed.'.PHP_EOL;
