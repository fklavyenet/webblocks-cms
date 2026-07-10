<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Tax;

/**
 * The immutable result of a tax calculation for a single money amount.
 *
 * All amounts are integer minor units (e.g. cents). `net + tax === gross`
 * always holds, so callers can snapshot the breakdown onto an order without
 * re-deriving anything.
 */
class TaxLine
{
  public function __construct(
    public readonly int $net,
    public readonly int $tax,
    public readonly int $gross,
    public readonly int $rateBps,
    public readonly string $taxClass,
    public readonly ?string $country,
    public readonly bool $pricesIncludeTax,
  ) {}
}
