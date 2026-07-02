<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;

class FakeCheckoutGateway implements PaymentGatewayInterface
{
  public function createCheckoutSession(CommerceOrder $order, CommerceProduct $product): GatewayCheckoutSession
  {
    $sessionId = 'fake_'.Str::lower(Str::random(24));

    return new GatewayCheckoutSession(
      id: $sessionId,
      redirectUrl: URL::temporarySignedRoute('webblocks.commerce.checkout.success', now()->addMinutes(30), [
        'order' => $order->id,
        'checkout_id' => $sessionId,
      ]),
      mode: 'fake',
    );
  }
}
