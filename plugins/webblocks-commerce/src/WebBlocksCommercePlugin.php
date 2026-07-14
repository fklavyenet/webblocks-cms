<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce;

use WebBlocks\Cms\Plugins\WebBlocksCommerce\Console\ExpireStalePendingOrders;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\WebBlocksCommerceHealth;
use WebBlocks\Cms\Support\Plugins\PluginBlockTypeDefinition;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginMenuItem;
use WebBlocks\Cms\Support\Plugins\PluginPermission;
use WebBlocks\Cms\Support\Plugins\PluginSettingsDefinition;

class WebBlocksCommercePlugin
{
  public const HANDLE = 'webblocks-commerce';

  public static function definition(): PluginDefinition
  {
    return PluginDefinition::make(self::HANDLE)
      ->label('WebBlocks Commerce')
      ->version('0.8.3')
      ->provider(self::class)
      ->description('Simple product sales and hosted checkout foundations for WebBlocks CMS sites.')
      ->requiresCms('^1.37.3')
      ->settingsNamespace('webblocks_commerce')
      ->databasePrefix('webblocks_commerce_')
      ->permissions([
        PluginPermission::make('webblocks-commerce.view')
          ->label('View commerce')
          ->description('View commerce setup, products, orders, and payment status.'),
        PluginPermission::make('webblocks-commerce.manage')
          ->label('Manage commerce')
          ->description('Manage commerce setup and settings.'),
        PluginPermission::make('webblocks-commerce.manage-products')
          ->label('Manage commerce products')
          ->description('Create and edit simple commerce products.'),
        PluginPermission::make('webblocks-commerce.manage-orders')
          ->label('Manage commerce orders')
          ->description('View orders and payment attempts.'),
        PluginPermission::make('webblocks-commerce.manage-settings')
          ->label('Manage commerce settings')
          ->description('Configure write-only payment credentials and review checkout gateway readiness.'),
      ])
      ->menu([
        PluginMenuItem::make('commerce-products')
          ->label('Products')
          ->route('webblocks.plugins.webblocks_commerce.products.index')
          ->icon('wb-icon-package')
          ->permission('webblocks-commerce.view')
          ->group('Commerce')
          ->sort(70),
        PluginMenuItem::make('commerce-orders')
          ->label('Orders')
          ->route('webblocks.plugins.webblocks_commerce.orders.index')
          ->icon('wb-icon-receipt')
          ->permission('webblocks-commerce.manage-orders')
          ->group('Commerce')
          ->sort(71),
        PluginMenuItem::make('commerce-settings')
          ->label('Settings')
          ->route('webblocks.plugins.webblocks_commerce.settings.edit')
          ->icon('wb-icon-settings')
          ->permission('webblocks-commerce.manage-settings')
          ->group('Commerce')
          ->sort(72),
      ])
      ->blockTypes([
        PluginBlockTypeDefinition::make('webblocks-commerce::buy-button')
          ->label('Commerce Buy Button')
          ->description('Adds a selected WebBlocks Commerce product to the public shopping cart.')
          ->adminView('webblocks-cms::admin.blocks.types.webblocks-commerce-buy-button')
          ->publicView('webblocks-cms::pages.partials.blocks.webblocks-commerce-buy-button')
          ->metadata([
            'catalog_slug' => 'webblocks-commerce-buy-button',
          ]),
      ])
      ->adminRoutes(dirname(__DIR__).'/routes/webblocks-commerce.php')
      ->apiRoutes(dirname(__DIR__).'/routes/api.php')
      ->apiDiscovery(self::apiDiscovery())
      ->apiCapabilities([
        'group' => [
          'key' => 'commerce',
          'label' => 'Commerce',
          'description' => 'Manage products and translations, drive carts and checkout, and read Commerce orders.',
        ],
        'capabilities' => [
          'commerce.read' => 'Read commerce product catalog records',
          'commerce.products.write' => 'Create or update commerce products and translations',
          'commerce.orders.read' => 'Read commerce order records',
          'commerce.cart.read' => 'Read commerce carts',
          'commerce.cart.write' => 'Create and modify commerce carts and start checkout',
        ],
      ])
      ->commands([
        ExpireStalePendingOrders::class,
      ])
      ->migrations([
        'database/migrations',
      ])
      ->settings(
        PluginSettingsDefinition::make('webblocks.plugins.webblocks_commerce.settings.edit')
          ->label('Commerce Settings')
          ->description('Choose no-payment test orders, PayPal, or SumUp; set the default currency; and review checkout readiness. Environment configuration remains an optional override.')
      )
      ->health(WebBlocksCommerceHealth::class);
  }

  /**
   * API discovery contribution so AI agents can self-discover the plugin's
   * endpoints. Only surfaced while the plugin is enabled.
   *
   * @return array<string, mixed>
   */
  private static function apiDiscovery(): array
  {
    $json = ['application/json' => ['schema' => ['type' => 'object']]];

    return [
      'resources' => [
        'commerce_products' => '/webadmin/api/commerce/products',
        'commerce_product' => '/webadmin/api/commerce/products/{product}',
        'commerce_product_translations' => '/webadmin/api/commerce/products/{product}/translations',
        'commerce_product_translation' => '/webadmin/api/commerce/products/{product}/translations/{locale}',
        'commerce_orders' => '/webadmin/api/commerce/orders',
        'commerce_order' => '/webadmin/api/commerce/orders/{order}',
        'commerce_cart_create' => '/webadmin/api/commerce/cart',
        'commerce_cart' => '/webadmin/api/commerce/cart/{cart}',
        'commerce_cart_items' => '/webadmin/api/commerce/cart/{cart}/items',
        'commerce_cart_item' => '/webadmin/api/commerce/cart/{cart}/items/{product}',
        'commerce_cart_checkout' => '/webadmin/api/commerce/cart/{cart}/checkout',
      ],
      'openapi_paths' => [
        '/commerce/products' => [
          'get' => ['summary' => 'List Commerce products', 'x-required-capability' => 'commerce.read', 'responses' => ['200' => ['description' => 'Commerce products JSON', 'content' => $json]]],
          'post' => ['summary' => 'Create a Commerce product', 'x-required-capability' => 'commerce.products.write', 'x-required-fields' => ['title', 'slug', 'status', 'price_amount', 'currency'], 'x-optional-fields' => ['tax_class'], 'responses' => ['201' => ['description' => 'Created Commerce product JSON', 'content' => $json], '422' => ['description' => 'Validation JSON', 'content' => $json]]],
        ],
        '/commerce/products/{product}' => ['patch' => ['summary' => 'Update a Commerce product (incl. tax_class)', 'x-required-capability' => 'commerce.products.write', 'responses' => ['200' => ['description' => 'Updated Commerce product JSON', 'content' => $json], '404' => ['description' => 'Product not found JSON', 'content' => $json]]]],
        '/commerce/products/{product}/translations' => ['get' => ['summary' => 'List product translations', 'x-required-capability' => 'commerce.read', 'responses' => ['200' => ['description' => 'Translations JSON', 'content' => $json]]]],
        '/commerce/products/{product}/translations/{locale}' => [
          'put' => ['summary' => 'Upsert a product translation for a CMS locale', 'x-required-capability' => 'commerce.products.write', 'x-optional-fields' => ['title', 'description'], 'responses' => ['200' => ['description' => 'Translation JSON', 'content' => $json], '404' => ['description' => 'Product or locale not found JSON', 'content' => $json]]],
          'delete' => ['summary' => 'Delete a product translation', 'x-required-capability' => 'commerce.products.write', 'responses' => ['200' => ['description' => 'Deletion JSON', 'content' => $json]]],
        ],
        '/commerce/orders' => ['get' => ['summary' => 'List Commerce orders (with tax breakdown)', 'x-required-capability' => 'commerce.orders.read', 'responses' => ['200' => ['description' => 'Commerce orders JSON', 'content' => $json]]]],
        '/commerce/orders/{order}' => ['get' => ['summary' => 'Read a Commerce order (with tax breakdown)', 'x-required-capability' => 'commerce.orders.read', 'responses' => ['200' => ['description' => 'Commerce order JSON', 'content' => $json], '404' => ['description' => 'Order not found JSON', 'content' => $json]]]],
        '/commerce/cart' => ['post' => ['summary' => 'Create a cart', 'x-required-capability' => 'commerce.cart.write', 'x-optional-fields' => ['site_id', 'locale', 'currency'], 'responses' => ['201' => ['description' => 'Cart JSON', 'content' => $json]]]],
        '/commerce/cart/{cart}' => ['get' => ['summary' => 'Read a cart with live totals', 'x-required-capability' => 'commerce.cart.read', 'responses' => ['200' => ['description' => 'Cart JSON', 'content' => $json], '404' => ['description' => 'Cart not found JSON', 'content' => $json]]]],
        '/commerce/cart/{cart}/items' => [
          'post' => ['summary' => 'Add a product to the cart', 'x-required-capability' => 'commerce.cart.write', 'x-required-fields' => ['product_id'], 'x-optional-fields' => ['quantity'], 'responses' => ['200' => ['description' => 'Cart JSON', 'content' => $json], '422' => ['description' => 'Cart rejected JSON', 'content' => $json]]],
          'delete' => ['summary' => 'Clear the cart', 'x-required-capability' => 'commerce.cart.write', 'responses' => ['200' => ['description' => 'Cart JSON', 'content' => $json]]],
        ],
        '/commerce/cart/{cart}/items/{product}' => [
          'patch' => ['summary' => 'Set a cart line quantity (0 removes)', 'x-required-capability' => 'commerce.cart.write', 'x-required-fields' => ['quantity'], 'responses' => ['200' => ['description' => 'Cart JSON', 'content' => $json]]],
          'delete' => ['summary' => 'Remove a cart line', 'x-required-capability' => 'commerce.cart.write', 'responses' => ['200' => ['description' => 'Cart JSON', 'content' => $json]]],
        ],
        '/commerce/cart/{cart}/checkout' => ['post' => ['summary' => 'Start hosted checkout for a cart', 'x-required-capability' => 'commerce.cart.write', 'x-optional-fields' => ['customer_email'], 'responses' => ['201' => ['description' => 'Checkout redirect + order JSON', 'content' => $json], '409' => ['description' => 'Checkout unavailable JSON', 'content' => $json]]]],
      ],
      'guidance' => [
        'For WebBlocks Commerce, create products with POST /webadmin/api/commerce/products (set tax_class), add per-locale content with PUT /webadmin/api/commerce/products/{product}/translations/{locale}, then either place a `webblocks-commerce-buy-button` block or drive a cart: POST /webadmin/api/commerce/cart, add items, and POST .../checkout to get a hosted-checkout redirect_url. Orders and prices are VAT-aware; amounts are integer minor units.',
      ],
    ];
  }
}
