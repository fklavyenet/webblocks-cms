<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Auth\LoginController;

Route::middleware(['web', 'guest'])->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/admin/login', fn () => redirect()->route('login'))->name('admin.login');
    Route::post('/admin/login', [LoginController::class, 'store'])->name('admin.login.store');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/admin/logout', [LoginController::class, 'destroy'])->name('admin.logout');
});
