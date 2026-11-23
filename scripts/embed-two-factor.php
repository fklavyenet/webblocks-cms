<?php

declare(strict_types=1);

use WebBlocks\Cms\Support\Auth\Module\TwoFactor;

$source = dirname(__DIR__).'/src/Support/Auth/Module';
require $source.'/TwoFactor.php';

[$script, $version, $namespace, $output] = array_pad($argv, 4, '');
$sourceNamespace = 'WebBlocks\\Cms\\Support\\Auth\\Module';

try {
  if (count($argv) !== 4 || $version !== TwoFactor::VERSION
    || preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)+$/D', $namespace) !== 1
    || $output === '' || ! is_dir(dirname($output))) {
    throw new RuntimeException('Usage: php scripts/embed-two-factor.php '.TwoFactor::VERSION." 'Product\\Support\\TwoFactor' NEW_DIRECTORY");
  }

  $sourceFiles = [];
  $files = [];
  foreach (glob($source.'/*.php') as $path) {
    $name = basename($path);
    $bytes = file_get_contents($path);
    if ($bytes === false || ! str_contains($bytes, 'namespace '.$sourceNamespace.';')) {
      throw new RuntimeException('Invalid module source file: '.$name);
    }
    $sourceFiles[$name] = hash('sha256', $bytes);
    $files[$name] = str_replace($sourceNamespace, $namespace, $bytes);
  }
  $license = file_get_contents(dirname(__DIR__).'/LICENSE');
  if ($license === false) {
    throw new RuntimeException('The module license is missing.');
  }
  $files['LICENSE'] = $license;
  $manifest = [
    'schema' => 1,
    'source_package' => 'fklavyenet/webblocks-cms',
    'module' => 'two-factor',
    'name' => TwoFactor::NAME,
    'version' => $version,
    'namespace' => $namespace,
    'source_files' => $sourceFiles,
    'files' => array_map(static fn (string $bytes): string => hash('sha256', $bytes), $files),
  ];
  $files['module.json'] = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
  if (! @mkdir($output)) {
    throw new RuntimeException('The export needs a new directory: '.$output);
  }
  foreach ($files as $name => $bytes) {
    if (file_put_contents($output.'/'.$name, $bytes) !== strlen($bytes)) {
      throw new RuntimeException('Could not write module file: '.$name);
    }
  }
  echo 'Exported '.TwoFactor::NAME.' '.$version.' to '.$output.PHP_EOL;
} catch (Throwable $exception) {
  fwrite(STDERR, $exception->getMessage().PHP_EOL);
  exit(1);
}
