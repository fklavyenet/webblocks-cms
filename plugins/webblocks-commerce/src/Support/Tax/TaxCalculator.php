<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Tax;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Country-agnostic VAT calculator driven entirely by config.
 *
 * Rates live in `webblocks-commerce.tax.rates` as integer basis points keyed by
 * ISO country then product tax class, so a new jurisdiction is a config edit,
 * not a code change. All arithmetic is integer minor units with half-up
 * rounding — money never touches a float.
 */
class TaxCalculator
{
  /**
   * Built-in VAT rates (basis points) used when the host app has not published
   * or overridden `webblocks-commerce.tax.rates`. Mirrors the shipped config so
   * the plugin works out of the box, matching how the gateway config falls back
   * to env defaults.
   *
   * @var array<string, array<string, int>>
   */
  private const DEFAULT_RATES = [
    'DE' => ['standard' => 1900, 'reduced' => 700, 'zero' => 0],
    'AT' => ['standard' => 2000, 'reduced' => 1000, 'zero' => 0],
    'FR' => ['standard' => 2000, 'reduced' => 550, 'zero' => 0],
    'NL' => ['standard' => 2100, 'reduced' => 900, 'zero' => 0],
    'IT' => ['standard' => 2200, 'reduced' => 1000, 'zero' => 0],
    'ES' => ['standard' => 2100, 'reduced' => 1000, 'zero' => 0],
  ];

  public function __construct(
    private readonly Config $config,
  ) {}

  public function enabled(): bool
  {
    return (bool) $this->config->get('webblocks-commerce.tax.enabled', env('WEBBLOCKS_COMMERCE_TAX_ENABLED', true));
  }

  public function pricesIncludeTax(): bool
  {
    return (bool) $this->config->get('webblocks-commerce.tax.prices_include_tax', env('WEBBLOCKS_COMMERCE_PRICES_INCLUDE_TAX', true));
  }

  public function storeCountry(): ?string
  {
    $country = $this->config->get('webblocks-commerce.tax.store_country', env('WEBBLOCKS_COMMERCE_TAX_COUNTRY', 'DE'));

    return is_string($country) && $country !== '' ? strtoupper($country) : null;
  }

  /**
   * Resolve the VAT rate (basis points) for a tax class in a country.
   * Unknown country/class combinations resolve to 0 (untaxed) rather than error.
   */
  public function rateBps(string $taxClass, ?string $country = null): int
  {
    if (! $this->enabled()) {
      return 0;
    }

    $country = strtoupper($country ?? (string) $this->storeCountry());
    $rates = $this->config->get('webblocks-commerce.tax.rates') ?: self::DEFAULT_RATES;

    $rate = data_get($rates, $country.'.'.$taxClass);

    return is_numeric($rate) ? max(0, (int) $rate) : 0;
  }

  /**
   * Break a money amount into its net/tax/gross parts for a given tax class.
   *
   * When prices are tax-inclusive the amount IS the gross and tax is extracted
   * from it; otherwise the amount is the net and tax is added on top.
   */
  public function calculate(int $amount, string $taxClass, ?string $country = null): TaxLine
  {
    $country = $country !== null ? strtoupper($country) : $this->storeCountry();
    $rateBps = $this->rateBps($taxClass, $country);
    $inclusive = $this->pricesIncludeTax();

    if ($rateBps === 0) {
      return new TaxLine($amount, 0, $amount, 0, $taxClass, $country, $inclusive);
    }

    if ($inclusive) {
      // gross = amount; net = gross * 10000 / (10000 + rate); tax = gross - net.
      $net = $this->divRound($amount * 10000, 10000 + $rateBps);
      $tax = $amount - $net;

      return new TaxLine($net, $tax, $amount, $rateBps, $taxClass, $country, true);
    }

    // net = amount; tax = net * rate / 10000; gross = net + tax.
    $tax = $this->divRound($amount * $rateBps, 10000);

    return new TaxLine($amount, $tax, $amount + $tax, $rateBps, $taxClass, $country, false);
  }

  /**
   * Integer division with half-up rounding.
   */
  private function divRound(int $numerator, int $denominator): int
  {
    return intdiv($numerator + intdiv($denominator, 2), $denominator);
  }
}
