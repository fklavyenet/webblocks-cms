<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Http\Controllers\WebBlocksUiReleaseController;

Route::middleware('can:webblocks-ui-manager.view')->group(function (): void {
  Route::get('/releases', [WebBlocksUiReleaseController::class, 'index'])->name('releases.index');
  Route::get('/releases/create', [WebBlocksUiReleaseController::class, 'create'])->middleware('can:webblocks-ui-manager.manage')->name('releases.create');
  Route::post('/releases', [WebBlocksUiReleaseController::class, 'store'])->middleware('can:webblocks-ui-manager.manage')->name('releases.store');
  Route::get('/releases/{release}', [WebBlocksUiReleaseController::class, 'show'])->name('releases.show');
  Route::get('/releases/{release}/edit', [WebBlocksUiReleaseController::class, 'edit'])->middleware('can:webblocks-ui-manager.manage')->name('releases.edit');
  Route::put('/releases/{release}', [WebBlocksUiReleaseController::class, 'update'])->middleware('can:webblocks-ui-manager.manage')->name('releases.update');
});
