<?php

declare(strict_types=1);

use WebBlocks\Cms\Tests\Support\InventoryReview;

require_once __DIR__.'/InventoryReview.php';
$options = getopt('', ['reviewed', 'note:', 'no-authoring-impact:']);
$root = dirname(__DIR__, 2);

try {
  if (! array_key_exists('reviewed', $options) || ! isset($options['note'])) {
    throw new RuntimeException('Review the changed runtime sources and inventory prose first. Usage: composer inventory:review -- --reviewed --note="review summary" [--no-authoring-impact="reason"]');
  }
  if (! is_file($root.'/vendor/autoload.php')) {
    throw new RuntimeException('Install development dependencies before recording the mechanical surface.');
  }
  require_once $root.'/vendor/autoload.php';
  $record = InventoryReview::record($root, $options['note'], $options['no-authoring-impact'] ?? null);
  $target = $root.'/'.InventoryReview::RECORD;
  $temporary = tempnam(dirname($target), '.inventory-review-');
  try {
    $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    if ($temporary === false || file_put_contents($temporary, $json) !== strlen($json) || ! rename($temporary, $target)) {
      throw new RuntimeException('Cannot save the inventory review atomically.');
    }
  } finally {
    if (is_string($temporary) && is_file($temporary)) {
      unlink($temporary);
    }
  }
  echo 'Inventory review recorded. Refresh docs with its inventory-snapshot tool; no publication performed.'.PHP_EOL;
} catch (Throwable $exception) {
  fwrite(STDERR, $exception->getMessage().PHP_EOL);
  exit(1);
}
