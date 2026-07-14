<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Currency;

use InvalidArgumentException;

class CurrencyCatalog
{
  /**
   * Payment precision follows the gateway contract. PayPal requires HUF and
   * TWD to be submitted without decimals even though ISO metadata may expose
   * fractional digits for those currencies.
   *
   * @var array<string, array{name: string, digits: int, gateways: array<int, string>}>
   */
  private const CURRENCIES = [
    'AUD' => ['name' => 'Australian dollar', 'digits' => 2, 'gateways' => ['paypal']],
    'BGN' => ['name' => 'Bulgarian lev', 'digits' => 2, 'gateways' => ['sumup']],
    'BRL' => ['name' => 'Brazilian real', 'digits' => 2, 'gateways' => ['paypal', 'sumup']],
    'CAD' => ['name' => 'Canadian dollar', 'digits' => 2, 'gateways' => ['paypal']],
    'CHF' => ['name' => 'Swiss franc', 'digits' => 2, 'gateways' => ['paypal', 'sumup']],
    'CLP' => ['name' => 'Chilean peso', 'digits' => 0, 'gateways' => ['sumup']],
    'CNY' => ['name' => 'Chinese renminbi', 'digits' => 2, 'gateways' => ['paypal']],
    'COP' => ['name' => 'Colombian peso', 'digits' => 2, 'gateways' => ['sumup']],
    'CZK' => ['name' => 'Czech koruna', 'digits' => 2, 'gateways' => ['paypal', 'sumup']],
    'DKK' => ['name' => 'Danish krone', 'digits' => 2, 'gateways' => ['paypal', 'sumup']],
    'EUR' => ['name' => 'Euro', 'digits' => 2, 'gateways' => ['paypal', 'sumup']],
    'GBP' => ['name' => 'Pound sterling', 'digits' => 2, 'gateways' => ['paypal', 'sumup']],
    'HKD' => ['name' => 'Hong Kong dollar', 'digits' => 2, 'gateways' => ['paypal']],
    'HRK' => ['name' => 'Croatian kuna', 'digits' => 2, 'gateways' => ['sumup']],
    'HUF' => ['name' => 'Hungarian forint', 'digits' => 0, 'gateways' => ['paypal', 'sumup']],
    'ILS' => ['name' => 'Israeli new shekel', 'digits' => 2, 'gateways' => ['paypal']],
    'JPY' => ['name' => 'Japanese yen', 'digits' => 0, 'gateways' => ['paypal']],
    'MXN' => ['name' => 'Mexican peso', 'digits' => 2, 'gateways' => ['paypal']],
    'MYR' => ['name' => 'Malaysian ringgit', 'digits' => 2, 'gateways' => ['paypal']],
    'NOK' => ['name' => 'Norwegian krone', 'digits' => 2, 'gateways' => ['paypal', 'sumup']],
    'NZD' => ['name' => 'New Zealand dollar', 'digits' => 2, 'gateways' => ['paypal']],
    'PHP' => ['name' => 'Philippine peso', 'digits' => 2, 'gateways' => ['paypal']],
    'PLN' => ['name' => 'Polish zloty', 'digits' => 2, 'gateways' => ['paypal', 'sumup']],
    'RON' => ['name' => 'Romanian leu', 'digits' => 2, 'gateways' => ['sumup']],
    'RUB' => ['name' => 'Russian ruble', 'digits' => 2, 'gateways' => ['paypal']],
    'SEK' => ['name' => 'Swedish krona', 'digits' => 2, 'gateways' => ['paypal', 'sumup']],
    'SGD' => ['name' => 'Singapore dollar', 'digits' => 2, 'gateways' => ['paypal']],
    'THB' => ['name' => 'Thai baht', 'digits' => 2, 'gateways' => ['paypal']],
    'TWD' => ['name' => 'New Taiwan dollar', 'digits' => 0, 'gateways' => ['paypal']],
    'USD' => ['name' => 'United States dollar', 'digits' => 2, 'gateways' => ['paypal', 'sumup']],
  ];

  /**
   * @return array<int, string>
   */
  public function codes(): array
  {
    return array_keys(self::CURRENCIES);
  }

  /**
   * @return array<int, string>
   */
  public function codesForGateway(string $gateway): array
  {
    $gateway = strtolower(trim($gateway));

    if ($gateway === 'fake') {
      return $this->codes();
    }

    return array_keys(array_filter(
      self::CURRENCIES,
      fn (array $currency): bool => in_array($gateway, $currency['gateways'], true),
    ));
  }

  public function supports(string $gateway, string $currency): bool
  {
    return in_array(strtoupper(trim($currency)), $this->codesForGateway($gateway), true);
  }

  public function fractionDigits(string $currency): int
  {
    return $this->currency($currency)['digits'];
  }

  /**
   * @return array<string, string>
   */
  public function options(bool $includeGatewaySupport = false): array
  {
    $options = [];

    foreach (self::CURRENCIES as $code => $currency) {
      $label = $code.' — '.$currency['name'];

      if ($includeGatewaySupport) {
        $label .= ' ('.implode(', ', array_map('ucfirst', $currency['gateways'])).')';
      }

      $options[$code] = $label;
    }

    return $options;
  }

  /**
   * @return array<string, string>
   */
  public function optionsForGateway(string $gateway): array
  {
    return array_intersect_key($this->options(), array_flip($this->codesForGateway($gateway)));
  }

  /**
   * @return array{name: string, digits: int, gateways: array<int, string>}
   */
  private function currency(string $currency): array
  {
    $code = strtoupper(trim($currency));
    $definition = self::CURRENCIES[$code] ?? null;

    if ($definition === null) {
      throw new InvalidArgumentException("Unsupported currency code: {$code}.");
    }

    return $definition;
  }
}
