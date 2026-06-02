<?php

namespace WebBlocks\Cms\Support\System\Updates\Publishing;

final readonly class UpdatePublishResult
{
  public function __construct(
    public string $status,
    public string $message,
    public string $product,
    public string $channel,
    public string $version,
    public string $artifactPath,
    public string $payloadPath,
    public string $checksumSha256,
    public bool $tokenConfigured,
    public bool $published,
    public bool $verified,
    public ?array $publishResponse = null,
    public ?array $latestResponse = null,
    public array $configuredKeys = [],
  ) {}
}
