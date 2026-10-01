<?php

namespace WebBlocks\Cms\Support\Pages;

use InvalidArgumentException;

/** Deployment mount for public content, independent of stored page identity. */
class PublicMount
{
  public function prefix(): string
  {
    $value = config('webblocks-cms.public.mount');

    if ($value !== null && ! is_string($value)) {
      throw new InvalidArgumentException('The CMS public mount must be a string or null.');
    }

    $prefix = trim(trim((string) $value), '/');

    if ($prefix === '') {
      return '';
    }

    // Use the same safe segment syntax as content paths, without altering it.
    $path = PagePath::canonicalize('/'.$prefix);

    if (PagePath::isReserved($path) || in_array(explode('/', $prefix)[0], ['webblocks-applications', '_webblocks-cms'], true)) {
      throw new InvalidArgumentException('The CMS public mount overlaps a reserved endpoint.');
    }

    return $prefix;
  }

  public function path(string $path): string
  {
    $prefix = $this->prefix();
    $path = '/'.ltrim($path, '/');

    return $prefix === '' ? $path : '/'.$prefix.($path === '/' ? '' : $path);
  }

  /** Returns null when an incoming public path is outside this mount. */
  public function unmount(string $path): ?string
  {
    $prefix = $this->prefix();

    if ($prefix === '') {
      return '/'.ltrim($path, '/');
    }

    $mount = '/'.$prefix;

    if (rtrim($path, '/') === $mount) {
      return '/';
    }

    return str_starts_with($path, $mount.'/') ? substr($path, strlen($mount)) : null;
  }
}
