<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

Route::get(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_PATH, static function () {
    return response()->json([
        'package' => WebBlocksCmsServiceProvider::PACKAGE_NAME,
        'diagnostic' => 'package-route-boundary',
        'root_runtime_authoritative' => true,
    ]);
})->name(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_NAME);
