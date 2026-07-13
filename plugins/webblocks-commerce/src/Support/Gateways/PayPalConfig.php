<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways;

use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\CommerceSettingsStore;

class PayPalConfig
{
  public function __construct(
    private readonly CommerceSettingsStore $settings,
  ) {}

  public function mode(): string
  {
    $configured = $this->settings->value(CommerceSettingsStore::PAYPAL_MODE);

    return strtolower((string) $configured) === 'live' ? 'live' : 'sandbox';
  }

  public function clientId(): ?string
  {
    return $this->settings->value(CommerceSettingsStore::PAYPAL_CLIENT_ID);
  }

  public function clientSecret(): ?string
  {
    return $this->settings->value(CommerceSettingsStore::PAYPAL_CLIENT_SECRET);
  }

  public function webhookId(): ?string
  {
    return $this->settings->value(CommerceSettingsStore::PAYPAL_WEBHOOK_ID);
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
}
