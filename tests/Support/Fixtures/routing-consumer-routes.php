<?php

use App\Http\Controllers\RoutingConsumerController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RoutingConsumerController::class, 'show'])->name('home');
foreach (['settings', 'de', 'en', 'products', 'products/{id}', 'account/settings', 'orders/{order}/items/{item}'] as $index => $path) {
  Route::get($path, [RoutingConsumerController::class, 'show'])->name('host.probe.'.$index);
}
Route::get('/host-missing', [RoutingConsumerController::class, 'missing'])->name('host.missing');
