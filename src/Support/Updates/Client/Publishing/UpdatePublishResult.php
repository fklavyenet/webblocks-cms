<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Publishing;

/**
 * Immutable result of a publish attempt.
 * `status` is one of: dry_run | skipped | published.
 */
class UpdatePublishResult
{
  public function __construct(
    public readonly string $status,
    public readonly string $message,
    public readonly string $product,
    public readonly string $channel,
    public readonly string $version,
    public readonly string $artifactPath,
    public readonly string $payloadPath,
    public readonly string $checksumSha256,
    public readonly bool $tokenConfigured,
    public readonly bool $published,
    public readonly bool $verified,
    public readonly array $configuredKeys = [],
    public readonly ?array $publishResponse = null,
    public readonly ?array $latestResponse = null,
  ) {}
}
