<?php

declare(strict_types=1);
use WebBlocks\Cms\Tests\Support\InventoryReview;

$root = dirname(__DIR__, 2);
$required = ['README.md', 'LICENSE', 'CONTRIBUTING.md', 'SECURITY.md', 'SUPPORT.md', 'CODE_OF_CONDUCT.md', 'UPGRADING.md', 'CHANGELOG.md'];

$errors = [];

foreach (array_merge($required, ['resources/contracts/inventory.md']) as $file) {
  if (! is_file($root.'/'.$file)) {
    $errors[] = 'Missing required product file: '.$file;
  }
}

if (is_dir($root.'/docs')) {
  $errors[] = 'User documentation belongs to the independent webblocks-cms-docs repository.';
}

foreach ($required as $file) {
  if (! is_file($root.'/'.$file)) {
    continue;
  }
  $contents = (string) file_get_contents($root.'/'.$file);
  if (str_contains($contents, '/Users/') || str_contains($contents, 'package-only-phase')) {
    $errors[] = 'Private workspace path in '.$file;
  }
  $prose = preg_replace('/^```.*?^```/ms', '', $contents) ?? $contents;
  preg_match_all('/\[[^\]]*\]\(([^)]+)\)/', $prose, $matches);
  foreach ($matches[1] as $target) {
    $target = preg_replace('/#.*$/', '', trim($target, '<>'));
    if ($target === '' || str_contains($target, '://') || str_starts_with($target, 'mailto:') || str_starts_with($target, '/')) {
      continue;
    }
    if (! file_exists($root.'/'.$target)) {
      $errors[] = 'Broken relative link in '.$file.': '.$target;
    }
  }
}

$readme = (string) file_get_contents($root.'/README.md');
$composer = json_decode((string) file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
$scripts = array_keys($composer['scripts'] ?? []);

foreach (['fklavyenet/webblocks-cms', 'webblocks:install', 'webblocks-cms-config', 'webblocks-cms-assets', 'webblocks-cms-stubs'] as $needle) {
  if (! str_contains($readme, $needle)) {
    $errors[] = 'README is missing '.$needle;
  }
}

foreach (['format:test', 'test'] as $script) {
  if (! in_array($script, $scripts, true)) {
    $errors[] = 'Documented Composer script is missing: '.$script;
  }
}

if (preg_match('/git clone.*\n.*php artisan serve/s', $readme) === 1) {
  $errors[] = 'README presents a cloned package as a runnable application.';
}

require_once __DIR__.'/InventoryReview.php';
try {
  $errors = array_merge($errors, InventoryReview::errors($root));
} catch (Throwable $exception) {
  $errors[] = $exception->getMessage();
}

if ($errors !== []) {
  fwrite(STDERR, implode(PHP_EOL, array_unique($errors)).PHP_EOL);
  exit(1);
}

echo 'Product metadata and runtime contract checks passed.'.PHP_EOL;
