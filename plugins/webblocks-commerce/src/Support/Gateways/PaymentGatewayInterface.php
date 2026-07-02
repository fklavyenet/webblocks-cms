<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways;

use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;

interface PaymentGatewayInterface
{
  public function createCheckoutSession(CommerceOrder $order, CommerceProduct $product): GatewayCheckoutSession;
}
