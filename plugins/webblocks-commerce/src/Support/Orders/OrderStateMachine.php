<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Orders;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Inventory\InventoryManager;

/**
 * The single guarded entry point for changing a commerce order's status.
 *
 * Order status is the money/state backbone of the plugin, so it must never be
 * mutated with a raw `->update(['status' => ...])`. Every transition goes
 * through here, which:
 *   - enforces the allowed transition graph (rejects illegal jumps),
 *   - is idempotent (re-delivered webhooks that land on the current state no-op),
 *   - locks the row so racing webhooks cannot both apply a transition,
 *   - releases reserved inventory when an order leaves the "holds stock" states.
 *
 * Allowed graph:
 *   pending -> paid | failed | cancelled | expired
 *   paid    -> refunded
 *   (failed | cancelled | expired | refunded are terminal)
 */
class OrderStateMachine
{
  /**
   * @var array<string, list<string>>
   */
  private const TRANSITIONS = [
    CommerceOrder::STATUS_PENDING => [
      CommerceOrder::STATUS_PAID,
      CommerceOrder::STATUS_FAILED,
      CommerceOrder::STATUS_CANCELLED,
      CommerceOrder::STATUS_EXPIRED,
    ],
    CommerceOrder::STATUS_PAID => [
      CommerceOrder::STATUS_REFUNDED,
    ],
    CommerceOrder::STATUS_FAILED => [],
    CommerceOrder::STATUS_CANCELLED => [],
    CommerceOrder::STATUS_EXPIRED => [],
    CommerceOrder::STATUS_REFUNDED => [],
  ];

  public function __construct(
    private readonly InventoryManager $inventory,
  ) {}

  public function canTransition(string $from, string $to): bool
  {
    return in_array($to, self::TRANSITIONS[$from] ?? [], true);
  }

  /**
   * @param  array<string, mixed>  $attributes
   */
  public function markPaid(CommerceOrder $order, array $attributes = []): CommerceOrder
  {
    return $this->transition($order, CommerceOrder::STATUS_PAID, $attributes + [
      'paid_at' => $order->paid_at ?? now(),
    ]);
  }

  /**
   * @param  array<string, mixed>  $attributes
   */
  public function markFailed(CommerceOrder $order, array $attributes = []): CommerceOrder
  {
    return $this->transition($order, CommerceOrder::STATUS_FAILED, $attributes, releaseInventory: true);
  }

  /**
   * @param  array<string, mixed>  $attributes
   */
  public function cancel(CommerceOrder $order, array $attributes = []): CommerceOrder
  {
    return $this->transition($order, CommerceOrder::STATUS_CANCELLED, $attributes + [
      'cancelled_at' => $order->cancelled_at ?? now(),
    ], releaseInventory: true);
  }

  /**
   * @param  array<string, mixed>  $attributes
   */
  public function expire(CommerceOrder $order, array $attributes = []): CommerceOrder
  {
    return $this->transition($order, CommerceOrder::STATUS_EXPIRED, $attributes + [
      'cancelled_at' => $order->cancelled_at ?? now(),
    ], releaseInventory: true);
  }

  /**
   * @param  array<string, mixed>  $attributes
   */
  public function refund(CommerceOrder $order, array $attributes = []): CommerceOrder
  {
    return $this->transition($order, CommerceOrder::STATUS_REFUNDED, $attributes, releaseInventory: true);
  }

  /**
   * @param  array<string, mixed>  $attributes
   */
  private function transition(
    CommerceOrder $order,
    string $to,
    array $attributes = [],
    bool $releaseInventory = false,
  ): CommerceOrder {
    // Fast idempotent no-op before touching the database: a re-delivered webhook
    // that lands on the state the order is already in is a success, not an error.
    if ($order->status === $to) {
      return $order;
    }

    if (! $this->canTransition($order->status, $to)) {
      throw new InvalidOrderTransitionException($order->status, $to);
    }

    return DB::transaction(function () use ($order, $to, $attributes, $releaseInventory): CommerceOrder {
      $locked = CommerceOrder::query()
        ->whereKey($order->getKey())
        ->lockForUpdate()
        ->firstOrFail();

      // Re-check under the row lock: another request may have won the race
      // between our pre-check and acquiring the lock.
      if ($locked->status === $to) {
        $order->setRawAttributes($locked->getAttributes(), sync: true);

        return $order;
      }

      if (! $this->canTransition($locked->status, $to)) {
        throw new InvalidOrderTransitionException($locked->status, $to);
      }

      $locked->fill(['status' => $to] + $attributes)->save();

      if ($releaseInventory) {
        $this->inventory->releaseForOrder($locked);
      }

      // Keep the caller's instance in sync with what was persisted.
      $order->setRawAttributes($locked->getAttributes(), sync: true);

      return $order;
    });
  }
}
