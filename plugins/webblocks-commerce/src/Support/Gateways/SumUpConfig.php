<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways;

use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\CommerceSettingsStore;

class SumUpConfig
{
  public function __construct(
    private readonly CommerceSettingsStore $settings,
  ) {}

  public function mode(): string
  {
    $configured = $this->settings->value(CommerceSettingsStore::SUMUP_MODE);

    return strtolower((string) $configured) === 'live' ? 'live' : 'sandbox';
  }

  public function apiKey(): ?string
  {
    return $this->settings->value(CommerceSettingsStore::SUMUP_API_KEY);
  }

  public function merchantCode(): ?string
  {
    return $this->settings->value(CommerceSettingsStore::SUMUP_MERCHANT_CODE);
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
}
