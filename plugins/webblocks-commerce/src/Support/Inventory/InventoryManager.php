<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Inventory;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\CheckoutUnavailableException;

/**
 * Atomic stock reservation for commerce orders.
 *
 * Stock is decremented the moment an order leaves the catalog (checkout starts)
 * so that concurrent buyers cannot oversell a tracked product. The reservation
 * is released back to the catalog when the order fails, is cancelled, expires,
 * or is refunded. Products with a null inventory_quantity are treated as
 * untracked (unlimited) and are never touched.
 */
class InventoryManager
{
  /**
   * Reserve stock for every tracked item on the order.
   *
   * Runs inside the caller's checkout transaction: if any item is out of stock
   * the exception rolls back the whole order, so no partial reservation leaks.
   *
   * @throws CheckoutUnavailableException when a tracked product has insufficient stock.
   */
  public function reserveForOrder(CommerceOrder $order): void
  {
    $order->loadMissing('items');

    foreach ($order->items as $item) {
      if ($item->product_id === null) {
        continue;
      }

      $product = CommerceProduct::query()->find($item->product_id);

      if ($product === null || ! $product->tracksInventory()) {
        continue;
      }

      $quantity = max(1, (int) $item->quantity);

      $reserved = CommerceProduct::query()
        ->whereKey($product->getKey())
        ->whereNotNull('inventory_quantity')
        ->where('inventory_quantity', '>=', $quantity)
        ->update(['inventory_quantity' => DB::raw('inventory_quantity - '.$quantity)]);

      if ($reserved === 0) {
        throw new CheckoutUnavailableException(sprintf('“%s” is out of stock.', $product->title));
      }

      $metadata = $item->metadata ?? [];
      $metadata['inventory_reserved'] = true;
      $metadata['reserved_quantity'] = $quantity;
      $item->update(['metadata' => $metadata]);
    }
  }

  /**
   * Return previously reserved stock to the catalog.
   *
   * Idempotent: the per-item reserved flag is cleared once released, so calling
   * this twice for the same order never restocks twice.
   */
  public function releaseForOrder(CommerceOrder $order): void
  {
    $order->loadMissing('items');

    foreach ($order->items as $item) {
      $metadata = $item->metadata ?? [];

      if (empty($metadata['inventory_reserved']) || $item->product_id === null) {
        continue;
      }

      $quantity = max(1, (int) ($metadata['reserved_quantity'] ?? $item->quantity));

      CommerceProduct::query()
        ->whereKey($item->product_id)
        ->whereNotNull('inventory_quantity')
        ->update(['inventory_quantity' => DB::raw('inventory_quantity + '.$quantity)]);

      $metadata['inventory_reserved'] = false;
      $metadata['released_at'] = now()->toIso8601String();
      $item->update(['metadata' => $metadata]);
    }
  }
}
