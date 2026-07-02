<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommercePayment;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways\CommerceGatewayManager;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways\GatewayCheckoutSession;

class StartCheckout
{
  public function __construct(
    private readonly CommerceGatewayManager $gateways,
    private readonly OrderNumberGenerator $orderNumbers,
  ) {}

  public function forProduct(CommerceProduct $product): GatewayCheckoutSession
  {
    if (! $product->isAvailableForCheckout()) {
      throw new CheckoutUnavailableException('This product is not available for checkout.');
    }

    return DB::transaction(function () use ($product): GatewayCheckoutSession {
      $gateway = $this->gateways->gateway();
      $gatewayKey = $this->gateways->gatewayKey();

      $order = CommerceOrder::query()->create([
        'site_id' => $product->site_id,
        'order_number' => $this->orderNumbers->generate(),
        'status' => CommerceOrder::STATUS_PENDING,
        'subtotal_amount' => $product->price_amount,
        'total_amount' => $product->price_amount,
        'currency' => $product->currency,
        'gateway' => $gatewayKey,
        'placed_at' => now(),
        'metadata' => [
          'checkout_source' => 'single_product_buy_url',
          'product_slug' => $product->slug,
        ],
      ]);

      $order->items()->create([
        'product_id' => $product->id,
        'title' => $product->title,
        'sku' => $product->sku,
        'quantity' => 1,
        'unit_amount' => $product->price_amount,
        'total_amount' => $product->price_amount,
        'currency' => $product->currency,
      ]);

      $session = $gateway->createCheckoutSession($order, $product);

      $order->update([
        'gateway_checkout_id' => $session->id,
      ]);

      $order->payments()->create([
        'gateway' => $gatewayKey,
        'gateway_checkout_id' => $session->id,
        'status' => CommercePayment::STATUS_PENDING,
        'amount' => $order->total_amount,
        'currency' => $order->currency,
        'metadata' => [
          'checkout_session_mode' => $session->mode,
        ],
      ]);

      return $session;
    });
  }
}
