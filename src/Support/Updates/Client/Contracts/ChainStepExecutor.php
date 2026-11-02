<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Contracts;

interface ChainStepExecutor
{
    /** Execute in a fresh runtime and return the verified installed version, or throw. */
    public function execute(int $position, array $approvedStep): string;
}
