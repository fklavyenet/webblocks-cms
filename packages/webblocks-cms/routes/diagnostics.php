<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Diagnostics\PackageDiagnosticsController;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

Route::get(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_PATH, [PackageDiagnosticsController::class, 'show'])
    ->name(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_NAME);
