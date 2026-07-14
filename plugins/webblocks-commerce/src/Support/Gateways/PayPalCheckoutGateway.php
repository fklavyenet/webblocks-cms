<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\CheckoutUnavailableException;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Currency\MoneyFormatter;

class PayPalCheckoutGateway implements PaymentGatewayInterface
{
  public function __construct(
    private readonly PayPalApiClient $client,
    private readonly PayPalConfig $config,
    private readonly MoneyFormatter $money,
  ) {}

  public function createCheckoutSession(CommerceOrder $order): GatewayCheckoutSession
  {
    // Derive the charge from the order's authoritative totals. The breakdown
    // balances by construction — item_total (net) + tax_total == amount (gross) —
    // because the order aggregates its line net/tax/gross consistently.
    $payload = [
      'intent' => 'CAPTURE',
      'purchase_units' => [[
        'reference_id' => (string) $order->id,
        'custom_id' => $order->order_number,
        'invoice_id' => $order->order_number,
        'amount' => [
          'currency_code' => $order->currency,
          'value' => $this->money->majorUnits($order->total_amount, $order->currency),
          'breakdown' => [
            'item_total' => [
              'currency_code' => $order->currency,
              'value' => $this->money->majorUnits($order->subtotal_amount, $order->currency),
            ],
            'tax_total' => [
              'currency_code' => $order->currency,
              'value' => $this->money->majorUnits((int) $order->tax_amount, $order->currency),
            ],
          ],
        ],
      ]],
      'application_context' => [
        'brand_name' => Str::limit((string) config('app.name', 'WebBlocks Commerce'), 127, ''),
        'return_url' => URL::temporarySignedRoute('webblocks.commerce.checkout.success', now()->addHours(2), [
          'order' => $order->id,
        ]),
        'cancel_url' => URL::temporarySignedRoute('webblocks.commerce.checkout.cancel', now()->addHours(2), [
          'order' => $order->id,
        ]),
        'user_action' => 'PAY_NOW',
      ],
    ];

    $response = $this->client->createOrder($payload);
    $paypalOrderId = $response['id'] ?? null;
    $approveUrl = $this->approveUrl($response);

    if (! is_string($paypalOrderId) || trim($paypalOrderId) === '' || $approveUrl === null) {
      throw new CheckoutUnavailableException('PayPal checkout response did not include an approval URL.');
    }

    return new GatewayCheckoutSession(
      id: $paypalOrderId,
      redirectUrl: $approveUrl,
      mode: 'paypal-'.$this->config->mode(),
    );
  }

  /**
   * @param  array<string, mixed>  $response
   */
  private function approveUrl(array $response): ?string
  {
    $links = $response['links'] ?? [];

    if (! is_array($links)) {
      return null;
    }

    foreach ($links as $link) {
      if (! is_array($link)) {
        continue;
      }

      if (($link['rel'] ?? null) === 'approve' && is_string($link['href'] ?? null)) {
        return $link['href'];
      }
    }

    return null;
  }
}
