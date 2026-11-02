<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Contracts;

interface UpdateAdmission
{
    public function assertAllowed(): void;
    public function blocked(): bool;
}
