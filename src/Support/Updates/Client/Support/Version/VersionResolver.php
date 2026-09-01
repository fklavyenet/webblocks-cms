<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Support\Version;

/**
 * The single integration point with Task A (single-source Support::VERSION).
 *
 * Every consumer exposes its installed version through one resolver, so the
 * engine never hard-codes a product-specific class/const. The default
 * {@see ConfigVersionResolver} reads config('app.version'); a product with a
 * different source binds its own implementation in a service provider.
 */
interface VersionResolver
{
    /**
     * The currently-installed product version (e.g. "1.40.21").
     */
    public function current(): string;

    /**
     * Read the version that a freshly-applied package reports on disk, used by
     * post-apply verification. Implementations typically tokenize the applied
     * version file rather than booting the class. Returns null if it cannot be
     * determined from the given root.
     *
     * @param string $appliedRoot Filesystem root of the just-applied package.
     */
    public function appliedVersion(string $appliedRoot): ?string;
}
