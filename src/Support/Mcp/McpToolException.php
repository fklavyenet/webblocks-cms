<?php

namespace WebBlocks\Cms\Support\Mcp;

final class McpToolException extends \RuntimeException
{
  public function __construct(public readonly string $reason)
  {
    parent::__construct(__('webblocks-cms::mcp.'.$reason));
  }
}
