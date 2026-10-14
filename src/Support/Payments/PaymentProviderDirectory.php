<?php

namespace WebBlocks\Cms\Support\Payments;

/** Shared provider transport; credentials stay with the supplying plugin. */
interface PaymentProviderDirectory
{
  public function info(string $provider): ?array;

  public function request(string $provider, string $method, string $path, array $payload = [], ?string $requestId = null): array;

  public function verifyWebhook(string $provider, array $event, array $headers): bool;
}
