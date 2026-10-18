<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Contracts;

/** Progress contains phase identifiers and timings only, never command output. */
interface ProgressReporter
{
    public function beginRun(): void;
    public function phase(string $phase): void;
    public function beat(): void;
    public function failStep(): void;
    public function endRun(string $status): void;
}
