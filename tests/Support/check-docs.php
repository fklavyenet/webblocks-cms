<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$required = ['README.md', 'LICENSE', 'CONTRIBUTING.md', 'SECURITY.md', 'SUPPORT.md', 'CODE_OF_CONDUCT.md', 'UPGRADING.md', 'CHANGELOG.md'];

// Documentation that the product depends on at runtime or contractually.
// docs/inventory.md is served by GET /webadmin/api/inventory and is the
// AI-facing authoring contract, so losing it must fail the docs check.
$requiredDocs = ['docs/inventory.md', 'docs/personal-ai-tokens.md'];
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

$docsIterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($root.'/docs', FilesystemIterator::SKIP_DOTS),
);
$documentationFiles = [];

foreach ($docsIterator as $file) {
  if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
    $documentationFiles[] = $file->getPathname();
  }
}

$markdownFiles = array_merge($required, $documentationFiles);
$syncSourceIds = [];
$syncPaths = [];
$guideSlugs = [];
$publicDocumentationPaths = [];

foreach ($markdownFiles as $file) {
  $path = str_starts_with($file, '/') ? $file : $root.'/'.$file;

  if (! is_file($path)) {
    continue;
  }

  $contents = (string) file_get_contents($path);
  $frontMatter = '';
  if (preg_match('/\A---\R(.*?)\R---(?:\R|\z)/s', $contents, $frontMatterMatch) === 1) {
    $frontMatter = $frontMatterMatch[1];
  }

  if (preg_match('/^cms_sync:\s*true\s*$/m', $frontMatter) === 1) {
    foreach (['cms_site', 'cms_locale', 'cms_path', 'cms_title', 'cms_layout', 'cms_source_id'] as $field) {
      if (preg_match('/^'.preg_quote($field, '/').':\s*\S.*$/m', $frontMatter) !== 1) {
        $errors[] = 'CMS-synced documentation is missing '.$field.': '.str_replace($root.'/', '', $path);
      }
    }

    if (preg_match('/^cms_source_id:\s*(.+)$/m', $frontMatter, $sourceIdMatch) === 1) {
      $sourceId = trim($sourceIdMatch[1]);
      if (isset($syncSourceIds[$sourceId])) {
        $errors[] = 'Duplicate cms_source_id '.$sourceId.' in '.str_replace($root.'/', '', $path).' and '.$syncSourceIds[$sourceId];
      }
      $syncSourceIds[$sourceId] = str_replace($root.'/', '', $path);
    }

    if (preg_match('/^cms_path:\s*(.+)$/m', $frontMatter, $cmsPathMatch) === 1) {
      $cmsPath = trim($cmsPathMatch[1]);
      if (! str_starts_with($cmsPath, '/docs/')) {
        $errors[] = 'CMS-synced documentation path must start with /docs/: '.str_replace($root.'/', '', $path);
      }
      if (isset($syncPaths[$cmsPath])) {
        $errors[] = 'Duplicate cms_path '.$cmsPath.' in '.str_replace($root.'/', '', $path).' and '.$syncPaths[$cmsPath];
      }
      $syncPaths[$cmsPath] = str_replace($root.'/', '', $path);
      $publicDocumentationPaths[$cmsPath] = str_replace($root.'/', '', $path);
    }
  }

  if (preg_match('/^guide:\s*true\s*$/m', $frontMatter) === 1) {
    $guideFields = ['guide_slug', 'cms_site', 'cms_locale', 'cms_path', 'cms_title', 'cms_layout'];
    if (basename($path) !== 'index.md') {
      $guideFields = array_merge($guideFields, ['guide_series', 'guide_order', 'card_description']);
    }

    foreach ($guideFields as $field) {
      if (preg_match('/^'.preg_quote($field, '/').':\s*\S.*$/m', $frontMatter) !== 1) {
        $errors[] = 'Guide is missing '.$field.': '.str_replace($root.'/', '', $path);
      }
    }

    if (preg_match('/^guide_slug:\s*(.+)$/m', $frontMatter, $guideSlugMatch) === 1) {
      $guideSlug = trim($guideSlugMatch[1]);
      if (isset($guideSlugs[$guideSlug])) {
        $errors[] = 'Duplicate guide_slug '.$guideSlug.' in '.str_replace($root.'/', '', $path).' and '.$guideSlugs[$guideSlug];
      }
      $guideSlugs[$guideSlug] = str_replace($root.'/', '', $path);
    }

    if (preg_match('/^cms_path:\s*(.+)$/m', $frontMatter, $guidePathMatch) === 1) {
      $guidePath = trim($guidePathMatch[1]);
      if (! str_starts_with($guidePath, '/guides')) {
        $errors[] = 'Guide cms_path must start with /guides: '.str_replace($root.'/', '', $path);
      }
      if (isset($publicDocumentationPaths[$guidePath])) {
        $errors[] = 'Duplicate public documentation path '.$guidePath.' in '.str_replace($root.'/', '', $path).' and '.$publicDocumentationPaths[$guidePath];
      }
      $publicDocumentationPaths[$guidePath] = str_replace($root.'/', '', $path);
    }
  }

  if (str_contains($contents, '/Users/') || str_contains($contents, 'package-only-phase')) {
    $errors[] = 'Private workspace path in '.str_replace($root.'/', '', $path);
  }

  /*
   * Links inside a fenced code block are examples, not references.
   *
   * The private-path check above deliberately still reads the whole file — a
   * `/Users/...` path is a leak whether or not it is in an example — but a document
   * that shows what a page looks like will contain link syntax pointing at things
   * that do not exist, and flagging those means either a permanently red check or a
   * correct document mangled to satisfy it. Both are worse than not looking.
   *
   * `docs/user-guides-plan.md` is the case that surfaced this: a plan describing the
   * shape of a user guide, with a `![...](screenshot)` placeholder and a site-path
   * link inside a ```markdown block.
   */
  $prose = preg_replace('/^```.*?^```/ms', '', $contents) ?? $contents;

  preg_match_all('/\[[^\]]*\]\(([^)]+)\)/', $prose, $matches);

  foreach ($matches[1] as $target) {
    $target = preg_replace('/#.*$/', '', trim($target, '<>'));

    if ($target === '' || str_contains($target, '://') || str_starts_with($target, 'mailto:') || str_starts_with($target, '/')) {
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
