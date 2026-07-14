<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Currency;

use NumberFormatter;
use RuntimeException;

class MoneyFormatter
{
  public function __construct(
    private readonly CurrencyCatalog $currencies,
  ) {}

  public function format(int $minorUnits, string $currency, ?string $locale = null): string
  {
    $this->requireIntl();

    $currency = strtoupper(trim($currency));
    $digits = $this->currencies->fractionDigits($currency);
    $formatter = new NumberFormatter($this->locale($locale), NumberFormatter::CURRENCY);
    $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $digits);
    $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $digits);
    $formatted = $formatter->formatCurrency((float) $this->majorUnits($minorUnits, $currency), $currency);

    if ($formatted === false) {
      throw new RuntimeException("Unable to format {$currency} for locale {$this->locale($locale)}.");
    }

    return $formatted;
  }

  public function majorUnits(int $minorUnits, string $currency): string
  {
    $digits = $this->currencies->fractionDigits($currency);
    $negative = $minorUnits < 0;
    $absolute = abs($minorUnits);

    if ($digits === 0) {
      return ($negative ? '-' : '').(string) $absolute;
    }

    $divisor = 10 ** $digits;
    $whole = intdiv($absolute, $divisor);
    $fraction = str_pad((string) ($absolute % $divisor), $digits, '0', STR_PAD_LEFT);

    return ($negative ? '-' : '').$whole.'.'.$fraction;
  }

  public function majorUnitsNumber(int $minorUnits, string $currency): int|float
  {
    $value = $this->majorUnits($minorUnits, $currency);

    if (! str_contains($value, '.') || preg_match('/\.0+$/', $value) === 1) {
      return (int) $value;
    }

    return (float) $value;
  }

  private function requireIntl(): void
  {
    if (! class_exists(NumberFormatter::class)) {
      throw new RuntimeException('WebBlocks Commerce currency formatting requires the PHP intl extension.');
    }
  }

  private function locale(?string $locale): string
  {
    $locale = trim((string) ($locale ?: app()->getLocale()));

    return $locale !== '' ? str_replace('-', '_', $locale) : 'en';
  }
}
