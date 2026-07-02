<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways;

use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\CheckoutUnavailableException;

class CommerceGatewayManager
{
  public function __construct(
    private readonly PayPalConfig $paypalConfig,
  ) {}

  public function gatewayKey(): string
  {
    $configured = config('webblocks-commerce.gateway');

    if (! is_string($configured) || trim($configured) === '') {
      $configured = env('WEBBLOCKS_COMMERCE_GATEWAY', 'paypal');
    }

    return strtolower(trim((string) $configured));
  }

  public function supportsCheckout(): bool
  {
    return match ($this->gatewayKey()) {
      'fake' => true,
      'paypal' => $this->paypalConfig->isCheckoutReady(),
      default => false,
    };
  }

  public function unavailableMessage(): string
  {
    return match ($this->gatewayKey()) {
      'paypal' => 'PayPal checkout is not configured yet. Add the PayPal client ID, client secret, and webhook ID before accepting payments.',
      default => 'Checkout gateway configuration is not active yet.',
    };
  }

  public function gateway(): PaymentGatewayInterface
  {
    return match ($this->gatewayKey()) {
      'fake' => app(FakeCheckoutGateway::class),
      'paypal' => $this->paypalConfig->isCheckoutReady()
        ? app(PayPalCheckoutGateway::class)
        : throw new CheckoutUnavailableException($this->unavailableMessage()),
      default => throw new CheckoutUnavailableException($this->unavailableMessage()),
    };
  }
}
