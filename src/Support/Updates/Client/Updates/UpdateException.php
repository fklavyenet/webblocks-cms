<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Updates;

use RuntimeException;
use Throwable;

/**
 * Carries a safe, user-facing message separately from the (possibly sensitive)
 * log message.
 */
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
