<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers\Api\Concerns\RespondsWithCommerceApiEnvelope;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;

/**
 * Plugin-owned order API (migrated from the CMS core InternalCommerceController).
 * Read-only, matching the read-only admin order screens, but now exposes the full
 * net/tax/gross breakdown snapshotted on each order and line.
 */
class CommerceOrderApiController extends Controller
{
  use RespondsWithCommerceApiEnvelope;

  private const TABLES = [
    'webblocks_commerce_orders',
    'webblocks_commerce_order_items',
    'webblocks_commerce_payments',
  ];

  public function index(Request $request): JsonResponse
  {
    if ($unavailable = $this->requireTables(self::TABLES)) {
      return $unavailable;
    }

    $query = CommerceOrder::query()->with(['site', 'items', 'payments']);

    foreach (['status', 'gateway', 'site_id'] as $field) {
      if ($request->filled($field)) {
        $query->where($field, $field === 'site_id' ? (int) $request->query($field) : (string) $request->query($field));
      }
    }

    if ($request->filled('search')) {
      $search = trim((string) $request->query('search'));
      $query->where(function ($query) use ($search): void {
        $query
          ->where('order_number', 'like', '%'.$search.'%')
          ->orWhere('customer_email', 'like', '%'.$search.'%')
          ->orWhere('gateway_checkout_id', 'like', '%'.$search.'%')
          ->orWhere('gateway_payment_id', 'like', '%'.$search.'%');
      });
    }

    $orders = $query
      ->orderByDesc('created_at')
      ->orderByDesc('id')
      ->limit(min(max((int) $request->query('limit', 50), 1), 100))
      ->get()
      ->map(fn (CommerceOrder $order): array => $this->payload($order))
      ->values()
      ->all();

    return $this->ok(['orders' => $orders]);
  }

  public function show(string $order): JsonResponse
  {
    if ($unavailable = $this->requireTables(self::TABLES)) {
      return $unavailable;
    }

    $record = CommerceOrder::query()->with(['site', 'items', 'payments'])->whereKey($order)->first();

    if ($record === null) {
      return $this->apiError('commerce_order_not_found', 'The requested commerce order was not found.', 404);
    }

    return $this->ok(['order' => $this->payload($record)]);
  }

  /**
   * @return array<string, mixed>
   */
  private function payload(CommerceOrder $order): array
  {
    return [
      'id' => $order->id,
      'site_id' => $order->site_id,
      'site_handle' => $order->relationLoaded('site') ? $order->site?->handle : null,
      'order_number' => $order->order_number,
      'customer_email' => $order->customer_email,
      'status' => $order->status,
      'subtotal_amount' => (int) $order->subtotal_amount,
      'tax_amount' => (int) $order->tax_amount,
      'tax_rate' => (int) $order->tax_rate,
      'tax_country' => $order->tax_country,
      'prices_include_tax' => (bool) $order->prices_include_tax,
      'total_amount' => (int) $order->total_amount,
      'currency' => $order->currency,
      'gateway' => $order->gateway,
      'gateway_checkout_id' => $order->gateway_checkout_id,
      'gateway_payment_id' => $order->gateway_payment_id,
      'placed_at' => $order->placed_at?->toIso8601String(),
      'paid_at' => $order->paid_at?->toIso8601String(),
      'cancelled_at' => $order->cancelled_at?->toIso8601String(),
      'metadata' => $order->metadata ?? [],
      'items' => $order->relationLoaded('items')
        ? $order->items->map(fn ($item): array => [
          'id' => $item->id,
          'product_id' => $item->product_id,
          'title' => $item->title,
          'sku' => $item->sku,
          'quantity' => (int) $item->quantity,
          'unit_amount' => (int) $item->unit_amount,
          'tax_amount' => (int) $item->tax_amount,
          'tax_rate' => (int) $item->tax_rate,
          'tax_class' => $item->tax_class,
          'total_amount' => (int) $item->total_amount,
          'currency' => $item->currency,
        ])->values()->all()
        : [],
      'payments' => $order->relationLoaded('payments')
        ? $order->payments->map(fn ($payment): array => [
          'id' => $payment->id,
          'gateway' => $payment->gateway,
          'gateway_payment_id' => $payment->gateway_payment_id,
          'gateway_checkout_id' => $payment->gateway_checkout_id,
          'status' => $payment->status,
          'amount' => (int) $payment->amount,
          'currency' => $payment->currency,
        ])->values()->all()
        : [],
      'created_at' => $order->created_at?->toIso8601String(),
      'updated_at' => $order->updated_at?->toIso8601String(),
    ];
  }
}
