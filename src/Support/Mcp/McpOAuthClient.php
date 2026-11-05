<?php

namespace WebBlocks\Cms\Support\Mcp;

final readonly class McpOAuthClient
{
  public function __construct(
    public string $id,
    public ?string $name,
    public array $redirectUris,
  ) {}
}
