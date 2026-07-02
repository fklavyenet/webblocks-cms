<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways;

class PayPalConfig
{
  public function mode(): string
  {
    $configured = $this->stringValue('mode', env('WEBBLOCKS_COMMERCE_PAYPAL_MODE', 'sandbox'));

    return $configured === 'live' ? 'live' : 'sandbox';
  }

  public function clientId(): ?string
  {
    return $this->nullableStringValue('client_id', env('WEBBLOCKS_COMMERCE_PAYPAL_CLIENT_ID'));
  }

  public function clientSecret(): ?string
  {
    return $this->nullableStringValue('client_secret', env('WEBBLOCKS_COMMERCE_PAYPAL_CLIENT_SECRET'));
  }

  public function webhookId(): ?string
  {
    return $this->nullableStringValue('webhook_id', env('WEBBLOCKS_COMMERCE_PAYPAL_WEBHOOK_ID'));
  }

  public function apiBaseUrl(): string
  {
    return $this->mode() === 'live'
      ? 'https://api-m.paypal.com'
      : 'https://api-m.sandbox.paypal.com';
  }

  public function isCheckoutReady(): bool
  {
    return $this->clientId() !== null && $this->clientSecret() !== null;
  }

  public function isWebhookReady(): bool
  {
    return $this->isCheckoutReady() && $this->webhookId() !== null;
  }

  private function stringValue(string $key, mixed $fallback): string
  {
    $value = config('webblocks-commerce.paypal.'.$key, $fallback);

    return strtolower(trim((string) $value));
  }

  private function nullableStringValue(string $key, mixed $fallback): ?string
  {
    $value = config('webblocks-commerce.paypal.'.$key, $fallback);

    if (! is_string($value) || trim($value) === '') {
      return null;
    }

    return trim($value);
  }
}
