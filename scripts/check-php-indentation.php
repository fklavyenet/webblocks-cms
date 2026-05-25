<?php

$root = dirname(__DIR__);

$includeRoots = [
  'app',
  'bootstrap',
  'config',
  'database',
  'packages',
  'project',
  'resources',
  'routes',
  'scripts',
  'tests',
];

$excludedPrefixes = [
  'vendor/',
  'storage/',
  'bootstrap/cache/',
  'node_modules/',
  'public/',
  '.git/',
];

$allowedExtensions = [
  '.php',
  '.blade.php',
];

$targetPaths = array_slice($argv, 1);

if ($targetPaths === []) {
  $targetPaths = $includeRoots;
}

$files = [];

foreach ($targetPaths as $targetPath) {
  $normalizedTarget = normalizePath($targetPath);

  if ($normalizedTarget === '' || isExcluded($normalizedTarget, $excludedPrefixes)) {
    continue;
  }

  if (! isWithinIncludeRoots($normalizedTarget, $includeRoots)) {
    continue;
  }

  $absoluteTarget = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalizedTarget);

  if (is_file($absoluteTarget) && isPhpLikeFile($normalizedTarget, $allowedExtensions)) {
    $files[$normalizedTarget] = $normalizedTarget;

    continue;
  }

  if (! is_dir($absoluteTarget)) {
    continue;
  }

  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($absoluteTarget, FilesystemIterator::SKIP_DOTS)
  );

  foreach ($iterator as $file) {
    if (! $file->isFile()) {
      continue;
    }

    $absolutePath = $file->getPathname();
    $relativePath = normalizePath(substr($absolutePath, strlen($root) + 1));

    if (isExcluded($relativePath, $excludedPrefixes) || ! isPhpLikeFile($relativePath, $allowedExtensions)) {
      continue;
    }

    $files[$relativePath] = $relativePath;
  }
}

ksort($files);

$issues = [];

foreach ($files as $relativePath) {
  $absolutePath = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
  $lines = @file($absolutePath);

  if ($lines === false) {
    $issues[] = [
      'path' => $relativePath,
      'line' => 0,
      'message' => 'Unable to read file.',
    ];

    continue;
  }

    $braceDepth = 0;
    $bracketDepth = 0;
    $parenDepth = 0;

    foreach ($lines as $index => $line) {
        $lineNumber = $index + 1;
        $trimmed = ltrim($line, " \t");

        if ($trimmed === '' || $trimmed === "\n" || $trimmed === "\r\n") {
            $braceDepth += braceDelta($line);
            $bracketDepth += bracketDelta($line);
            $parenDepth += parenDelta($line);

            continue;
        }

    preg_match('/^[ \t]*/', $line, $matches);
    $indent = $matches[0] ?? '';

    if (str_contains($indent, "\t")) {
      $issues[] = [
        'path' => $relativePath,
        'line' => $lineNumber,
        'message' => 'Leading tabs are not allowed; use 2-space indentation.',
      ];
    }

        if (
            $braceDepth === 1
            && $bracketDepth === 0
            && $parenDepth === 0
            && $indent === '    '
            && isLikelyFirstLevelPhpDeclaration($trimmed)
        ) {
            $issues[] = [
                'path' => $relativePath,
                'line' => $lineNumber,
        'message' => 'Likely 4-space first-level indentation detected; expected 2 spaces.',
      ];
    }

        $braceDepth += braceDelta($line);
        $bracketDepth += bracketDelta($line);
        $parenDepth += parenDelta($line);
    }
}

if ($issues !== []) {
  fwrite(STDERR, "PHP indentation guard found issues:\n");

  foreach ($issues as $issue) {
    $location = $issue['line'] > 0
      ? $issue['path'].':'.$issue['line']
      : $issue['path'];

    fwrite(STDERR, '- '.$location.' '.$issue['message']."\n");
  }

  exit(1);
}

if ($files === []) {
  fwrite(STDOUT, "PHP indentation guard passed (no target PHP files found).\n");

  exit(0);
}

fwrite(STDOUT, 'PHP indentation guard passed for '.count($files)." file(s).\n");

function normalizePath(string $path): string
{
  $normalized = str_replace('\\', '/', trim($path));

  while (str_starts_with($normalized, './')) {
    $normalized = substr($normalized, 2);
  }

  return ltrim($normalized, '/');
}

function isWithinIncludeRoots(string $path, array $includeRoots): bool
{
  foreach ($includeRoots as $includeRoot) {
    if ($path === $includeRoot || str_starts_with($path, $includeRoot.'/')) {
      return true;
    }
  }

  return false;
}

function isExcluded(string $path, array $excludedPrefixes): bool
{
  foreach ($excludedPrefixes as $prefix) {
    if (str_starts_with($path, $prefix)) {
      return true;
    }
  }

  return false;
}

function isPhpLikeFile(string $path, array $allowedExtensions): bool
{
  foreach ($allowedExtensions as $extension) {
    if (str_ends_with($path, $extension)) {
      return true;
    }
  }

  return false;
}

function isLikelyFirstLevelPhpDeclaration(string $trimmedLine): bool
{
    return preg_match('/^(#\[|abstract\s+|final\s+|public\s+|protected\s+|private\s+|static\s+|use\s+|const\s+|function\s+)/', $trimmedLine) === 1;
}

function braceDelta(string $line): int
{
    return substr_count($line, '{') - substr_count($line, '}');
}

function bracketDelta(string $line): int
{
    return substr_count($line, '[') - substr_count($line, ']');
}

function parenDelta(string $line): int
{
    return substr_count($line, '(') - substr_count($line, ')');
}
