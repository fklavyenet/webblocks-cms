<?php

use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LocaleController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\SiteDomainController;
use App\Http\Controllers\Admin\SiteExportController;
use App\Http\Controllers\Admin\SiteImportController;
use App\Http\Controllers\Admin\SitePromotionController;
use App\Http\Controllers\Admin\SiteVariableController;
use App\Http\Controllers\Admin\SlotTypeController;
use App\Http\Controllers\Admin\SystemBackupController;
use App\Http\Controllers\Admin\SystemSearchController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\SystemUpdateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VisitorReportController;
use App\Support\SharedSlots\SharedSlotSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Admin\BlockController;
use WebBlocks\Cms\Http\Controllers\Admin\BlockTypeController;
use WebBlocks\Cms\Http\Controllers\Admin\IconCatalogController;
use WebBlocks\Cms\Http\Controllers\Admin\MediaController;
use WebBlocks\Cms\Http\Controllers\Admin\NavigationItemController;
use WebBlocks\Cms\Http\Controllers\Admin\PackageAdminStatusController;
use WebBlocks\Cms\Http\Controllers\Admin\PageAssetController;
use WebBlocks\Cms\Http\Controllers\Admin\PageController;
use WebBlocks\Cms\Http\Controllers\Admin\PageDuplicateController;
use WebBlocks\Cms\Http\Controllers\Admin\PageImportController;
use WebBlocks\Cms\Http\Controllers\Admin\PageLayoutController;
use WebBlocks\Cms\Http\Controllers\Admin\PageLayoutSlotController;
use WebBlocks\Cms\Http\Controllers\Admin\PageRevisionController;
use WebBlocks\Cms\Http\Controllers\Admin\PageSiteMoveController;
use WebBlocks\Cms\Http\Controllers\Admin\PageSlotController;
use WebBlocks\Cms\Http\Controllers\Admin\PageTranslationController;
use WebBlocks\Cms\Http\Controllers\Admin\SharedSlotController;
use WebBlocks\Cms\Http\Controllers\Admin\SharedSlotRevisionController;

Route::middleware(['web', 'install.required', 'auth', 'admin.access'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        $missingSharedSlot = function (): never {
            if (! app(SharedSlotSchema::class)->sharedSlotsTableExists()) {
                redirect()
                    ->route('admin.shared-slots.index')
                    ->withErrors(['shared_slots' => 'Shared Slots are not ready yet. Run the latest migrations before using Shared Slots.'])
                    ->throwResponse();
            }

            abort(404);
        };

        $missingSharedSlotRevision = function (Request $request): never {
            $schema = app(SharedSlotSchema::class);

            if (! $schema->sharedSlotsTableExists()) {
                redirect()
                    ->route('admin.shared-slots.index')
                    ->withErrors(['shared_slots' => 'Shared Slots are not ready yet. Run the latest migrations before using Shared Slots.'])
                    ->throwResponse();
            }

            if (! $schema->revisionsTableExists()) {
                $sharedSlot = $request->route('shared_slot');

                redirect()
                    ->route($sharedSlot ? 'admin.shared-slots.edit' : 'admin.shared-slots.index', $sharedSlot ? ['shared_slot' => $sharedSlot] : [])
                    ->withErrors(['revisions' => 'Shared Slot revisions are not ready yet. Run the latest migrations before opening revision details.'])
                    ->throwResponse();
            }

            abort(404);
        };

        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/dashboard', fn () => redirect()->route('admin.dashboard'));
        Route::post('/pages/{page}/workflow', [PageController::class, 'updateWorkflow'])->name('pages.workflow');
        Route::post('/pages/import-json', [PageImportController::class, 'store'])->name('pages.import.store');
        Route::post('/pages/{page}/assets/{type}', [PageAssetController::class, 'store'])->name('pages.assets.store');
        Route::put('/pages/{page}/assets/{page_asset}', [PageAssetController::class, 'update'])->name('pages.assets.update');
        Route::delete('/pages/{page}/assets/{page_asset}', [PageAssetController::class, 'destroy'])->name('pages.assets.destroy');
        Route::get('/pages/{page}/duplicate', [PageDuplicateController::class, 'create'])->name('pages.duplicate.create');
        Route::post('/pages/{page}/duplicate', [PageDuplicateController::class, 'store'])->name('pages.duplicate.store');
        Route::get('/pages/{page}/move-site', [PageSiteMoveController::class, 'create'])->name('pages.move-site.create');
        Route::post('/pages/{page}/move-site', [PageSiteMoveController::class, 'store'])->name('pages.move-site.store');
        Route::get('/pages/{page}/revisions', [PageRevisionController::class, 'index'])->name('pages.revisions.index');
        Route::post('/pages/{page}/revisions/{revision}/restore', [PageRevisionController::class, 'restore'])->name('pages.revisions.restore');
        Route::post('/pages/{page}/sync-layout-slots', [PageSlotController::class, 'syncLayoutSlots'])->name('pages.layout-slots.sync');
        Route::post('/pages/{page}/slots', [PageSlotController::class, 'store'])->name('pages.slots.store');
        Route::delete('/pages/{page}/slots/{slot}', [PageSlotController::class, 'destroy'])->name('pages.slots.destroy');
        Route::put('/pages/{page}/slots/{slot}/source', [PageSlotController::class, 'updateSource'])->name('pages.slots.source.update');
        Route::post('/pages/{page}/slots/{slot}/move-up', [PageSlotController::class, 'moveUp'])->name('pages.slots.move-up');
        Route::post('/pages/{page}/slots/{slot}/move-down', [PageSlotController::class, 'moveDown'])->name('pages.slots.move-down');
        Route::get('/shared-slots/{shared_slot}/revisions', [SharedSlotRevisionController::class, 'index'])->name('shared-slots.revisions.index')->missing($missingSharedSlot);
        Route::get('/shared-slots/{shared_slot}/revisions/{revision}', [SharedSlotRevisionController::class, 'show'])->name('shared-slots.revisions.show')->missing($missingSharedSlotRevision);
        Route::post('/shared-slots/{shared_slot}/revisions/{revision}/restore', [SharedSlotRevisionController::class, 'restore'])->name('shared-slots.revisions.restore')->missing($missingSharedSlotRevision);
        Route::get('reports/visitors', [VisitorReportController::class, 'index'])->name('reports.visitors.index');
        Route::get('/pages/{page}/slots/{slot}/blocks', [PageController::class, 'editSlotBlocks'])->name('pages.slots.blocks');
        Route::post('/pages/{page}/slots/{slot}/blocks/reorder', [PageController::class, 'reorderSlotBlocks'])->name('pages.slots.blocks.reorder');
        Route::delete('/pages/{page}/slots/{slot}/blocks', [PageController::class, 'destroySlotBlocks'])->name('pages.slots.blocks.destroy-all');
        Route::get('/shared-slots/{shared_slot}/blocks', [SharedSlotController::class, 'editBlocks'])->name('shared-slots.blocks.edit')->missing($missingSharedSlot);
        Route::post('/shared-slots/{shared_slot}/blocks/reorder', [SharedSlotController::class, 'reorderBlocks'])->name('shared-slots.blocks.reorder')->missing($missingSharedSlot);
        Route::delete('/shared-slots/{shared_slot}/blocks', [SharedSlotController::class, 'destroyBlocks'])->name('shared-slots.blocks.destroy-all')->missing($missingSharedSlot);
        Route::get('/pages/{page}/translations/{locale}/create', [PageTranslationController::class, 'create'])->name('pages.translations.create');
        Route::post('/pages/{page}/translations/{locale}', [PageTranslationController::class, 'store'])->name('pages.translations.store');
        Route::get('/pages/{page}/translations/{translation}/edit', [PageTranslationController::class, 'edit'])->name('pages.translations.edit');
        Route::put('/pages/{page}/translations/{translation}', [PageTranslationController::class, 'update'])->name('pages.translations.update');
        Route::resource('pages', PageController::class)->except([]);
        Route::resource('shared-slots', SharedSlotController::class)->parameters(['shared-slots' => 'shared_slot'])->except(['show'])->missing($missingSharedSlot);
        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::post('media', [MediaController::class, 'store'])->name('media.store');
        Route::post('media/folders', [MediaController::class, 'storeFolder'])->name('media.folders.store');
        Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');
        Route::get('media/{media}/edit', [MediaController::class, 'edit'])->name('media.edit');
        Route::put('media/{media}', [MediaController::class, 'update'])->name('media.update');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
        Route::post('navigation/reorder', [NavigationItemController::class, 'reorder'])->name('navigation.reorder');
        Route::patch('navigation/{navigation}/visibility', [NavigationItemController::class, 'toggleVisibility'])->name('navigation.visibility');
        Route::resource('navigation', NavigationItemController::class)->except(['show']);
        Route::get('contact-messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
        Route::get('contact-messages/{contactMessage}', [AdminContactMessageController::class, 'show'])->name('contact-messages.show');
        Route::patch('contact-messages/{contactMessage}/status', [AdminContactMessageController::class, 'updateStatus'])->name('contact-messages.status');
        Route::delete('contact-messages/{contactMessage}', [AdminContactMessageController::class, 'destroy'])->name('contact-messages.destroy');
        Route::post('/blocks/{block}/move-up', [BlockController::class, 'moveUp'])->name('blocks.move-up');
        Route::post('/blocks/{block}/move-down', [BlockController::class, 'moveDown'])->name('blocks.move-down');
        Route::get('/blocks', [BlockController::class, 'index'])->name('blocks.index')->middleware('can:access-system');
        Route::resource('blocks', BlockController::class)->except(['show', 'index']);
        Route::get('sites/{site}/edit', [SiteController::class, 'edit'])->name('sites.edit');
        Route::put('sites/{site}', [SiteController::class, 'update'])->name('sites.update');
        Route::post('sites/{site}/variables', [SiteVariableController::class, 'store'])->name('sites.variables.store');
        Route::put('sites/{site}/variables/{site_variable}', [SiteVariableController::class, 'update'])->name('sites.variables.update');
        Route::delete('sites/{site}/variables/{site_variable}', [SiteVariableController::class, 'destroy'])->name('sites.variables.destroy');

        Route::middleware('can:access-system')->group(function () {
            Route::resource('users', UserController::class)->except(['show'])->middleware('can:manage-users');
            Route::get('sites/clone', [SiteController::class, 'cloneForm'])->name('sites.clone');
            Route::get('sites/{site}/clone', [SiteController::class, 'cloneForm'])->name('sites.clone.prefill');
            Route::post('sites/clone', [SiteController::class, 'cloneStore'])->name('sites.clone.store');
            Route::post('sites/{site}/export', [SiteExportController::class, 'storeFromSitesIndex'])->name('sites.export');
            Route::get('sites/promote', [SitePromotionController::class, 'index'])->name('sites.promote');
            Route::post('sites/promote/dry-run', [SitePromotionController::class, 'dryRun'])->name('sites.promote.dry-run');
            Route::post('sites/promote/apply', [SitePromotionController::class, 'apply'])->name('sites.promote.apply');
            Route::get('sites/{site}/delete', [SiteController::class, 'deleteConfirm'])->name('sites.delete');
            Route::get('domains', [SiteDomainController::class, 'landing'])->name('domains.index');
            Route::get('sites/{site}/domains', [SiteDomainController::class, 'index'])->name('sites.domains.index');
            Route::post('sites/{site}/domains', [SiteDomainController::class, 'store'])->name('sites.domains.store');
            Route::put('sites/{site}/domains/{domain}', [SiteDomainController::class, 'update'])->name('sites.domains.update');
            Route::delete('sites/{site}/domains/{domain}', [SiteDomainController::class, 'destroy'])->name('sites.domains.destroy');
            Route::post('sites/{site}/domains/{domain}/primary', [SiteDomainController::class, 'setPrimary'])->name('sites.domains.primary');
            Route::resource('sites', SiteController::class)->except(['show', 'edit', 'update']);
            Route::resource('locales', LocaleController::class)->except(['show', 'destroy']);
            Route::post('locales/{locale}/enable', [LocaleController::class, 'enable'])->name('locales.enable');
            Route::post('locales/{locale}/disable', [LocaleController::class, 'disable'])->name('locales.disable');
            Route::delete('locales/{locale}', [LocaleController::class, 'destroy'])->name('locales.destroy');
            Route::resource('page-layouts', PageLayoutController::class)->except(['show', 'destroy']);
            Route::get('page-layouts/{page_layout}/slots/create', [PageLayoutSlotController::class, 'create'])->name('page-layouts.slots.create');
            Route::post('page-layouts/{page_layout}/slots', [PageLayoutSlotController::class, 'store'])->name('page-layouts.slots.store');
            Route::get('page-layouts/{page_layout}/slots/{page_layout_slot}/edit', [PageLayoutSlotController::class, 'edit'])->name('page-layouts.slots.edit');
            Route::put('page-layouts/{page_layout}/slots/{page_layout_slot}', [PageLayoutSlotController::class, 'update'])->name('page-layouts.slots.update');
            Route::delete('page-layouts/{page_layout}/slots/{page_layout_slot}', [PageLayoutSlotController::class, 'destroy'])->name('page-layouts.slots.destroy');
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
            Route::get('system/search', [SystemSearchController::class, 'index'])->name('system.search.index');
            Route::post('system/search/rebuild', [SystemSearchController::class, 'rebuild'])->name('system.search.rebuild');
            Route::get('system/updates', [SystemUpdateController::class, 'index'])->name('system.updates.index');
            Route::get('system/updates/check', [SystemUpdateController::class, 'check'])->name('system.updates.check');
            Route::post('system/updates', [SystemUpdateController::class, 'store'])->name('system.updates.store');
            Route::post('system/updates/continue', [SystemUpdateController::class, 'continue'])->name('system.updates.continue');
            Route::post('system/updates/cancel', [SystemUpdateController::class, 'cancel'])->name('system.updates.cancel');
            Route::get('system/icons', [IconCatalogController::class, 'index'])->name('system.icons.index');
            Route::put('system/icons/{iconCatalogItem}', [IconCatalogController::class, 'update'])->name('system.icons.update');
        });

        if (config('webblocks-cms.admin.load_status_route', false)) {
            Route::middleware('can:access-system')
                ->prefix('_webblocks-cms')
                ->name('webblocks-cms.')
                ->group(function () {
                    Route::get('/runtime-status', PackageAdminStatusController::class)
                        ->name('runtime-status');
                });
        }
    });
