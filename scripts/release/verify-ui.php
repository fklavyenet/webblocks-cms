<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$versionSource = (string) file_get_contents($root.'/src/Support/WebBlocks.php');

if (preg_match("/UI_VERSION = '([^']+)'/", $versionSource, $versionMatch) !== 1) {
  fwrite(STDERR, "[webblocks-verify-ui] Unable to read UI_VERSION.\n");
  exit(1);
}

$version = $versionMatch[1];
$runtimeDirectory = $root.'/public/cms/webblocks-ui/'.$version;
$manifestPath = $runtimeDirectory.'/manifest.json';
$manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : null;

if (! is_array($manifest) || ($manifest['product'] ?? null) !== 'webblocks-ui' || ($manifest['version'] ?? null) !== $version) {
  fwrite(STDERR, "[webblocks-verify-ui] Missing or invalid manifest for {$version}. Run composer ui:vendor.\n");
  exit(1);
}

$requiredFiles = ['webblocks-ui.css', 'webblocks-icons.css', 'webblocks-ui.js', 'webblocks-icons.json', 'LICENSE.txt'];

foreach ($requiredFiles as $file) {
  $path = $runtimeDirectory.'/'.$file;
  $metadata = $manifest['artifacts'][$file] ?? null;

  if (! is_file($path) || ! is_array($metadata)) {
    fwrite(STDERR, "[webblocks-verify-ui] Missing runtime artifact or manifest entry: {$file}.\n");
    exit(1);
  }

  $actualChecksum = hash_file('sha256', $path);
  $actualBytes = filesize($path);

  if (! hash_equals((string) ($metadata['sha256'] ?? ''), $actualChecksum) || (int) ($metadata['bytes'] ?? -1) !== $actualBytes) {
    fwrite(STDERR, "[webblocks-verify-ui] Checksum or size mismatch: {$file}.\n");
    exit(1);
  }
}

foreach (['webblocks-ui.css', 'webblocks-icons.css'] as $stylesheet) {
  $css = (string) file_get_contents($runtimeDirectory.'/'.$stylesheet);
  preg_match_all('/url\(\s*(["\']?)(.*?)\1\s*\)/i', $css, $matches);

  foreach ($matches[2] ?? [] as $reference) {
    $reference = trim((string) $reference);

    if ($reference !== '' && ! str_starts_with($reference, 'data:') && ! str_starts_with($reference, '#')) {
      fwrite(STDERR, "[webblocks-verify-ui] {$stylesheet} contains an unvendored URL reference: {$reference}.\n");
      exit(1);
    }
  }
}

$catalogManifest = $root.'/database/content/icons/webblocks-ui-'.$version.'.json';

if (! is_file($catalogManifest) || ! hash_equals(hash_file('sha256', $runtimeDirectory.'/webblocks-icons.json'), hash_file('sha256', $catalogManifest))) {
  fwrite(STDERR, "[webblocks-verify-ui] Bundled icon catalog does not match the local UI runtime.\n");
  exit(1);
}

foreach ([
  $root.'/src/Support/WebBlocks.php',
  $root.'/resources/views/layouts/admin.blade.php',
  $root.'/resources/views/layouts/guest.blade.php',
  $root.'/resources/views/layouts/public.blade.php',
  $root.'/resources/views/errors/404.blade.php',
] as $runtimeSource) {
  $contents = (string) file_get_contents($runtimeSource);

  if (str_contains($contents, 'cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui')) {
    fwrite(STDERR, "[webblocks-verify-ui] Runtime source still references the WebBlocks UI CDN: {$runtimeSource}.\n");
    exit(1);
  }
}

fwrite(STDOUT, "[webblocks-verify-ui] {$version} local runtime checksums and references are valid.\n");
