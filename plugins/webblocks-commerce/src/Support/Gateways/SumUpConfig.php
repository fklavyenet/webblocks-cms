<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways;

class SumUpConfig
{
  public function mode(): string
  {
    $configured = $this->stringValue('mode', env('WEBBLOCKS_COMMERCE_SUMUP_MODE', 'sandbox'));

    return $configured === 'live' ? 'live' : 'sandbox';
  }

  public function apiKey(): ?string
  {
    return $this->nullableStringValue('api_key', env('WEBBLOCKS_COMMERCE_SUMUP_API_KEY'));
  }

  public function merchantCode(): ?string
  {
    return $this->nullableStringValue('merchant_code', env('WEBBLOCKS_COMMERCE_SUMUP_MERCHANT_CODE'));
  }

  public function apiBaseUrl(): string
  {
    return 'https://api.sumup.com';
  }

  public function isCheckoutReady(): bool
  {
    return $this->apiKey() !== null && $this->merchantCode() !== null;
  }

  public function isWebhookReady(): bool
  {
    return $this->isCheckoutReady();
  }

  private function stringValue(string $key, mixed $fallback): string
  {
    $value = config('webblocks-commerce.sumup.'.$key, $fallback);

    return strtolower(trim((string) $value));
  }

  private function nullableStringValue(string $key, mixed $fallback): ?string
  {
    $value = config('webblocks-commerce.sumup.'.$key, $fallback);

    if (! is_string($value) || trim($value) === '') {
      return null;
    }

    return trim($value);
  }
}
