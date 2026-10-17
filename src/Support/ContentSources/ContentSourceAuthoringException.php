<?php

namespace WebBlocks\Cms\Support\ContentSources;

use RuntimeException;

class ContentSourceAuthoringException extends RuntimeException
{
  public function __construct(public readonly string $path, string $message, public readonly string $reason = 'invalid_configuration')
  {
    parent::__construct($message);
  }
}
