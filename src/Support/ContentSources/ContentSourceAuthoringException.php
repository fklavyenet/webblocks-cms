<?php

namespace WebBlocks\Cms\Support\ContentSources;

use RuntimeException;

class ContentSourceAuthoringException extends RuntimeException
{
  public function __construct(public readonly string $path, string $message)
  {
    parent::__construct($message);
  }
}
