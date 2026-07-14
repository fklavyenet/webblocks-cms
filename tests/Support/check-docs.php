<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$required = ['README.md', 'LICENSE', 'CONTRIBUTING.md', 'SECURITY.md', 'SUPPORT.md', 'CODE_OF_CONDUCT.md', 'UPGRADING.md', 'CHANGELOG.md'];

// Documentation that the product depends on at runtime or contractually.
// docs/inventory.md is served by GET /webadmin/api/inventory and is the
// AI-facing authoring contract, so losing it must fail the docs check.
$requiredDocs = ['docs/inventory.md'];
$errors = [];

foreach ($required as $file) {
  if (! is_file($root.'/'.$file)) {
    $errors[] = 'Missing required public file: '.$file;
  }
}

foreach ($requiredDocs as $file) {
  if (! is_file($root.'/'.$file)) {
    $errors[] = 'Missing required documentation file: '.$file;
  }
}

$markdownFiles = array_merge($required, glob($root.'/docs/*.md') ?: []);

foreach ($markdownFiles as $file) {
  $path = str_starts_with($file, '/') ? $file : $root.'/'.$file;

  if (! is_file($path)) {
    continue;
  }

  $contents = (string) file_get_contents($path);

  if (str_contains($contents, '/Users/') || str_contains($contents, 'package-only-phase')) {
    $errors[] = 'Private workspace path in '.str_replace($root.'/', '', $path);
  }

  preg_match_all('/\[[^\]]*\]\(([^)]+)\)/', $contents, $matches);

  foreach ($matches[1] as $target) {
    $target = preg_replace('/#.*$/', '', trim($target, '<>'));

    if ($target === '' || str_contains($target, '://') || str_starts_with($target, 'mailto:')) {
      continue;
    }

    if (! file_exists(dirname($path).'/'.$target)) {
      $errors[] = 'Broken relative link in '.str_replace($root.'/', '', $path).': '.$target;
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

if ($errors !== []) {
  fwrite(STDERR, implode(PHP_EOL, array_unique($errors)).PHP_EOL);
  exit(1);
}

echo 'Documentation checks passed.'.PHP_EOL;
