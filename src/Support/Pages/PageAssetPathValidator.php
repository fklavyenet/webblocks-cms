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
      return __('webblocks-cms::admin-feedback.page_assets.invalid_type');
    }

    if ($normalizedPath === '') {
      return __('webblocks-cms::admin-feedback.page_assets.path_required');
    }

    if (preg_match('/[[:cntrl:]<>"\']/', $normalizedPath) === 1) {
      return __('webblocks-cms::admin-feedback.page_assets.path_characters');
    }

    if (str_starts_with($normalizedPath, '//')) {
      return __('webblocks-cms::admin-feedback.page_assets.path_local');
    }

    $lowerPath = strtolower($normalizedPath);

    foreach (['http://', 'https://', 'javascript:', 'data:'] as $disallowedPrefix) {
      if (str_starts_with($lowerPath, $disallowedPrefix)) {
        return __('webblocks-cms::admin-feedback.page_assets.path_local');
      }
    }

    if (! str_starts_with($normalizedPath, '/site/')) {
      return __('webblocks-cms::admin-feedback.page_assets.path_prefix');
    }

    if (str_contains($normalizedPath, '\\') || str_contains($normalizedPath, '..')) {
      return __('webblocks-cms::admin-feedback.page_assets.path_traversal');
    }

    if (str_contains($normalizedPath, '?') || str_contains($normalizedPath, '#')) {
      return __('webblocks-cms::admin-feedback.page_assets.path_query');
    }

    $requiredExtension = $normalizedType === PageAsset::TYPE_JS ? '.js' : '.css';

    if (! str_ends_with(strtolower($normalizedPath), $requiredExtension)) {
      return __('webblocks-cms::admin-feedback.page_assets.path_extension');
    }

    $relativePath = ltrim($normalizedPath, '/');

    if ($relativePath === 'site' || ! str_starts_with($relativePath, 'site/')) {
      return __('webblocks-cms::admin-feedback.page_assets.path_root');
    }

    foreach (explode('/', $relativePath) as $segment) {
      if ($segment === '' || $segment === '.' || $segment === '..') {
        return __('webblocks-cms::admin-feedback.page_assets.path_segments');
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

  public function assetData(string $type, mixed $path, array $input, ?PageAsset $existing = null): array
  {
    $isJs = $type === PageAsset::TYPE_JS;
    $flag = fn (string $key, bool $default): bool => filter_var(
      array_key_exists($key, $input) ? $input[$key] : ($existing?->{$key} ?? $default),
      FILTER_VALIDATE_BOOL,
    );

    return [
      'type' => $type,
      'path' => $this->normalizeForStorage($type, $path),
      'load_position' => PageAsset::defaultLoadPositionFor($type),
      'sort_order' => max((int) (array_key_exists('sort_order', $input) ? $input['sort_order'] : ($existing?->sort_order ?? 0)), 0),
      'is_enabled' => $flag('is_enabled', true),
      'is_defer' => $isJs && $flag('is_defer', true),
      'is_async' => $isJs && $flag('is_async', false),
      'is_module' => $isJs && $flag('is_module', false),
    ];
  }

  public function relativePublicPath(string $path): string
  {
    $normalized = $this->normalizePath($path);

    if (! str_starts_with($normalized, '/site/')) {
      throw new InvalidArgumentException(__('webblocks-cms::admin-feedback.page_assets.path_root'));
    }

    foreach (explode('/', ltrim($normalized, '/')) as $segment) {
      if ($segment === '' || $segment === '.' || $segment === '..') {
        throw new InvalidArgumentException(__('webblocks-cms::admin-feedback.page_assets.path_segments'));
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
