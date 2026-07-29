<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Support\Str;
use InvalidArgumentException;

class PagePath
{
  private const RESERVED_FIRST_SEGMENTS = [
    'admin-api',
    'cms',
    'contact-messages',
    'confirm-password',
    'dashboard',
    'email',
    'forgot-password',
    'install',
    'login',
    'logout',
    'password',
    /*
     * The prefix every plugin public route is mounted under. Reserving it is
     * what keeps a plugin endpoint and a page slug from competing for the same
     * URL — public pages are served by a dynamic `{slug}` route, so without this
     * a page published at `/plugins/anything` and a plugin's own endpoint are
     * two routes for one path.
     *
     * It replaced `commerce`, which reserved a first segment for one plugin's
     * storefront back when core registered those URLs itself. Nothing serves
     * `/commerce` now, so holding it back would only stop someone publishing a
     * page there.
     */
    'plugins',
    'privacy-consent',
    'profile',
    'register',
    'reset-password',
    'search',
    'search.json',
    'verify-email',
    'webadmin',
  ];

  public static function canonicalize(string $path): string
  {
    $path = trim($path);

    if ($path === '') {
      throw new InvalidArgumentException('Page path is required.');
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $path)) {
      throw new InvalidArgumentException('Page path contains control characters.');
    }

    if (str_contains($path, '?') || str_contains($path, '#')) {
      throw new InvalidArgumentException('Page path must not include query strings or fragments.');
    }

    if (preg_match('/%2f|%5c/i', $path)) {
      throw new InvalidArgumentException('Page path must not include encoded slash characters.');
    }

    $path = str_replace('\\', '/', $path);

    if ($path === '/') {
      return '/';
    }

    $path = '/'.trim($path, '/');

    if (str_contains($path, '//')) {
      throw new InvalidArgumentException('Page path must not include empty segments.');
    }

    $segments = explode('/', trim($path, '/'));

    foreach ($segments as $segment) {
      if ($segment === '' || $segment === '.' || $segment === '..') {
        throw new InvalidArgumentException('Page path contains an unsafe segment.');
      }

      if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $segment)) {
        throw new InvalidArgumentException('Page path segments may only contain lowercase letters, numbers, and hyphens.');
      }
    }

    return '/'.implode('/', $segments);
  }

  public static function fromSlug(string $slug): string
  {
    $slug = Str::slug($slug);

    return $slug === 'home' || $slug === '' ? '/' : '/'.$slug;
  }

  public static function slugFromPath(string $path): string
  {
    $path = self::canonicalize($path);

    if ($path === '/') {
      return 'home';
    }

    $segments = explode('/', trim($path, '/'));

    return end($segments) ?: 'home';
  }

  public static function isReserved(string $path): bool
  {
    $path = self::canonicalize($path);

    if ($path === '/') {
      return false;
    }

    $first = explode('/', trim($path, '/'))[0] ?? '';

    return in_array($first, self::RESERVED_FIRST_SEGMENTS, true);
  }

  public static function routePattern(): string
  {
    $reserved = implode('|', array_map(
      fn (string $segment) => preg_quote($segment, '#'),
      self::RESERVED_FIRST_SEGMENTS,
    ));

    return '(?!(?:'.$reserved.')(?:/|$)).*';
  }
}
