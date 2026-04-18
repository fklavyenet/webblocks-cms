<?php

namespace App\Support\System\Updates;

class ReleaseCompatibility
{
    public function __construct(
        public readonly string $status,
        public readonly array $reasons,
    ) {}
}
