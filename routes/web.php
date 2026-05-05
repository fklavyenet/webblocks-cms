<?php

use App\Http\Controllers\Admin\BlockController;
use App\Http\Controllers\Admin\BlockTypeController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LocaleController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NavigationItemController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PageRevisionController;
use App\Http\Controllers\Admin\PageSlotController;
use App\Http\Controllers\Admin\PageTranslationController;
use App\Http\Controllers\Admin\SharedSlotController;
use App\Http\Controllers\Admin\SiteExportController;
use App\Http\Controllers\Admin\SiteImportController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\SlotTypeController;
use App\Http\Controllers\Admin\SystemBackupController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\SystemUpdateController;
use App\Http\Controllers\Admin\VisitorReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Install\InstallWizardController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\PageController as PublicPageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicPrivacyConsentController;
use App\Models\Locale;
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

Route::middleware('install.required')->get('/', [PublicPageController::class, 'home'])->name('home');

Route::middleware('install.required')->get('/{locale}', [PublicPageController::class, 'home'])
    ->where('locale', Locale::routePattern())
    ->name('localized.home');

Route::middleware(['install.required', 'auth'])->group(function () {
    Route::redirect('/dashboard', '/admin')->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['install.required', 'auth', 'admin.access'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/dashboard', fn () => redirect()->route('admin.dashboard'));
    Route::post('/pages/{page}/workflow', [PageController::class, 'updateWorkflow'])->name('pages.workflow');
    Route::get('/pages/{page}/revisions', [PageRevisionController::class, 'index'])->name('pages.revisions.index');
    Route::post('/pages/{page}/revisions/{revision}/restore', [PageRevisionController::class, 'restore'])->name('pages.revisions.restore');
    Route::post('/pages/{page}/slots', [PageSlotController::class, 'store'])->name('pages.slots.store');
    Route::delete('/pages/{page}/slots/{slot}', [PageSlotController::class, 'destroy'])->name('pages.slots.destroy');
    Route::put('/pages/{page}/slots/{slot}/source', [PageSlotController::class, 'updateSource'])->name('pages.slots.source.update');
    Route::post('/pages/{page}/slots/{slot}/move-up', [PageSlotController::class, 'moveUp'])->name('pages.slots.move-up');
    Route::post('/pages/{page}/slots/{slot}/move-down', [PageSlotController::class, 'moveDown'])->name('pages.slots.move-down');
    Route::get('reports/visitors', [VisitorReportController::class, 'index'])->name('reports.visitors.index');
    Route::get('/pages/{page}/slots/{slot}/blocks', [PageController::class, 'editSlotBlocks'])->name('pages.slots.blocks');
    Route::post('/pages/{page}/slots/{slot}/blocks/reorder', [PageController::class, 'reorderSlotBlocks'])->name('pages.slots.blocks.reorder');
    Route::get('/shared-slots/{shared_slot}/blocks', [SharedSlotController::class, 'editBlocks'])->name('shared-slots.blocks.edit');
    Route::post('/shared-slots/{shared_slot}/blocks/reorder', [SharedSlotController::class, 'reorderBlocks'])->name('shared-slots.blocks.reorder');
    Route::get('/pages/{page}/translations/{locale}/create', [PageTranslationController::class, 'create'])->name('pages.translations.create');
    Route::post('/pages/{page}/translations/{locale}', [PageTranslationController::class, 'store'])->name('pages.translations.store');
    Route::get('/pages/{page}/translations/{translation}/edit', [PageTranslationController::class, 'edit'])->name('pages.translations.edit');
    Route::put('/pages/{page}/translations/{translation}', [PageTranslationController::class, 'update'])->name('pages.translations.update');
    Route::resource('pages', PageController::class)->except([]);
    Route::resource('shared-slots', SharedSlotController::class)->parameters(['shared-slots' => 'shared_slot'])->except(['show']);
    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::post('media/folders', [MediaController::class, 'storeFolder'])->name('media.folders.store');
    Route::get('media/{asset}', [MediaController::class, 'show'])->name('media.show');
    Route::get('media/{asset}/edit', [MediaController::class, 'edit'])->name('media.edit');
    Route::put('media/{asset}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('media/{asset}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::post('navigation/reorder', [NavigationItemController::class, 'reorder'])->name('navigation.reorder');
    Route::patch('navigation/{navigation}/visibility', [NavigationItemController::class, 'toggleVisibility'])->name('navigation.visibility');
    Route::resource('navigation', NavigationItemController::class)->except(['show']);
    Route::get('contact-messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
    Route::get('contact-messages/{contactMessage}', [AdminContactMessageController::class, 'show'])->name('contact-messages.show');
    Route::patch('contact-messages/{contactMessage}/status', [AdminContactMessageController::class, 'updateStatus'])->name('contact-messages.status');
    Route::delete('contact-messages/{contactMessage}', [AdminContactMessageController::class, 'destroy'])->name('contact-messages.destroy');
    Route::post('/blocks/{block}/move-up', [BlockController::class, 'moveUp'])->name('blocks.move-up');
    Route::post('/blocks/{block}/move-down', [BlockController::class, 'moveDown'])->name('blocks.move-down');
    Route::resource('blocks', BlockController::class)->except(['show']);

    Route::middleware('can:access-system')->group(function () {
        Route::resource('users', UserController::class)->except(['show'])->middleware('can:manage-users');
        Route::get('sites/clone', [SiteController::class, 'cloneForm'])->name('sites.clone');
        Route::get('sites/{site}/clone', [SiteController::class, 'cloneForm'])->name('sites.clone.prefill');
        Route::post('sites/clone', [SiteController::class, 'cloneStore'])->name('sites.clone.store');
        Route::get('sites/{site}/delete', [SiteController::class, 'deleteConfirm'])->name('sites.delete');
        Route::resource('sites', SiteController::class)->except(['show']);
        Route::resource('locales', LocaleController::class)->except(['show', 'destroy']);
        Route::post('locales/{locale}/enable', [LocaleController::class, 'enable'])->name('locales.enable');
        Route::post('locales/{locale}/disable', [LocaleController::class, 'disable'])->name('locales.disable');
        Route::delete('locales/{locale}', [LocaleController::class, 'destroy'])->name('locales.destroy');
        Route::resource('slot-types', SlotTypeController::class)->only(['index']);
        Route::resource('block-types', BlockTypeController::class)->except(['show']);
        Route::get('site-transfers/exports', [SiteExportController::class, 'index'])->name('site-transfers.exports.index');
        Route::post('site-transfers/exports', [SiteExportController::class, 'store'])->name('site-transfers.exports.store');
        Route::get('site-transfers/exports/{siteExport}', [SiteExportController::class, 'show'])->name('site-transfers.exports.show');
        Route::get('site-transfers/exports/{siteExport}/download', [SiteExportController::class, 'download'])->name('site-transfers.exports.download');
        Route::delete('site-transfers/exports/{siteExport}', [SiteExportController::class, 'destroy'])->name('site-transfers.exports.destroy');
        Route::get('site-transfers/imports', [SiteImportController::class, 'index'])->name('site-transfers.imports.index');
        Route::get('site-transfers/imports/create', [SiteImportController::class, 'create'])->name('site-transfers.imports.create');
        Route::post('site-transfers/imports/inspect', [SiteImportController::class, 'inspect'])->name('site-transfers.imports.inspect');
        Route::get('site-transfers/imports/{siteImport}', [SiteImportController::class, 'show'])->name('site-transfers.imports.show');
        Route::post('site-transfers/imports/{siteImport}/run', [SiteImportController::class, 'run'])->name('site-transfers.imports.run');
        Route::delete('site-transfers/imports/{siteImport}', [SiteImportController::class, 'destroy'])->name('site-transfers.imports.destroy');
        Route::get('system/backups', [SystemBackupController::class, 'index'])->name('system.backups.index');
        Route::post('system/backups', [SystemBackupController::class, 'store'])->name('system.backups.store');
        Route::get('system/backups/upload', [SystemBackupController::class, 'createUpload'])->name('system.backups.upload');
        Route::post('system/backups/upload', [SystemBackupController::class, 'upload'])->name('system.backups.upload.store');
        Route::delete('system/backups/{backup}', [SystemBackupController::class, 'destroy'])->name('system.backups.destroy');
        Route::get('system/backups/{backup}', [SystemBackupController::class, 'show'])->name('system.backups.show');
        Route::get('system/backups/{backup}/download', [SystemBackupController::class, 'download'])->name('system.backups.download');
        Route::post('system/backups/{backup}/restore', [SystemBackupController::class, 'restore'])->name('system.backups.restore');
        Route::delete('system/backups/{backup}/restores/{restore}', [SystemBackupController::class, 'destroyRestore'])->name('system.backups.restores.destroy');
        Route::get('system/settings', [SystemSettingsController::class, 'edit'])->name('system.settings.edit');
        Route::put('system/settings', [SystemSettingsController::class, 'update'])->name('system.settings.update');
        Route::get('system/updates', [SystemUpdateController::class, 'index'])->name('system.updates.index');
        Route::get('system/updates/check', [SystemUpdateController::class, 'check'])->name('system.updates.check');
        Route::post('system/updates', [SystemUpdateController::class, 'store'])->name('system.updates.store');
        Route::post('system/updates/continue', [SystemUpdateController::class, 'continue'])->name('system.updates.continue');
        Route::post('system/updates/cancel', [SystemUpdateController::class, 'cancel'])->name('system.updates.cancel');
    });
});

Route::middleware('install.required')->post('/contact-messages', [ContactMessageController::class, 'store'])
    ->middleware('throttle:contact-form-submissions')
    ->name('contact-messages.store');

Route::middleware('install.required')->prefix('privacy-consent')->name('public.privacy-consent.')->group(function () {
    Route::post('/sync', [PublicPrivacyConsentController::class, 'sync'])->name('sync');
});

Route::middleware('install.required')->get('/p/{slug}', [PublicPageController::class, 'show'])->name('pages.show');
Route::middleware('install.required')->get('/{locale}/p/{slug}', [PublicPageController::class, 'show'])
    ->where('locale', Locale::routePattern())
    ->name('localized.pages.show');

require __DIR__.'/auth.php';
