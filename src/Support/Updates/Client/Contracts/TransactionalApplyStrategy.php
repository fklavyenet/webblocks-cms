<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Contracts;

/** Optional lifecycle for strategies that also change resolved dependencies. */
interface TransactionalApplyStrategy extends ApplyStrategy
{
    /** Validate and resolve without changing the installed application. */
    public function prepare(string $stagedRoot, array &$output): void;

    /** Restore all affected code and Composer state before database recovery. */
    public function rollback(array &$output): void;

    /** Release recovery files only after the entire update succeeds. */
    public function commit(): void;
}
