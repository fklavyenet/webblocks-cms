<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers\CommerceOrderController;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers\CommerceProductController;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers\CommerceSettingsController;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Middleware\ProtectCommerceCredentialInput;

Route::middleware('plugin.permission:webblocks-commerce.view')->group(function (): void {
  Route::get('/products', [CommerceProductController::class, 'index'])->name('products.index');
  Route::get('/products/create', [CommerceProductController::class, 'create'])
    ->middleware('plugin.permission:webblocks-commerce.manage-products')
    ->name('products.create');
  Route::get('/products/{product}', [CommerceProductController::class, 'show'])->name('products.show');
  Route::get('/products/{product}/edit', [CommerceProductController::class, 'edit'])
    ->middleware('plugin.permission:webblocks-commerce.manage-products')
    ->name('products.edit');
});

Route::middleware('plugin.permission:webblocks-commerce.manage-products')->group(function (): void {
  Route::post('/products', [CommerceProductController::class, 'store'])->name('products.store');
  Route::put('/products/{product}', [CommerceProductController::class, 'update'])->name('products.update');
  Route::post('/products/{product}/archive', [CommerceProductController::class, 'archive'])->name('products.archive');
});

Route::middleware('plugin.permission:webblocks-commerce.manage-orders')->group(function (): void {
  Route::get('/orders', [CommerceOrderController::class, 'index'])->name('orders.index');
  Route::get('/orders/{order}', [CommerceOrderController::class, 'show'])->name('orders.show');
});

Route::middleware([
  ProtectCommerceCredentialInput::class,
  'plugin.permission:webblocks-commerce.manage-settings',
])->group(function (): void {
  Route::get('/settings', [CommerceSettingsController::class, 'edit'])->name('settings.edit');
  Route::put('/settings', [CommerceSettingsController::class, 'update'])->name('settings.update');
});
