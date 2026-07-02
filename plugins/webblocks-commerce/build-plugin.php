<?php

$source = __DIR__;
$manifestPath = $source.DIRECTORY_SEPARATOR.'webblocks-plugin.json';

if (! is_file($manifestPath)) {
  fwrite(STDERR, "Missing webblocks-plugin.json\n");
  exit(1);
}

$manifest = json_decode((string) file_get_contents($manifestPath), true);
$version = is_array($manifest) ? (string) ($manifest['version'] ?? '0.1.0') : '0.1.0';
$output = $argv[1] ?? dirname(__DIR__, 2).'/storage/app/webblocks/plugin-artifacts/webblocks-commerce-'.$version.'.zip';

if (! is_dir(dirname($output))) {
  mkdir(dirname($output), 0755, true);
}

if (is_file($output)) {
  unlink($output);
}

$zip = new ZipArchive;

if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
  fwrite(STDERR, "Unable to create {$output}\n");
  exit(1);
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));

foreach ($files as $file) {
  if (! $file->isFile()) {
    continue;
  }

  $relative = str_replace($source.DIRECTORY_SEPARATOR, '', $file->getPathname());
  $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

  if ($relative === 'build-plugin.php' || str_contains($relative, '/.') || str_starts_with($relative, '.')) {
    continue;
  }

  $zip->addFile($file->getPathname(), $relative);
}

$zip->close();

fwrite(STDOUT, "Built {$output}\n");
