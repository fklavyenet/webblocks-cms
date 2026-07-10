<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Cart;

use Illuminate\Support\Str;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceCart;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceCartItem;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\I18n\ProductLocalizer;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Tax\TaxCalculator;

/**
 * Server-side, persistent, single-currency shopping cart.
 *
 * The cart stores only product references + quantities; prices and tax are
 * resolved live from the current catalog and only frozen when the cart is
 * converted to an order at checkout. This keeps the "snapshot at purchase"
 * discipline and avoids stale-price bugs. Inventory is not reserved here — that
 * happens atomically at checkout — but add/update are guarded against exceeding
 * available tracked stock so shoppers get early feedback.
 */
class CartService
{
  public function __construct(
    private readonly TaxCalculator $tax,
    private readonly ProductLocalizer $localizer,
  ) {}

  public function create(?int $siteId = null, ?string $locale = null, ?string $currency = null): CommerceCart
  {
    return CommerceCart::query()->create([
      'token' => $this->generateToken(),
      'site_id' => $siteId,
      'locale' => $locale !== null && $locale !== '' ? $locale : null,
      'currency' => $currency !== null && $currency !== '' ? strtoupper($currency) : null,
      'status' => CommerceCart::STATUS_OPEN,
    ]);
  }

  public function findOpenByToken(string $token): ?CommerceCart
  {
    return CommerceCart::query()
      ->where('token', $token)
      ->where('status', CommerceCart::STATUS_OPEN)
      ->first();
  }

  public function addProduct(CommerceCart $cart, CommerceProduct $product, int $quantity = 1): CommerceCartItem
  {
    $this->assertOpen($cart);

    if ($quantity < 1) {
      throw new CartException('Quantity must be at least 1.');
    }

    if (! $product->isActive()) {
      throw new CartException(sprintf('“%s” is not available for purchase.', $product->title));
    }

    $this->assertCurrencyMatches($cart, $product);

    $item = $cart->items()->where('product_id', $product->getKey())->first();
    $newQuantity = ($item?->quantity ?? 0) + $quantity;

    $this->assertStock($product, $newQuantity);

    if ($cart->currency === null) {
      $cart->update(['currency' => $product->currency]);
    }

    if ($item !== null) {
      $item->update(['quantity' => $newQuantity]);

      return $item;
    }

    return $cart->items()->create([
      'product_id' => $product->getKey(),
      'quantity' => $newQuantity,
      'currency' => $product->currency,
    ]);
  }

  public function setQuantity(CommerceCart $cart, CommerceProduct $product, int $quantity): void
  {
    $this->assertOpen($cart);

    $item = $cart->items()->where('product_id', $product->getKey())->first();

    if ($quantity <= 0) {
      $item?->delete();
      $this->resetCurrencyIfEmpty($cart);

      return;
    }

    if ($item === null) {
      $this->addProduct($cart, $product, $quantity);

      return;
    }

    if (! $product->isActive()) {
      throw new CartException(sprintf('“%s” is not available for purchase.', $product->title));
    }

    $this->assertStock($product, $quantity);
    $item->update(['quantity' => $quantity]);
  }

  public function removeProduct(CommerceCart $cart, CommerceProduct $product): void
  {
    $this->assertOpen($cart);

    $cart->items()->where('product_id', $product->getKey())->delete();
    $this->resetCurrencyIfEmpty($cart);
  }

  public function clear(CommerceCart $cart): void
  {
    $this->assertOpen($cart);

    $cart->items()->delete();
    $cart->update(['currency' => null]);
  }

  /**
   * Live pricing + tax breakdown for the cart. Unavailable lines (archived or
   * deleted product) are flagged and excluded from the totals.
   *
   * @return array<string, mixed>
   */
  public function summary(CommerceCart $cart): array
  {
    $cart->loadMissing('items.product.translations');

    $lines = [];
    $net = 0;
    $tax = 0;
    $gross = 0;

    foreach ($cart->items as $item) {
      $product = $item->product;
      $quantity = max(1, (int) $item->quantity);
      $available = $product !== null && $product->isActive();

      if (! $available) {
        $lines[] = [
          'product_id' => $item->product_id,
          'title' => $product !== null ? $this->localizer->title($product, $cart->locale) : null,
          'quantity' => $quantity,
          'available' => false,
          'currency' => $cart->currency,
        ];

        continue;
      }

      $taxLine = $this->tax->calculate($product->price_amount * $quantity, $product->taxClass());
      $net += $taxLine->net;
      $tax += $taxLine->tax;
      $gross += $taxLine->gross;

      $lines[] = [
        'product_id' => $product->getKey(),
        'title' => $this->localizer->title($product, $cart->locale),
        'sku' => $product->sku,
        'quantity' => $quantity,
        'available' => true,
        'unit_amount' => (int) $product->price_amount,
        'line_net' => $taxLine->net,
        'line_tax' => $taxLine->tax,
        'line_total' => $taxLine->gross,
        'tax_rate' => $taxLine->rateBps,
        'tax_class' => $product->taxClass(),
        'currency' => $product->currency,
      ];
    }

    return [
      'token' => $cart->token,
      'status' => $cart->status,
      'currency' => $cart->currency,
      'locale' => $cart->locale,
      'prices_include_tax' => $this->tax->pricesIncludeTax(),
      'items' => $lines,
      'subtotal_amount' => $net,
      'tax_amount' => $tax,
      'total_amount' => $gross,
    ];
  }

  private function assertOpen(CommerceCart $cart): void
  {
    if (! $cart->isOpen()) {
      throw new CartException('This cart is no longer open for changes.');
    }
  }

  private function assertCurrencyMatches(CommerceCart $cart, CommerceProduct $product): void
  {
    if ($cart->currency !== null && $cart->currency !== $product->currency) {
      throw new CartException(sprintf(
        'This cart is in %s; “%s” is priced in %s. Use a separate cart per currency.',
        $cart->currency,
        $product->title,
        $product->currency,
      ));
    }
  }

  private function assertStock(CommerceProduct $product, int $quantity): void
  {
    if ($product->tracksInventory() && $quantity > (int) $product->inventory_quantity) {
      throw new CartException(sprintf(
        'Only %d of “%s” are in stock.',
        (int) $product->inventory_quantity,
        $product->title,
      ));
    }
  }

  private function resetCurrencyIfEmpty(CommerceCart $cart): void
  {
    if ($cart->items()->count() === 0) {
      $cart->update(['currency' => null]);
    }
  }

  private function generateToken(): string
  {
    do {
      $token = 'cart_'.Str::lower(Str::random(40));
    } while (CommerceCart::query()->where('token', $token)->exists());

    return $token;
  }
}
