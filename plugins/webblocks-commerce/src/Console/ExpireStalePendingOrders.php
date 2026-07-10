<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Orders\OrderStateMachine;

/**
 * Expire abandoned pending checkouts and release the stock they were holding.
 *
 * Reserved inventory is only returned to the catalog when an order leaves the
 * pending state, so a checkout the buyer never completes would otherwise hold
 * its reservation forever. Run this on a schedule, e.g. in the host app's
 * console kernel:
 *
 *   $schedule->command('webblocks-commerce:expire-stale-orders')->everyFifteenMinutes();
 */
class ExpireStalePendingOrders extends Command
{
  protected $signature = 'webblocks-commerce:expire-stale-orders
    {--minutes=30 : Age in minutes after which a pending order is expired}';

  protected $description = 'Expire pending commerce orders older than the given age and release their reserved stock.';

  public function handle(OrderStateMachine $orders): int
  {
    $minutes = max(1, (int) $this->option('minutes'));
    $cutoff = now()->subMinutes($minutes);

    $expired = 0;

    CommerceOrder::query()
      ->where('status', CommerceOrder::STATUS_PENDING)
      ->where('created_at', '<', $cutoff)
      ->orderBy('id')
      ->chunkById(100, function ($staleOrders) use ($orders, &$expired): void {
        foreach ($staleOrders as $order) {
          $orders->expire($order);
          $expired++;
        }
      });

    $this->info(sprintf('Expired %d stale pending order(s) older than %d minute(s).', $expired, $minutes));

    return self::SUCCESS;
  }
}
