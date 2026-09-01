<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Contracts;

/**
 * Takes a pre-update snapshot and restores it on failure (§7.2, restore-on-
 * failure recovery). Per-product: the default snapshots the apply
 * target on the filesystem; a product with richer backups (DB + files) rebinds.
 */
interface BackupManager
{
    /**
     * Create a pre-update backup. Returns an opaque handle used by restore(),
     * or null when nothing was captured.
     */
    public function create(string $label, ?int $userId = null): ?string;

    /**
     * Restore a backup created by create(). Returns true on success.
     */
    public function restore(string $handle): bool;
}
