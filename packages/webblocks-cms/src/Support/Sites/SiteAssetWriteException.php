<?php

namespace WebBlocks\Cms\Support\Sites;

use RuntimeException;
use Throwable;

class SiteAssetWriteException extends RuntimeException
{
  public function __construct(
    string $message,
    public readonly array $readiness = [],
    ?Throwable $previous = null,
  ) {
    parent::__construct($message, previous: $previous);
  }
}
