<?php

return [
  'enabled' => env('WEBBLOCKS_COMMERCE_ENABLED', false),
  'default_currency' => env('WEBBLOCKS_COMMERCE_DEFAULT_CURRENCY', 'USD'),
  'gateway' => env('WEBBLOCKS_COMMERCE_GATEWAY', 'paypal'),

  'paypal' => [
    'mode' => env('WEBBLOCKS_COMMERCE_PAYPAL_MODE', 'sandbox'),
    'client_id' => env('WEBBLOCKS_COMMERCE_PAYPAL_CLIENT_ID'),
    'client_secret' => env('WEBBLOCKS_COMMERCE_PAYPAL_CLIENT_SECRET'),
    'webhook_id' => env('WEBBLOCKS_COMMERCE_PAYPAL_WEBHOOK_ID'),
  ],

  /*
  |----------------------------------------------------------------------------
  | VAT / tax
  |----------------------------------------------------------------------------
  | Country-agnostic, rate-driven VAT. Rates are integer basis points
  | (1900 = 19.00%) keyed by ISO-3166 country then by product tax class, so any
  | jurisdiction can be added without code changes.
  |
  | `prices_include_tax` = true means catalog prices are gross (VAT-inclusive),
  | the EU B2C norm: tax is broken out of the shown price rather than added on
  | top, so the amount charged equals the catalog price. Set it false for net
  | (tax-exclusive) pricing where VAT is added at checkout.
  |
  | `store_country` is the jurisdiction whose VAT applies. Selling B2C below the
  | EU-wide OSS distance-selling threshold, you charge your own country's VAT, so
  | this is normally your store's country.
  */
  'tax' => [
    'enabled' => env('WEBBLOCKS_COMMERCE_TAX_ENABLED', true),
    'prices_include_tax' => env('WEBBLOCKS_COMMERCE_PRICES_INCLUDE_TAX', true),
    'store_country' => env('WEBBLOCKS_COMMERCE_TAX_COUNTRY', 'DE'),
    'rates' => [
      'DE' => ['standard' => 1900, 'reduced' => 700, 'zero' => 0],
      'AT' => ['standard' => 2000, 'reduced' => 1000, 'zero' => 0],
      'FR' => ['standard' => 2000, 'reduced' => 550, 'zero' => 0],
      'NL' => ['standard' => 2100, 'reduced' => 900, 'zero' => 0],
      'IT' => ['standard' => 2200, 'reduced' => 1000, 'zero' => 0],
      'ES' => ['standard' => 2100, 'reduced' => 1000, 'zero' => 0],
    ],
  ],
];
