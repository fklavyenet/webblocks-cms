<?php

use App\Http\Controllers\Admin\BlockController;
use App\Http\Controllers\Admin\BlockTypeController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LocaleController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NavigationItemController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PageTranslationController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\SlotTypeController;
use App\Http\Controllers\Admin\SystemBackupController;
use App\Http\Controllers\Admin\SystemUpdateController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\PageController as PublicPageController;
use App\Http\Controllers\ProfileController;
use App\Models\Locale;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicPageController::class, 'home'])->name('home');

Route::get('/{locale}', [PublicPageController::class, 'home'])
    ->where('locale', Locale::routePattern())
    ->name('localized.home');

Route::middleware('auth')->group(function () {
    Route::redirect('/dashboard', '/admin')->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/dashboard', fn () => redirect()->route('admin.dashboard'));
    Route::patch('/pages/{page}/status', [PageController::class, 'updateStatus'])->name('pages.status');
    Route::get('/pages/{page}/slots/{slot}/blocks', [PageController::class, 'editSlotBlocks'])->name('pages.slots.blocks');
    Route::get('/pages/{page}/translations/{locale}/create', [PageTranslationController::class, 'create'])->name('pages.translations.create');
    Route::post('/pages/{page}/translations/{locale}', [PageTranslationController::class, 'store'])->name('pages.translations.store');
    Route::get('/pages/{page}/translations/{translation}/edit', [PageTranslationController::class, 'edit'])->name('pages.translations.edit');
    Route::put('/pages/{page}/translations/{translation}', [PageTranslationController::class, 'update'])->name('pages.translations.update');
    Route::resource('pages', PageController::class)->except([]);
    Route::get('sites/clone', [SiteController::class, 'cloneForm'])->name('sites.clone');
    Route::get('sites/{site}/clone', [SiteController::class, 'cloneForm'])->name('sites.clone.prefill');
    Route::post('sites/clone', [SiteController::class, 'cloneStore'])->name('sites.clone.store');
    Route::get('sites/{site}/delete', [SiteController::class, 'deleteConfirm'])->name('sites.delete');
    Route::resource('sites', SiteController::class)->except(['show']);
    Route::resource('locales', LocaleController::class)->except(['show', 'destroy']);
    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::post('media/folders', [MediaController::class, 'storeFolder'])->name('media.folders.store');
    Route::get('media/{asset}', [MediaController::class, 'show'])->name('media.show');
    Route::get('media/{asset}/edit', [MediaController::class, 'edit'])->name('media.edit');
    Route::put('media/{asset}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('media/{asset}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::resource('slot-types', SlotTypeController::class)->only(['index']);
    Route::post('navigation/reorder', [NavigationItemController::class, 'reorder'])->name('navigation.reorder');
    Route::patch('navigation/{navigation}/visibility', [NavigationItemController::class, 'toggleVisibility'])->name('navigation.visibility');
    Route::resource('navigation', NavigationItemController::class)->except(['show']);
    Route::resource('block-types', BlockTypeController::class)->except(['show']);
    Route::get('system/backups', [SystemBackupController::class, 'index'])->name('system.backups.index');
    Route::post('system/backups', [SystemBackupController::class, 'store'])->name('system.backups.store');
    Route::delete('system/backups/{backup}', [SystemBackupController::class, 'destroy'])->name('system.backups.destroy');
    Route::get('system/backups/{backup}', [SystemBackupController::class, 'show'])->name('system.backups.show');
    Route::get('system/backups/{backup}/download', [SystemBackupController::class, 'download'])->name('system.backups.download');
    Route::post('system/backups/{backup}/restore', [SystemBackupController::class, 'restore'])->name('system.backups.restore');
    Route::get('system/updates', [SystemUpdateController::class, 'index'])->name('system.updates.index');
    Route::get('system/updates/check', [SystemUpdateController::class, 'check'])->name('system.updates.check');
    Route::post('system/updates', [SystemUpdateController::class, 'store'])->name('system.updates.store');
    Route::get('contact-messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
    Route::get('contact-messages/{contactMessage}', [AdminContactMessageController::class, 'show'])->name('contact-messages.show');
    Route::patch('contact-messages/{contactMessage}/status', [AdminContactMessageController::class, 'updateStatus'])->name('contact-messages.status');
    Route::delete('contact-messages/{contactMessage}', [AdminContactMessageController::class, 'destroy'])->name('contact-messages.destroy');
    Route::post('/blocks/{block}/move-up', [BlockController::class, 'moveUp'])->name('blocks.move-up');
    Route::post('/blocks/{block}/move-down', [BlockController::class, 'moveDown'])->name('blocks.move-down');
    Route::resource('blocks', BlockController::class)->except(['show']);
});

Route::post('/contact-messages', [ContactMessageController::class, 'store'])
    ->middleware('throttle:contact-form-submissions')
    ->name('contact-messages.store');

Route::get('/p/{slug}', [PublicPageController::class, 'show'])->name('pages.show');
Route::get('/{locale}/p/{slug}', [PublicPageController::class, 'show'])
    ->where('locale', Locale::routePattern())
    ->name('localized.pages.show');

require __DIR__.'/auth.php';
