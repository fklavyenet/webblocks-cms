<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Auth\TwoFactorController;
use WebBlocks\Cms\Http\Middleware\UseAdminLocale;

// Always registered: host-owned login routes and plugin recovery need the same CMS gate.
Route::middleware(['web', UseAdminLocale::class])->prefix('webadmin/two-factor')->name('webblocks.two-factor.')->group(function () {
  Route::get('/challenge', [TwoFactorController::class, 'challenge'])->name('challenge');
  Route::post('/challenge', [TwoFactorController::class, 'verify'])->middleware('throttle:webblocks-two-factor')->name('verify');
  Route::post('/challenge/send', [TwoFactorController::class, 'sendChallenge'])->middleware('throttle:webblocks-two-factor')->name('sendChallenge');
  Route::post('/email-code', [TwoFactorController::class, 'send'])->middleware('throttle:webblocks-two-factor')->name('send');
  Route::get('/', [TwoFactorController::class, 'show'])->name('show');
  Route::post('/setup', [TwoFactorController::class, 'start'])->middleware('throttle:webblocks-two-factor')->name('start');
  Route::post('/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:webblocks-two-factor')->name('confirm');
  Route::post('/recovery-codes', [TwoFactorController::class, 'regenerate'])->middleware('throttle:webblocks-two-factor')->name('regenerate');
  Route::delete('/', [TwoFactorController::class, 'disable'])->middleware('throttle:webblocks-two-factor')->name('disable');
  Route::put('/policy', [TwoFactorController::class, 'policy'])->middleware('throttle:webblocks-two-factor')->name('policy');
});
