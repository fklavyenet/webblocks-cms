<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Admin\PluginRecoveryController;
use WebBlocks\Cms\Http\Controllers\Auth\LoginController;
use WebBlocks\Cms\Http\Middleware\RequirePluginRecoveryAccess;
use WebBlocks\Cms\Http\Middleware\UseAdminLocale;

Route::middleware(['web'])->prefix('webadmin/plugin-recovery')->name('admin.plugins.recovery.')->group(function () {
  Route::get('/login', [LoginController::class, 'create'])->middleware('guest')->name('login');
  Route::post('/login', [LoginController::class, 'store'])->middleware(['guest', 'throttle:webblocks-auth'])->name('login.store');
  Route::middleware([RequirePluginRecoveryAccess::class, UseAdminLocale::class])->group(function () {
    Route::get('/', [PluginRecoveryController::class, 'index'])->name('index');
    Route::post('/{plugin}', [PluginRecoveryController::class, 'update'])->where('plugin', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('update');
  });
});
