<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Install\InstallNoticeController;

Route::middleware(['web', 'install.complete'])
  ->prefix('install')
  ->name('webblocks-cms.install.')
  ->group(function () {
    Route::get('/', InstallNoticeController::class)->name('notice');
  });
