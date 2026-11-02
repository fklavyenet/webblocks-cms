<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Chains;

use WebBlocks\Cms\Support\Updates\Client\Contracts\UpdateAdmission;

/** Consumers without a durable chain adapter keep their existing one-step behavior. */
class UnrestrictedUpdateAdmission implements UpdateAdmission
{
    public function assertAllowed(): void {}
    public function blocked(): bool { return false; }
}
