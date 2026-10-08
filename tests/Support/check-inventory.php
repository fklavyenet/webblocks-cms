<?php

declare(strict_types=1);

use WebBlocks\Cms\Tests\Support\InventoryReview;

require_once __DIR__.'/InventoryReview.php';
$options = getopt('', ['root:']);
$root = realpath($options['root'] ?? dirname(__DIR__, 2));

try {
  if ($root === false) {
    throw new RuntimeException('Product root does not exist.');
  }
  $errors = InventoryReview::errors($root);
  if ($errors !== []) {
    throw new RuntimeException(implode(PHP_EOL, $errors).PHP_EOL.'Review the contract, record the review, and refresh the documentation snapshot.');
  }
  echo 'Inventory source review is current.'.PHP_EOL;
} catch (Throwable $exception) {
  fwrite(STDERR, $exception->getMessage().PHP_EOL);
  exit(1);
}
