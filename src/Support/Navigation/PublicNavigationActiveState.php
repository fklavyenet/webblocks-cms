<?php

namespace WebBlocks\Cms\Support\Navigation;

use Illuminate\Http\Request;

class PublicNavigationActiveState
{
  public function __construct(private readonly Request $request) {}

  public function matches(
    ?string $href,
    string $mode = 'path',
    ?int $linkedPageId = null,
    ?int $currentPageId = null,
  ): bool {
    if ($mode === 'off' || $href === null) {
      return false;
    }

    if ($linkedPageId !== null && $currentPageId !== null && $linkedPageId === $currentPageId) {
      return true;
    }

    $hrefPath = $this->normalizePath($href);
    $currentPath = $this->normalizePath($this->request->getPathInfo());

    if ($hrefPath === null || $currentPath === null) {
      return false;
    }

    return match ($mode) {
      'section' => $hrefPath === '/'
        ? $currentPath === '/'
        : $currentPath === $hrefPath || str_starts_with($currentPath, $hrefPath.'/'),
      'current-page' => ($this->request->routeIs('pages.show') || $this->request->routeIs('localized.pages.show'))
        && $hrefPath === $currentPath,
      default => $hrefPath === $currentPath,
    };
  }

  public function normalizePath(?string $value): ?string
  {
    if (! is_string($value) || trim($value) === '' || str_starts_with($value, '#')) {
      return null;
    }

    $host = parse_url($value, PHP_URL_HOST);

    if (is_string($host) && strcasecmp($host, $this->request->getHost()) !== 0) {
      return null;
    }

    $path = parse_url($value, PHP_URL_PATH);

    if (! is_string($path)) {
      return null;
    }

    $path = '/'.ltrim($path, '/');

    return $path === '/' ? '/' : rtrim($path, '/');
  }
}
