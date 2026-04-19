<?php

use App\Http\Controllers\Api\Updates\LatestReleaseController;
use App\Http\Controllers\Api\Updates\PublishReleaseController;
use App\Http\Controllers\Api\Updates\ReleaseController;
use App\Http\Controllers\Api\Updates\UpdateServiceController;
use App\Http\Middleware\EnsureReleasePublishAuthorized;
use App\Http\Middleware\EnsureUpdateServerEnabled;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureUpdateServerEnabled::class)
    ->prefix('updates')
    ->name('api.updates.')
    ->group(function (): void {
        Route::get('/', UpdateServiceController::class)->name('index');
        Route::get('{product}/latest', LatestReleaseController::class)
            ->where('product', '[A-Za-z0-9\-]+')
            ->name('latest');
        Route::get('{product}/releases', [ReleaseController::class, 'index'])
            ->where('product', '[A-Za-z0-9\-]+')
            ->name('releases.index');
        Route::get('{product}/releases/{version}', [ReleaseController::class, 'show'])
            ->where('product', '[A-Za-z0-9\-]+')
            ->where('version', '[0-9A-Za-z\.\-\+]+')
            ->name('releases.show');
    });

Route::middleware([EnsureUpdateServerEnabled::class, EnsureReleasePublishAuthorized::class])
    ->prefix('updates')
    ->name('api.updates.')
    ->group(function (): void {
        Route::post('publish', PublishReleaseController::class)->name('publish');
    });
