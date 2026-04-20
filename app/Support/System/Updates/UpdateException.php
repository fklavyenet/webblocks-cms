<?php

namespace App\Support\System\Updates;

use RuntimeException;
use Throwable;

class UpdateException extends RuntimeException
{
    public function __construct(
        private readonly string $userMessage,
        ?string $logMessage = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($logMessage ?? $userMessage, $code, $previous);
    }

    public function userMessage(): string
    {
        return $this->userMessage;
    }
}
