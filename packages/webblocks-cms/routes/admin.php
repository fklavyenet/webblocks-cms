<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Admin\IconCatalogController;
use WebBlocks\Cms\Http\Controllers\Admin\PackageAdminStatusController;

Route::middleware(['web', 'install.required', 'auth', 'admin.access'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::middleware('can:access-system')->group(function () {
            Route::get('system/icons', [IconCatalogController::class, 'index'])->name('system.icons.index');
            Route::put('system/icons/{iconCatalogItem}', [IconCatalogController::class, 'update'])->name('system.icons.update');
        });

        if (config('webblocks-cms.admin.load_status_route', false)) {
            Route::middleware('can:access-system')
                ->prefix('_webblocks-cms')
                ->name('webblocks-cms.')
                ->group(function () {
                    Route::get('/runtime-status', PackageAdminStatusController::class)
                        ->name('runtime-status');
                });
        }
    });
