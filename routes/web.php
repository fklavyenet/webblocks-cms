<?php

use App\Http\Controllers\Install\InstallWizardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('install.complete')->prefix('install')->name('install.')->group(function () {
  Route::get('/', [InstallWizardController::class, 'welcome'])->name('welcome');
  Route::get('/database', [InstallWizardController::class, 'database'])->name('database');
  Route::post('/database', [InstallWizardController::class, 'saveDatabase'])->name('database.store');
  Route::get('/core', [InstallWizardController::class, 'core'])->name('core');
  Route::post('/core', [InstallWizardController::class, 'installCore'])->name('core.store');
  Route::get('/admin', [InstallWizardController::class, 'admin'])->name('admin');
  Route::post('/admin', [InstallWizardController::class, 'storeAdmin'])->name('admin.store');
  Route::get('/finish', [InstallWizardController::class, 'finish'])->name('finish');
});

Route::middleware(['install.required', 'auth'])->group(function () {
  Route::redirect('/dashboard', '/cms')->name('dashboard');
  Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
  Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
  Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require base_path('packages/webblocks-cms/routes/admin.php');

require base_path('packages/webblocks-cms/routes/public.php');

require __DIR__.'/auth.php';
