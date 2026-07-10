<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Orders;

use RuntimeException;

class InvalidOrderTransitionException extends RuntimeException
{
  public function __construct(
    public readonly string $from,
    public readonly string $to,
  ) {
    parent::__construct(sprintf('Commerce order cannot transition from "%s" to "%s".', $from, $to));
  }
}
