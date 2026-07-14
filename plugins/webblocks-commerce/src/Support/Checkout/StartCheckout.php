<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceCart;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommercePayment;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways\CommerceGatewayManager;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways\GatewayCheckoutSession;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\I18n\ProductLocalizer;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Inventory\InventoryManager;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Tax\TaxCalculator;

class StartCheckout
{
  public function __construct(
    private readonly CommerceGatewayManager $gateways,
    private readonly OrderNumberGenerator $orderNumbers,
    private readonly InventoryManager $inventory,
    private readonly TaxCalculator $tax,
    private readonly ProductLocalizer $localizer,
  ) {}

  public function forProduct(CommerceProduct $product): GatewayCheckoutSession
  {
    if (! $product->isAvailableForCheckout()) {
      throw new CheckoutUnavailableException('This product is not available for checkout.');
    }

    $this->assertCurrencySupported($product->currency);

    return DB::transaction(function () use ($product): GatewayCheckoutSession {
      $order = $this->newOrder($product->site_id, $product->currency, null, [
        'checkout_source' => 'single_product_buy_url',
        'product_slug' => $product->slug,
      ]);

      $this->appendLine($order, $product, 1, null);
      $this->syncOrderTotals($order);

      return $this->finalize($order);
    });
  }

  public function forCart(CommerceCart $cart): GatewayCheckoutSession
  {
    $cart->loadMissing('items.product');

    $lines = $cart->items->filter(
      fn ($item): bool => $item->product !== null && $item->product->isActive()
    )->values();

    if ($lines->isEmpty()) {
      throw new CheckoutUnavailableException('Your cart is empty.');
    }

    $currency = $cart->currency ?? $lines->first()->product->currency;

    foreach ($lines as $line) {
      if ($line->product->currency !== $currency) {
        throw new CheckoutUnavailableException('The cart mixes currencies and cannot be checked out.');
      }
    }

    $this->assertCurrencySupported($currency);

    return DB::transaction(function () use ($cart, $lines, $currency): GatewayCheckoutSession {
      $order = $this->newOrder($cart->site_id, $currency, $cart->customer_email, [
        'checkout_source' => 'cart',
        'cart_token' => $cart->token,
        'locale' => $cart->locale,
      ]);

      foreach ($lines as $line) {
        $this->appendLine($order, $line->product, max(1, (int) $line->quantity), $cart->locale);
      }

      $this->syncOrderTotals($order);

      $session = $this->finalize($order);

      $cart->update([
        'status' => CommerceCart::STATUS_CONVERTED,
        'converted_order_id' => $order->id,
      ]);

      return $session;
    });
  }

  /**
   * @param  array<string, mixed>  $metadata
   */
  private function newOrder(?int $siteId, string $currency, ?string $customerEmail, array $metadata): CommerceOrder
  {
    return CommerceOrder::query()->create([
      'site_id' => $siteId,
      'order_number' => $this->orderNumbers->generate(),
      'status' => CommerceOrder::STATUS_PENDING,
      'subtotal_amount' => 0,
      'total_amount' => 0,
      'tax_amount' => 0,
      'tax_rate' => 0,
      'tax_country' => $this->tax->storeCountry(),
      'prices_include_tax' => $this->tax->pricesIncludeTax(),
      'currency' => $currency,
      'customer_email' => $customerEmail,
      'gateway' => $this->gateways->gatewayKey(),
      'placed_at' => now(),
      'metadata' => $metadata,
    ]);
  }

  private function appendLine(CommerceOrder $order, CommerceProduct $product, int $quantity, ?string $localeCode): void
  {
    // Snapshot the applied tax rate onto the line so a later config change never
    // rewrites history. Tax is computed on the whole line to match the order.
    $taxLine = $this->tax->calculate($product->price_amount * $quantity, $product->taxClass());

    $order->items()->create([
      'product_id' => $product->id,
      // Freeze the localized title the buyer saw at purchase time.
      'title' => $this->localizer->title($product, $localeCode),
      'sku' => $product->sku,
      'quantity' => $quantity,
      'unit_amount' => (int) intdiv($taxLine->gross, $quantity),
      'total_amount' => $taxLine->gross,
      'tax_amount' => $taxLine->tax,
      'tax_rate' => $taxLine->rateBps,
      'tax_class' => $product->taxClass(),
      'currency' => $product->currency,
    ]);
  }

  private function syncOrderTotals(CommerceOrder $order): void
  {
    $order->loadMissing('items');

    $net = (int) $order->items->sum(fn ($item): int => (int) $item->total_amount - (int) $item->tax_amount);
    $tax = (int) $order->items->sum('tax_amount');
    $gross = (int) $order->items->sum('total_amount');

    $rates = $order->items->pluck('tax_rate')->unique()->values();

    $order->update([
      'subtotal_amount' => $net,
      'tax_amount' => $tax,
      'total_amount' => $gross,
      // A single order-level rate only makes sense when every line shares one.
      'tax_rate' => $rates->count() === 1 ? (int) $rates->first() : 0,
    ]);
  }

  private function finalize(CommerceOrder $order): GatewayCheckoutSession
  {
    // Reserve stock atomically before contacting the gateway. Out-of-stock lines
    // throw CheckoutUnavailableException, rolling back the whole order and
    // preventing overselling under concurrent checkouts.
    $this->inventory->reserveForOrder($order);

    $session = $this->gateways->gateway()->createCheckoutSession($order);

    $order->update([
      'gateway_checkout_id' => $session->id,
    ]);

    $order->payments()->create([
      'gateway' => $order->gateway,
      'gateway_checkout_id' => $session->id,
      'status' => CommercePayment::STATUS_PENDING,
      'amount' => $order->total_amount,
      'currency' => $order->currency,
      'metadata' => [
        'checkout_session_mode' => $session->mode,
      ],
    ]);

    return $session;
  }

  private function assertCurrencySupported(string $currency): void
  {
    if (! $this->gateways->supportsCurrency($currency)) {
      throw new CheckoutUnavailableException($this->gateways->currencyUnavailableMessage($currency));
    }
  }
}
