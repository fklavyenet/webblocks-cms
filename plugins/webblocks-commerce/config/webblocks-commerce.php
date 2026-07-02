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
];
