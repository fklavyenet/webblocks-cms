<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Public\PackagePublicStatusController;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

Route::middleware('install.required')
    ->get(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_PATH, PackagePublicStatusController::class)
    ->name(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_NAME);
