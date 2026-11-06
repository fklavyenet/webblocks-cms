<?php

namespace WebBlocks\Cms\Support\Mcp;

final readonly class McpOAuthClient
{
  public const PUBLIC_AUTH = 'none';

  public const SECRET_POST = 'client_secret_post';

  public const AUTH_METHODS = [self::PUBLIC_AUTH, self::SECRET_POST];

  public function __construct(
    public string $id,
    public ?string $name,
    public array $redirectUris,
    private ?string $secretHash,
  ) {}

  public function acceptsSecret(#[\SensitiveParameter] mixed $secret): bool
  {
    return $this->secretHash === null
      ? $secret === null
      : is_string($secret) && hash_equals($this->secretHash, hash('sha256', $secret));
  }
}
