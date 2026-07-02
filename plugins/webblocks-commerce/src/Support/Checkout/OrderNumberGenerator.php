<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout;

use Illuminate\Support\Str;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;

class OrderNumberGenerator
{
  public function generate(): string
  {
    do {
      $number = 'WB-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    } while (CommerceOrder::query()->where('order_number', $number)->exists());

    return $number;
  }
}
