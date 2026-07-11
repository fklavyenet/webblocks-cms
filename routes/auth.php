<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Auth\LoginController;
use WebBlocks\Cms\Http\Controllers\Auth\NewPasswordController;
use WebBlocks\Cms\Http\Controllers\Auth\PasswordResetLinkController;

Route::middleware(['web', 'guest'])->group(function () {
  Route::get('/webadmin/login', [LoginController::class, 'create'])->name('webblocks.auth.login');
  Route::post('/webadmin/login', [LoginController::class, 'store'])
    ->middleware('throttle:webblocks-auth')
    ->name('webblocks.auth.login.store');

  Route::get('/webadmin/forgot-password', [PasswordResetLinkController::class, 'create'])
    ->name('webblocks.auth.password.request');

  Route::post('/webadmin/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('throttle:webblocks-auth')
    ->name('webblocks.auth.password.email');

  Route::get('/webadmin/reset-password/{token}', [NewPasswordController::class, 'create'])
    ->name('webblocks.auth.password.reset');

  Route::post('/webadmin/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('throttle:webblocks-auth')
    ->name('webblocks.auth.password.store');
});

Route::middleware(['web', 'auth'])->group(function () {
  Route::post('/webadmin/logout', [LoginController::class, 'destroy'])->name('webblocks.auth.logout');
});
