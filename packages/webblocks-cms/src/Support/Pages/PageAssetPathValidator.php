<?php

namespace WebBlocks\Cms\Support\Pages;

use InvalidArgumentException;
use WebBlocks\Cms\Models\PageAsset;

class PageAssetPathValidator
{
  public function validate(string $type, mixed $path): ?string
  {
    $normalizedType = $this->normalizeType($type);
    $normalizedPath = $this->normalizePath((string) $path);

    if (! in_array($normalizedType, PageAsset::allowedTypes(), true)) {
      return 'Asset type must be css or js.';
    }

    if ($normalizedPath === '') {
      return 'Asset path is required.';
    }

    if (preg_match('/[[:cntrl:]<>"\']/', $normalizedPath) === 1) {
      return 'Asset path contains invalid characters.';
    }

    if (str_starts_with($normalizedPath, '//')) {
      return 'Asset path must use a local /site/... path.';
    }

    $lowerPath = strtolower($normalizedPath);

    foreach (['http://', 'https://', 'javascript:', 'data:'] as $disallowedPrefix) {
      if (str_starts_with($lowerPath, $disallowedPrefix)) {
        return 'Asset path must use a local /site/... path.';
      }
    }

    if (! str_starts_with($normalizedPath, '/site/')) {
      return 'Asset path must start with /site/.';
    }

    if (str_contains($normalizedPath, '\\') || str_contains($normalizedPath, '..')) {
      return 'Asset path cannot contain directory traversal or backslashes.';
    }

    if (str_contains($normalizedPath, '?') || str_contains($normalizedPath, '#')) {
      return 'Asset path cannot include query strings or fragments in V1.';
    }

    $requiredExtension = $normalizedType === PageAsset::TYPE_JS ? '.js' : '.css';

    if (! str_ends_with(strtolower($normalizedPath), $requiredExtension)) {
      return 'Asset path extension must match the selected asset type.';
    }

    $relativePath = ltrim($normalizedPath, '/');

    if ($relativePath === 'site' || ! str_starts_with($relativePath, 'site/')) {
      return 'Asset path must stay under /site/.';
    }

    foreach (explode('/', $relativePath) as $segment) {
      if ($segment === '' || $segment === '.' || $segment === '..') {
        return 'Asset path contains invalid path segments.';
      }
    }

    return null;
  }

  public function normalizeForStorage(string $type, mixed $path): string
  {
    $message = $this->validate($type, $path);

    if ($message !== null) {
      throw new InvalidArgumentException($message);
    }

    return $this->normalizePath((string) $path);
  }

  public function normalizeType(mixed $type): string
  {
    return strtolower(trim((string) $type));
  }

  public function relativePublicPath(string $path): string
  {
    $normalized = $this->normalizePath($path);

    if (! str_starts_with($normalized, '/site/')) {
      throw new InvalidArgumentException('Asset path must stay under /site/.');
    }

    foreach (explode('/', ltrim($normalized, '/')) as $segment) {
      if ($segment === '' || $segment === '.' || $segment === '..') {
        throw new InvalidArgumentException('Asset path contains invalid path segments.');
      }
    }

    return ltrim($normalized, '/');
  }

  private function normalizePath(string $path): string
  {
    $trimmed = trim($path);

    if ($trimmed === '') {
      return '';
    }

    $normalized = preg_replace('#/+#', '/', str_replace('\\', '/', $trimmed));

    return is_string($normalized) ? $normalized : $trimmed;
  }
}
