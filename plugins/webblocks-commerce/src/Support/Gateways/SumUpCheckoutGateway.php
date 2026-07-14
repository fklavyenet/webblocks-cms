<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\CheckoutUnavailableException;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Currency\MoneyFormatter;

class SumUpCheckoutGateway implements PaymentGatewayInterface
{
  public function __construct(
    private readonly SumUpApiClient $client,
    private readonly SumUpConfig $config,
    private readonly MoneyFormatter $money,
  ) {}

  public function createCheckoutSession(CommerceOrder $order): GatewayCheckoutSession
  {
    $merchantCode = $this->config->merchantCode();

    if ($merchantCode === null) {
      throw new CheckoutUnavailableException('SumUp checkout is not configured yet.');
    }

    $response = $this->client->createCheckout([
      'amount' => $this->money->majorUnitsNumber($order->total_amount, $order->currency),
      'checkout_reference' => $order->order_number,
      'currency' => $order->currency,
      'description' => Str::limit(
        (string) config('app.name', 'WebBlocks Commerce').' · '.$order->order_number,
        255,
        ''
      ),
      'merchant_code' => $merchantCode,
      'redirect_url' => URL::temporarySignedRoute('webblocks.commerce.checkout.success', now()->addHours(2), [
        'order' => $order->id,
      ]),
      'return_url' => route('webblocks.commerce.webhooks.sumup'),
      'hosted_checkout' => [
        'enabled' => true,
      ],
    ]);

    $checkoutId = $response['id'] ?? null;
    $hostedCheckoutUrl = $response['hosted_checkout_url'] ?? null;

    if (! is_string($checkoutId) || trim($checkoutId) === ''
      || ! is_string($hostedCheckoutUrl) || ! Str::startsWith($hostedCheckoutUrl, 'https://')) {
      throw new CheckoutUnavailableException('SumUp checkout response did not include a hosted checkout URL.');
    }

    return new GatewayCheckoutSession(
      id: $checkoutId,
      redirectUrl: $hostedCheckoutUrl,
      mode: 'sumup-'.$this->config->mode(),
    );
  }
}
