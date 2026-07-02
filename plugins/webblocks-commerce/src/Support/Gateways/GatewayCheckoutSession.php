<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways;

class GatewayCheckoutSession
{
  public function __construct(
    public readonly string $id,
    public readonly string $redirectUrl,
    public readonly string $mode,
  ) {}
}
