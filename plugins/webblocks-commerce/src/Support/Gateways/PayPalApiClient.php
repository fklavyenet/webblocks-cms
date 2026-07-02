<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\CheckoutUnavailableException;

class PayPalApiClient
{
  public function __construct(
    private readonly PayPalConfig $config,
  ) {}

  /**
   * @param  array<string, mixed>  $payload
   * @return array<string, mixed>
   */
  public function createOrder(array $payload): array
  {
    return $this->json($this->postJson('/v2/checkout/orders', $payload));
  }

  /**
   * @return array<string, mixed>
   */
  public function captureOrder(string $paypalOrderId): array
  {
    return $this->json($this->postJson('/v2/checkout/orders/'.$paypalOrderId.'/capture', []));
  }

  /**
   * @param  array<string, mixed>  $payload
   * @return array<string, mixed>
   */
  public function verifyWebhook(array $payload): array
  {
    return $this->json($this->postJson('/v1/notifications/verify-webhook-signature', $payload));
  }

  /**
   * @param  array<string, mixed>  $payload
   */
  private function postJson(string $path, array $payload): Response
  {
    if (! $this->config->isCheckoutReady()) {
      throw new CheckoutUnavailableException('PayPal checkout is not configured yet.');
    }

    $response = Http::withToken($this->accessToken())
      ->acceptJson()
      ->asJson()
      ->post($this->config->apiBaseUrl().$path, $payload);

    if ($response->failed()) {
      throw new CheckoutUnavailableException('PayPal request failed.');
    }

    return $response;
  }

  private function accessToken(): string
  {
    $clientId = $this->config->clientId();
    $clientSecret = $this->config->clientSecret();

    if ($clientId === null || $clientSecret === null) {
      throw new CheckoutUnavailableException('PayPal checkout is not configured yet.');
    }

    $response = Http::withBasicAuth($clientId, $clientSecret)
      ->acceptJson()
      ->asForm()
      ->post($this->config->apiBaseUrl().'/v1/oauth2/token', [
        'grant_type' => 'client_credentials',
      ]);

    if ($response->failed()) {
      throw new CheckoutUnavailableException('PayPal authentication failed.');
    }

    $token = $response->json('access_token');

    if (! is_string($token) || trim($token) === '') {
      throw new CheckoutUnavailableException('PayPal authentication response did not include an access token.');
    }

    return $token;
  }

  /**
   * @return array<string, mixed>
   */
  private function json(Response $response): array
  {
    $payload = $response->json();

    if (! is_array($payload)) {
      throw new CheckoutUnavailableException('PayPal response was not valid JSON.');
    }

    return $payload;
  }
}
