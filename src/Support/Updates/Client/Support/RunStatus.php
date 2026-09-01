<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Support;

/**
 * Update-run status vocabulary. `restored` marks a failed run
 * whose pre-update backup was rolled back successfully.
 */
final class RunStatus
{
    public const SUCCESS = 'success';
    public const SUCCESS_WITH_WARNINGS = 'success_with_warnings';
    public const FAILED = 'failed';
    public const RESTORED = 'restored';
}
