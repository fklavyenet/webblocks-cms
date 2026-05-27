<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Http\Controllers\WebBlocksUiReleaseController;

Route::middleware('can:access-system')->group(function (): void {
  Route::get('/releases', [WebBlocksUiReleaseController::class, 'index'])->name('releases.index');
  Route::get('/releases/create', [WebBlocksUiReleaseController::class, 'create'])->name('releases.create');
  Route::post('/releases', [WebBlocksUiReleaseController::class, 'store'])->name('releases.store');
  Route::get('/releases/{release}', [WebBlocksUiReleaseController::class, 'show'])->name('releases.show');
  Route::get('/releases/{release}/edit', [WebBlocksUiReleaseController::class, 'edit'])->name('releases.edit');
  Route::put('/releases/{release}', [WebBlocksUiReleaseController::class, 'update'])->name('releases.update');
  Route::post('/releases/{release}/publish-dry-run', [WebBlocksUiReleaseController::class, 'dryRun'])->name('releases.publish.dry-run');
  Route::post('/releases/{release}/publish', [WebBlocksUiReleaseController::class, 'publish'])->name('releases.publish');
});
