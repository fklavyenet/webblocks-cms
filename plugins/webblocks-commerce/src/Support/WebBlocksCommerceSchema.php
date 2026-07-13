<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support;

use Illuminate\Support\Facades\Schema;

class WebBlocksCommerceSchema
{
  /**
   * @return array<int, string>
   */
  public function requiredTables(): array
  {
    return [
      'webblocks_commerce_products',
      'webblocks_commerce_orders',
      'webblocks_commerce_order_items',
      'webblocks_commerce_payments',
      'webblocks_commerce_webhook_events',
      'webblocks_commerce_settings',
    ];
  }

  /**
   * @return array<int, string>
   */
  public function missingTables(): array
  {
    return array_values(array_filter(
      $this->requiredTables(),
      fn (string $table): bool => ! Schema::hasTable($table)
    ));
  }

  public function isReady(): bool
  {
    return $this->missingTables() === [];
  }

  public function message(): string
  {
    $missing = $this->missingTables();

    if ($missing === []) {
      return 'Commerce tables are ready.';
    }

    return 'Setup required. Plugin migrations pending. Commerce tables are missing: '.implode(', ', $missing).'.';
  }
}
