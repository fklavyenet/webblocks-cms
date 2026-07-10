<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways;

use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;

interface PaymentGatewayInterface
{
  /**
   * Start a hosted checkout session for a fully-built pending order. The order
   * already carries the authoritative net/tax/gross totals and its line items,
   * so gateways derive the charge from the order alone — this works equally for
   * single-product and multi-line cart checkouts.
   */
  public function createCheckoutSession(CommerceOrder $order): GatewayCheckoutSession;
}
