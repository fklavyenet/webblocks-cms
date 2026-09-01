<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Contracts;

/**
 * Runs post-apply work (migrations, cache clears, product-specific commands)
 * after files are in place and before version verification. The default runs the
 * configured allowlisted `commands.post_apply` list. Slice 5 layers strategy-
 * aware migrations onto the default implementation.
 *
 * `$commandDir` is the host app root (where `artisan`/`composer` run), which is
 * NOT necessarily the apply target (a package strategy replaces a subdirectory).
 */
interface PostApplyRunner
{
    public function run(string $commandDir, array &$output): void;
}
