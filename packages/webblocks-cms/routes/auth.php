<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Auth\LoginController;

Route::middleware(['web', 'guest'])->group(function () {
    Route::get('/cms/login', [LoginController::class, 'create'])->name('login');
    Route::post('/cms/login', [LoginController::class, 'store']);
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/cms/logout', [LoginController::class, 'destroy'])->name('logout');
});
