<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Auth\LoginController;
use WebBlocks\Cms\Http\Controllers\Auth\NewPasswordController;
use WebBlocks\Cms\Http\Controllers\Auth\PasswordResetLinkController;
use WebBlocks\Cms\Http\Controllers\Auth\PublicDemoLoginController;
use WebBlocks\Cms\Http\Middleware\ProtectPublicDemoSurface;

Route::middleware(['web', 'guest'])->group(function () {
  Route::get('/webadmin/login', [LoginController::class, 'create'])
    ->middleware(ProtectPublicDemoSurface::class)
    ->name('webblocks.auth.login');
  Route::post('/webadmin/login', [LoginController::class, 'store'])
    ->middleware(ProtectPublicDemoSurface::class, 'throttle:webblocks-auth')
    ->name('webblocks.auth.login.store');

  Route::post('/webadmin/demo', PublicDemoLoginController::class)
    ->middleware('throttle:webblocks-public-demo-login')
    ->name('webblocks.auth.public-demo');

  Route::get('/webadmin/forgot-password', [PasswordResetLinkController::class, 'create'])
    ->middleware(ProtectPublicDemoSurface::class)
    ->name('webblocks.auth.password.request');

  Route::post('/webadmin/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware(ProtectPublicDemoSurface::class, 'throttle:webblocks-auth')
    ->name('webblocks.auth.password.email');

  Route::get('/webadmin/reset-password/{token}', [NewPasswordController::class, 'create'])
    ->middleware(ProtectPublicDemoSurface::class)
    ->name('webblocks.auth.password.reset');

  Route::post('/webadmin/reset-password', [NewPasswordController::class, 'store'])
    ->middleware(ProtectPublicDemoSurface::class, 'throttle:webblocks-auth')
    ->name('webblocks.auth.password.store');
});

Route::middleware(['web', 'auth'])->group(function () {
  Route::post('/webadmin/logout', [LoginController::class, 'destroy'])->name('webblocks.auth.logout');
});
