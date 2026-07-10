<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers\Api\CommerceCartApiController;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers\Api\CommerceOrderApiController;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers\Api\CommerceProductApiController;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers\Api\CommerceProductTranslationApiController;

/*
| Plugin-owned internal API routes. Mounted inside the CMS internal API group
| (/webadmin/api, token auth) by PluginApiRouteRegistrar, so route names are
| prefixed with `internal-content-api.` and paths with `webadmin/api`.
*/

// Products (migrated from the CMS core commerce controller into the plugin).
Route::get('/commerce/products', [CommerceProductApiController::class, 'index'])
  ->middleware('internal-api.capability:commerce.read')
  ->name('commerce.products.index');

Route::post('/commerce/products', [CommerceProductApiController::class, 'store'])
  ->middleware('internal-api.capability:commerce.products.write')
  ->name('commerce.products.store');

Route::patch('/commerce/products/{product}', [CommerceProductApiController::class, 'update'])
  ->middleware('internal-api.capability:commerce.products.write')
  ->name('commerce.products.update');

// Orders (read-only, matching the read-only admin order screens).
Route::get('/commerce/orders', [CommerceOrderApiController::class, 'index'])
  ->middleware('internal-api.capability:commerce.orders.read')
  ->name('commerce.orders.index');

Route::get('/commerce/orders/{order}', [CommerceOrderApiController::class, 'show'])
  ->middleware('internal-api.capability:commerce.orders.read')
  ->name('commerce.orders.show');

Route::post('/commerce/cart', [CommerceCartApiController::class, 'store'])
  ->middleware('internal-api.capability:commerce.cart.write')
  ->name('commerce.cart.store');

Route::get('/commerce/cart/{cart}', [CommerceCartApiController::class, 'show'])
  ->middleware('internal-api.capability:commerce.cart.read')
  ->name('commerce.cart.show');

Route::post('/commerce/cart/{cart}/items', [CommerceCartApiController::class, 'addItem'])
  ->middleware('internal-api.capability:commerce.cart.write')
  ->name('commerce.cart.items.store');

Route::patch('/commerce/cart/{cart}/items/{product}', [CommerceCartApiController::class, 'updateItem'])
  ->middleware('internal-api.capability:commerce.cart.write')
  ->name('commerce.cart.items.update');

Route::delete('/commerce/cart/{cart}/items/{product}', [CommerceCartApiController::class, 'removeItem'])
  ->middleware('internal-api.capability:commerce.cart.write')
  ->name('commerce.cart.items.destroy');

Route::delete('/commerce/cart/{cart}/items', [CommerceCartApiController::class, 'clear'])
  ->middleware('internal-api.capability:commerce.cart.write')
  ->name('commerce.cart.items.clear');

Route::post('/commerce/cart/{cart}/checkout', [CommerceCartApiController::class, 'checkout'])
  ->middleware('internal-api.capability:commerce.cart.write')
  ->name('commerce.cart.checkout');

// Per-locale product content (storefront shares the CMS Site+Locale system).
Route::get('/commerce/products/{product}/translations', [CommerceProductTranslationApiController::class, 'index'])
  ->middleware('internal-api.capability:commerce.read')
  ->name('commerce.products.translations.index');

Route::put('/commerce/products/{product}/translations/{locale}', [CommerceProductTranslationApiController::class, 'upsert'])
  ->middleware('internal-api.capability:commerce.products.write')
  ->name('commerce.products.translations.upsert');

Route::delete('/commerce/products/{product}/translations/{locale}', [CommerceProductTranslationApiController::class, 'destroy'])
  ->middleware('internal-api.capability:commerce.products.write')
  ->name('commerce.products.translations.destroy');
