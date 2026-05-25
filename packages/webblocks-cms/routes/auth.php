<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Auth\LoginController;

Route::middleware(['web', 'guest'])->group(function () {
  Route::get('/webadmin/login', [LoginController::class, 'create'])->name('login');
  Route::post('/webadmin/login', [LoginController::class, 'store']);
});

Route::middleware(['web', 'auth'])->group(function () {
  Route::post('/webadmin/logout', [LoginController::class, 'destroy'])->name('logout');
});
