<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Contracts;

/**
 * The one part of the update pipeline that legitimately differs between product
 * types (§7.1): where extracted release files land on disk.
 *
 *  - 'package'   : replace only the configured product package directory.
 *  - 'full-root' : replace the app root, skipping preserve_paths (standalone apps).
 *
 * Everything else in the pipeline — check, download, verify (checksum +
 * mandatory Ed25519 signature), backup, migrate, record, admin flow — is shared
 * and strategy-agnostic. Concrete strategies are added in Phase 3.
 */
interface ApplyStrategy
{
    /**
     * Strategy identifier, e.g. 'package' | 'full-root'.
     */
    public function name(): string;

    /**
     * Resolve the absolute filesystem root this strategy writes into.
     */
    public function targetRoot(): string;

    /**
     * Apply the already-verified, extracted release located at $stagedRoot into
     * the target, honouring the strategy's preserve/allowlist rules.
     *
     * @param string $stagedRoot Absolute path to the verified, extracted release.
     * @return array<string,mixed> Apply outcome (changed paths, timing, notes).
     */
    public function apply(string $stagedRoot): array;
}
