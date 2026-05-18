<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Admin\PackageAdminStatusController;

Route::middleware(['install.required', 'auth', 'admin.access', 'can:access-system'])
    ->prefix('admin/_webblocks-cms')
    ->name('admin.webblocks-cms.')
    ->group(function () {
        Route::get('/runtime-status', PackageAdminStatusController::class)
            ->name('runtime-status');
    });
