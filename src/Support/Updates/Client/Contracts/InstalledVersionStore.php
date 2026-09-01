<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Contracts;

/**
 * Persists the installed version after a successful update. Optional by design:
 * the version constant travels inside the replaced files, so
 * {@see \WebBlocks\Cms\Support\Updates\Client\Support\Version\VersionResolver} reads the new
 * value on the next boot regardless. The default is a no-op; a product that keeps
 * a DB `installed_version` rebinds this to persist eagerly.
 */
interface InstalledVersionStore
{
    public function persist(string $version): void;
}
