<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\CheckoutUnavailableException;

class SumUpApiClient
{
  public function __construct(
    private readonly SumUpConfig $config,
  ) {}

  /**
   * @param  array<string, mixed>  $payload
   * @return array<string, mixed>
   */
  public function createCheckout(array $payload): array
  {
    return $this->json($this->request()->post($this->config->apiBaseUrl().'/v0.1/checkouts', $payload));
  }

  /**
   * @return array<string, mixed>
   */
  public function retrieveCheckout(string $checkoutId): array
  {
    return $this->json($this->request()->get(
      $this->config->apiBaseUrl().'/v0.1/checkouts/'.rawurlencode($checkoutId)
    ));
  }

  private function request(): PendingRequest
  {
    $apiKey = $this->config->apiKey();

    if ($apiKey === null || ! $this->config->isCheckoutReady()) {
      throw new CheckoutUnavailableException('SumUp checkout is not configured yet.');
    }

    return Http::withToken($apiKey)
      ->withHeaders(['Accept' => 'application/problem+json, application/json'])
      ->asJson();
  }

  /**
   * @return array<string, mixed>
   */
  private function json(Response $response): array
  {
    if ($response->failed()) {
      throw new CheckoutUnavailableException('SumUp request failed.');
    }

    $payload = $response->json();

    if (! is_array($payload)) {
      throw new CheckoutUnavailableException('SumUp response was not valid JSON.');
    }

    return $payload;
  }
}
