<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Persistence;

use WebBlocks\Cms\Support\Updates\Client\Contracts\InstalledVersionStore;

/**
 * Default: does not persist separately. The version constant travels with the
 * replaced files, so the resolver reads the new value on the next boot. A product
 * that keeps an eager DB copy rebinds this contract.
 */
final class NullInstalledVersionStore implements InstalledVersionStore
{
    public function persist(string $version): void
    {
        // no-op
    }
}
