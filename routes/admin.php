<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Admin\BlockController;
use WebBlocks\Cms\Http\Controllers\Admin\BlockTypeController;
use WebBlocks\Cms\Http\Controllers\Admin\CmsApiTokenController;
use WebBlocks\Cms\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use WebBlocks\Cms\Http\Controllers\Admin\DashboardController;
use WebBlocks\Cms\Http\Controllers\Admin\EmbeddedApplicationAssetController;
use WebBlocks\Cms\Http\Controllers\Admin\EmbeddedApplicationController;
use WebBlocks\Cms\Http\Controllers\Admin\EngagementController;
use WebBlocks\Cms\Http\Controllers\Admin\IconCatalogController;
use WebBlocks\Cms\Http\Controllers\Admin\LocaleController;
use WebBlocks\Cms\Http\Controllers\Admin\MaintenanceCleanupController;
use WebBlocks\Cms\Http\Controllers\Admin\MediaController;
use WebBlocks\Cms\Http\Controllers\Admin\NavigationItemController;
use WebBlocks\Cms\Http\Controllers\Admin\PackageAdminStatusController;
use WebBlocks\Cms\Http\Controllers\Admin\PageAssetController;
use WebBlocks\Cms\Http\Controllers\Admin\PageController;
use WebBlocks\Cms\Http\Controllers\Admin\PageConverterController;
use WebBlocks\Cms\Http\Controllers\Admin\PageDuplicateController;
use WebBlocks\Cms\Http\Controllers\Admin\PageImportController;
use WebBlocks\Cms\Http\Controllers\Admin\PageLayoutController;
use WebBlocks\Cms\Http\Controllers\Admin\PageLayoutSlotController;
use WebBlocks\Cms\Http\Controllers\Admin\PageRevisionController;
use WebBlocks\Cms\Http\Controllers\Admin\PageSiteMoveController;
use WebBlocks\Cms\Http\Controllers\Admin\PageSlotController;
use WebBlocks\Cms\Http\Controllers\Admin\PageTranslationController;
use WebBlocks\Cms\Http\Controllers\Admin\PluginCatalogController;
use WebBlocks\Cms\Http\Controllers\Admin\ProfileController;
use WebBlocks\Cms\Http\Controllers\Admin\SharedSlotController;
use WebBlocks\Cms\Http\Controllers\Admin\SharedSlotRevisionController;
use WebBlocks\Cms\Http\Controllers\Admin\SiteAssetController;
use WebBlocks\Cms\Http\Controllers\Admin\SiteController;
use WebBlocks\Cms\Http\Controllers\Admin\SiteDomainController;
use WebBlocks\Cms\Http\Controllers\Admin\SiteExportController;
use WebBlocks\Cms\Http\Controllers\Admin\SiteImportController;
use WebBlocks\Cms\Http\Controllers\Admin\SitePromotionController;
use WebBlocks\Cms\Http\Controllers\Admin\SiteVariableController;
use WebBlocks\Cms\Http\Controllers\Admin\SlotTypeController;
use WebBlocks\Cms\Http\Controllers\Admin\SupportController;
use WebBlocks\Cms\Http\Controllers\Admin\SystemBackupController;
use WebBlocks\Cms\Http\Controllers\Admin\SystemInformationController;
use WebBlocks\Cms\Http\Controllers\Admin\SystemPluginController;
use WebBlocks\Cms\Http\Controllers\Admin\SystemSearchController;
use WebBlocks\Cms\Http\Controllers\Admin\SystemSettingsController;
use WebBlocks\Cms\Http\Controllers\Admin\SystemUpdateController;
use WebBlocks\Cms\Http\Controllers\Admin\UserController;
use WebBlocks\Cms\Http\Controllers\Admin\VisitorReportController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalAdminRenderController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApplicationAssetController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApplicationController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalBackupCleanupController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentPlanController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalEngagementController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalInventoryController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalMaintenanceCleanupController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalNavigationController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPageAssetController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPagePublishController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPageRenderController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPageRevisionController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPageTranslationController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPluginController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalSharedSlotController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalSiteController;
use WebBlocks\Cms\Http\Middleware\AllowPagePreviewAccess;
use WebBlocks\Cms\Http\Middleware\CoalesceSearchIndexing;
use WebBlocks\Cms\Http\Middleware\UseCmsAuthenticationRedirect;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSchema;

$internalApiCsrfMiddleware = [
  'App\\Http\\Middleware\\VerifyCsrfToken',
  'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
  'Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken',
  'Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken',
];

Route::middleware(['web', 'install.required', 'throttle:internal-content-api'])
  ->prefix('webadmin/api')
  ->name('internal-content-api.')
  ->group(function () {
    Route::get('/', [InternalApiDiscoveryController::class, 'index'])->name('discovery');
  });

Route::middleware(['web', 'install.required', 'throttle:internal-content-api', 'internal-api.token', CoalesceSearchIndexing::class])
  ->withoutMiddleware($internalApiCsrfMiddleware)
  ->prefix('webadmin/api')
  ->name('internal-content-api.')
  ->group(function () {
    Route::get('/openapi.json', [InternalApiDiscoveryController::class, 'openapi'])->name('openapi');
    Route::get('/ai-guide', [InternalApiDiscoveryController::class, 'aiGuide'])->name('ai-guide');
    Route::get('/inventory', [InternalInventoryController::class, 'show'])->name('inventory.show');
    Route::get('/examples', [InternalApiDiscoveryController::class, 'examples'])->name('examples.index');
    Route::get('/examples/contact-page', [InternalApiDiscoveryController::class, 'contactPageExample'])->name('examples.contact-page');
    Route::get('/examples/landing-page', [InternalApiDiscoveryController::class, 'landingPageExample'])->name('examples.landing-page');
    Route::get('/admin-render/system-updates', [InternalAdminRenderController::class, 'systemUpdates'])->middleware('internal-api.capability:admin.render')->name('admin-render.system-updates');
    Route::get('/system/backup-cleanup', [InternalBackupCleanupController::class, 'show'])->middleware('internal-api.capability:backups.read')->name('system.backup-cleanup.show');
    Route::put('/system/backup-cleanup', [InternalBackupCleanupController::class, 'update'])->middleware('internal-api.capability:backups.settings.write')->name('system.backup-cleanup.update');
    Route::post('/system/backup-cleanup/run', [InternalBackupCleanupController::class, 'run'])->middleware('internal-api.capability:backups.delete')->name('system.backup-cleanup.run');
    Route::get('/system/cleanup', [InternalMaintenanceCleanupController::class, 'show'])->middleware('internal-api.capability:maintenance.read')->name('system.cleanup.show');
    Route::put('/system/cleanup', [InternalMaintenanceCleanupController::class, 'update'])->middleware('internal-api.capability:maintenance.settings.write')->name('system.cleanup.update');
    Route::post('/system/cleanup/{category}/run', [InternalMaintenanceCleanupController::class, 'run'])->middleware('internal-api.capability:maintenance.delete')->name('system.cleanup.run');
    Route::get('/sites', [InternalContentResourceController::class, 'sites'])->name('sites.index');
    Route::post('/sites/{site}/public-theme', [InternalSiteController::class, 'updatePublicTheme'])->middleware('internal-api.capability:site-settings.write')->name('sites.public-theme.update');
    Route::get('/sites/{site}/assets/{type}', [InternalSiteController::class, 'showAsset'])->middleware('internal-api.capability:site-assets.read')->name('sites.assets.show');
    Route::put('/sites/{site}/assets/{type}', [InternalSiteController::class, 'updateAsset'])->middleware('internal-api.capability:site-assets.write')->name('sites.assets.update');
    Route::get('/locales', [InternalContentResourceController::class, 'locales'])->name('locales.index');
    Route::get('/locale-options', [InternalContentResourceController::class, 'localeOptions'])->name('locale-options.index');
    Route::post('/locales', [InternalContentResourceController::class, 'storeLocale'])->middleware('internal-api.capability:site-settings.write')->name('locales.store');
    Route::patch('/locales/{locale}', [InternalContentResourceController::class, 'updateLocale'])->middleware('internal-api.capability:site-settings.write')->name('locales.update');
    Route::get('/page-layouts', [InternalContentResourceController::class, 'pageLayouts'])->name('page-layouts.index');
    Route::get('/block-types', [InternalContentResourceController::class, 'blockTypes'])->name('block-types.index');
    Route::get('/applications', [InternalApplicationController::class, 'index'])->middleware('internal-api.capability:applications.read')->name('applications.index');
    Route::post('/applications', [InternalApplicationController::class, 'store'])->middleware('internal-api.capability:applications.write')->name('applications.store');
    Route::get('/applications/{application}/schema', [InternalApplicationController::class, 'schema'])->middleware('internal-api.capability:applications.read')->name('applications.schema');
    Route::get('/applications/{application}', [InternalApplicationController::class, 'show'])->middleware('internal-api.capability:applications.read')->name('applications.show');
    Route::patch('/applications/{application}', [InternalApplicationController::class, 'update'])->middleware('internal-api.capability:applications.write')->name('applications.update');
    Route::delete('/applications/{application}', [InternalApplicationController::class, 'destroy'])->middleware('internal-api.capability:applications.delete')->name('applications.destroy');
    Route::get('/sites/{site}/applications/{application}/assets', [InternalApplicationAssetController::class, 'index'])->middleware('internal-api.capability:applications.read')->name('applications.assets.index');
    Route::get('/sites/{site}/applications/{application}/assets/{type}/{filename}', [InternalApplicationAssetController::class, 'show'])->middleware('internal-api.capability:applications.read')->name('applications.assets.show');
    Route::put('/sites/{site}/applications/{application}/assets/{type}/{filename}', [InternalApplicationAssetController::class, 'update'])->middleware('internal-api.capability:applications.write')->name('applications.assets.update');
    Route::delete('/sites/{site}/applications/{application}/assets/{type}/{filename}', [InternalApplicationAssetController::class, 'destroy'])->middleware('internal-api.capability:applications.delete')->name('applications.assets.destroy');
    Route::get('/icon-catalog', [InternalContentResourceController::class, 'iconCatalog'])->name('icon-catalog.index');
    Route::get('/content-contract', [InternalContentResourceController::class, 'contentContract'])->name('content-contract.show');
    Route::get('/plugins', [InternalPluginController::class, 'index'])->middleware('internal-api.capability:plugins.read')->name('plugins.index');
    Route::post('/plugins/install', [InternalPluginController::class, 'install'])->middleware('internal-api.capability:plugins.install')->name('plugins.install');
    Route::get('/plugins/catalog', [InternalPluginController::class, 'catalog'])->middleware('internal-api.capability:plugins.read')->name('plugins.catalog.index');
    Route::get('/plugins/catalog/{plugin}', [InternalPluginController::class, 'catalogShow'])->middleware('internal-api.capability:plugins.read')->name('plugins.catalog.show');
    Route::post('/plugins/catalog/{plugin}/install', [InternalPluginController::class, 'catalogInstall'])->middleware('internal-api.capability:plugins.install')->name('plugins.catalog.install');
    Route::post('/plugins/{plugin}/enable', [InternalPluginController::class, 'enable'])->middleware('internal-api.capability:plugins.manage')->name('plugins.enable');
    Route::post('/plugins/{plugin}/setup', [InternalPluginController::class, 'setup'])->middleware('internal-api.capability:plugins.setup')->name('plugins.setup');
    Route::post('/plugins/{plugin}/disable', [InternalPluginController::class, 'disable'])->middleware('internal-api.capability:plugins.manage')->name('plugins.disable');
    Route::delete('/plugins/{plugin}', [InternalPluginController::class, 'uninstall'])->middleware('internal-api.capability:plugins.uninstall')->name('plugins.uninstall');
    // Commerce product & order API routes are now owned by the WebBlocks Commerce
    // plugin itself (registered via PluginDefinition::apiRoutes()), so they are
    // only present when the plugin is enabled.
    Route::get('/pages', [InternalContentResourceController::class, 'pages'])->name('pages.index');
    Route::get('/pages/{page}', [InternalContentResourceController::class, 'page'])->name('pages.show');
    Route::post('/pages/{page}/sync-layout-slots', [InternalSiteController::class, 'syncPageLayoutSlots'])->middleware('internal-api.capability:content.apply')->name('pages.layout-slots.sync');
    Route::post('/pages/{page}/publish', [InternalPagePublishController::class, 'publish'])->middleware('internal-api.capability:content.publish')->name('pages.publish');
    Route::post('/pages/{page}/publish-page-owned-blocks', [InternalPagePublishController::class, 'publishPageOwnedBlocks'])->middleware('internal-api.capability:content.publish')->name('pages.publish-page-owned-blocks');
    Route::post('/pages/{page}/archive', [InternalPagePublishController::class, 'archive'])->middleware('internal-api.capability:content.publish')->name('pages.archive');
    Route::delete('/pages/{page}', [InternalContentResourceController::class, 'deletePage'])->middleware('internal-api.capability:pages.delete')->name('pages.delete');
    Route::delete('/pages/{page}/staged-update', [InternalContentResourceController::class, 'discardStagedUpdate'])->middleware('internal-api.capability:content.apply')->name('pages.staged-update.discard');
    Route::patch('/pages/{page}/layout', [InternalContentResourceController::class, 'updatePageLayout'])->middleware('internal-api.capability:content.apply')->name('pages.layout.update');
    Route::get('/pages/{page}/render', [InternalPageRenderController::class, 'show'])->middleware('internal-api.capability:content.read')->name('pages.render');
    Route::get('/pages/{page}/versions', [InternalPageRevisionController::class, 'index'])->middleware('internal-api.capability:content.read')->name('pages.versions.index');
    Route::get('/pages/{page}/versions/{revision}', [InternalPageRevisionController::class, 'show'])->middleware('internal-api.capability:content.read')->name('pages.versions.show');
    Route::post('/pages/{page}/versions/{revision}/candidate', [InternalPageRevisionController::class, 'prepare'])->middleware('internal-api.capability:content.apply')->name('pages.versions.candidate.prepare');
    Route::post('/pages/{page}/version-candidates/{candidate}/apply', [InternalPageRevisionController::class, 'apply'])->middleware(['internal-api.capability:content.apply', 'internal-api.capability:content.publish'])->name('pages.version-candidates.apply');
    Route::delete('/pages/{page}/version-candidates/{candidate}', [InternalPageRevisionController::class, 'discard'])->middleware('internal-api.capability:content.apply')->name('pages.version-candidates.discard');
    Route::get('/pages/{page}/translations', [InternalPageTranslationController::class, 'index'])->name('pages.translations.index');
    Route::post('/pages/{page}/translations/{locale}', [InternalPageTranslationController::class, 'store'])->middleware('internal-api.capability:content.apply')->name('pages.translations.store');
    Route::patch('/pages/{page}/translations/{translation}', [InternalPageTranslationController::class, 'update'])->middleware('internal-api.capability:content.apply')->name('pages.translations.update');
    Route::get('/pages/{page}/assets', [InternalPageAssetController::class, 'index'])->name('pages.assets.index');
    Route::post('/pages/{page}/assets/{type}', [InternalPageAssetController::class, 'store'])->middleware('internal-api.capability:page-assets.write')->name('pages.assets.store');
    Route::patch('/pages/{page}/assets/{pageAsset}', [InternalPageAssetController::class, 'update'])->middleware('internal-api.capability:page-assets.write')->name('pages.assets.update');
    Route::delete('/pages/{page}/assets/{pageAsset}', [InternalPageAssetController::class, 'destroy'])->middleware('internal-api.capability:page-assets.write')->name('pages.assets.delete');
    Route::post('/pages/{page}/slots/{slot}/shared-slot', [InternalSharedSlotController::class, 'assignToPageSlot'])->middleware('internal-api.capability:shared-slots.write')->name('pages.slots.shared-slot');
    Route::put('/pages/{page}/slots/{slot}/source', [InternalSharedSlotController::class, 'updatePageSlotSource'])->middleware('internal-api.capability:content.apply')->name('pages.slots.source');
    Route::post('/pages/{page}/slots/{slot}/blocks', [InternalContentResourceController::class, 'storeSlotBlock'])->middleware('internal-api.capability:content.apply')->name('pages.slots.blocks.store');
    Route::patch('/pages/{page}/slots/{slot}/blocks/reorder', [InternalContentResourceController::class, 'reorderSlotBlocks'])->middleware('internal-api.capability:content.apply')->name('pages.slots.blocks.reorder');
    Route::delete('/pages/{page}/slots/{slot}/blocks/{block}', [InternalContentResourceController::class, 'deleteSlotBlock'])->middleware('internal-api.capability:content.blocks.delete')->name('pages.slots.blocks.delete');
    Route::get('/navigation-menus', [InternalNavigationController::class, 'index'])->name('navigation-menus.index');
    Route::post('/navigation-menus', [InternalNavigationController::class, 'store'])->middleware('internal-api.capability:navigation.write')->name('navigation-menus.store');
    Route::get('/navigation-menus/{navigationMenu}', [InternalNavigationController::class, 'show'])->name('navigation-menus.show');
    Route::post('/navigation-menus/{navigationMenu}/items', [InternalNavigationController::class, 'storeItem'])->middleware('internal-api.capability:navigation.write')->name('navigation-menus.items.store');
    Route::patch('/navigation-menus/{navigationMenu}/items/reorder', [InternalNavigationController::class, 'reorderItems'])->middleware('internal-api.capability:navigation.write')->name('navigation-menus.items.reorder');
    Route::patch('/navigation-menus/{navigationMenu}/items/{item}', [InternalNavigationController::class, 'updateItem'])->middleware('internal-api.capability:navigation.write')->name('navigation-menus.items.update');
    Route::delete('/navigation-menus/{navigationMenu}/items/{item}', [InternalNavigationController::class, 'destroyItem'])->middleware('internal-api.capability:navigation.delete')->name('navigation-menus.items.destroy');
    Route::get('/shared-slots', [InternalSharedSlotController::class, 'index'])->name('shared-slots.index');
    Route::post('/shared-slots', [InternalSharedSlotController::class, 'store'])->middleware('internal-api.capability:shared-slots.write')->name('shared-slots.store');
    Route::get('/shared-slots/{sharedSlot}', [InternalSharedSlotController::class, 'show'])->name('shared-slots.show');
    Route::patch('/shared-slots/{sharedSlot}', [InternalSharedSlotController::class, 'update'])->middleware('internal-api.capability:shared-slots.write')->name('shared-slots.update');
    Route::delete('/shared-slots/{sharedSlot}', [InternalSharedSlotController::class, 'destroy'])->middleware('internal-api.capability:shared-slots.delete')->name('shared-slots.destroy');
    Route::post('/shared-slots/{sharedSlot}/blocks', [InternalSharedSlotController::class, 'storeBlock'])->middleware('internal-api.capability:shared-slots.write')->name('shared-slots.blocks.store');
    Route::post('/shared-slots/{sharedSlot}/publish-blocks', [InternalSharedSlotController::class, 'publishBlocks'])->middleware(['internal-api.capability:shared-slots.write', 'internal-api.capability:content.publish'])->name('shared-slots.blocks.publish');
    Route::patch('/shared-slots/{sharedSlot}/blocks/reorder', [InternalSharedSlotController::class, 'reorderBlocks'])->middleware('internal-api.capability:shared-slots.write')->name('shared-slots.blocks.reorder');
    Route::delete('/shared-slots/{sharedSlot}/blocks/{block}', [InternalSharedSlotController::class, 'deleteBlock'])->middleware(['internal-api.capability:shared-slots.write', 'internal-api.capability:content.blocks.delete'])->name('shared-slots.blocks.delete');
    Route::delete('/shared-slots/{sharedSlot}/blocks', [InternalSharedSlotController::class, 'clearBlocks'])->middleware(['internal-api.capability:shared-slots.write', 'internal-api.capability:content.blocks.delete'])->name('shared-slots.blocks.clear');
    Route::get('/media', [InternalContentResourceController::class, 'media'])->name('media.index');
    Route::post('/media', [InternalContentResourceController::class, 'storeMedia'])->middleware('internal-api.capability:media.upload')->name('media.store');
    Route::post('/media/fetch', [InternalContentResourceController::class, 'fetchRemoteMedia'])->middleware('internal-api.capability:media.upload')->name('media.fetch');
    // Registered before /media/{media} so "folders" is not read as a media id.
    Route::get('/media/folders', [InternalContentResourceController::class, 'mediaFolders'])->name('media.folders.index');
    Route::post('/media/folders', [InternalContentResourceController::class, 'storeMediaFolder'])->middleware('internal-api.capability:media.write')->name('media.folders.store');
    Route::patch('/media/{media}', [InternalContentResourceController::class, 'updateMedia'])->middleware('internal-api.capability:media.write')->name('media.update');
    Route::get('/media/{media}', [InternalContentResourceController::class, 'showMedia'])->name('media.show');
    Route::post('/media/{media}/replace', [InternalContentResourceController::class, 'replaceMedia'])->middleware('internal-api.capability:media.replace')->name('media.replace');
    Route::post('/media/{media}/move', [InternalContentResourceController::class, 'moveMedia'])->middleware('internal-api.capability:media.move')->name('media.move');
    Route::delete('/media/{media}', [InternalContentResourceController::class, 'deleteMedia'])->middleware('internal-api.capability:media.delete')->name('media.delete');
    Route::patch('/sites/{site}/branding', [InternalSiteController::class, 'updateBranding'])->middleware('internal-api.capability:site-settings.write')->name('sites.branding.update');
    Route::patch('/sites/{site}/head', [InternalSiteController::class, 'updateCustomHead'])->middleware('internal-api.capability:site-settings.write')->name('sites.head.update');
    Route::patch('/sites/{site}/seo', [InternalSiteController::class, 'updateSeoDefaults'])->middleware('internal-api.capability:site-settings.write')->name('sites.seo.update');
    Route::patch('/sites/{site}/contact-recipient', [InternalSiteController::class, 'updateContactRecipient'])->middleware('internal-api.capability:site-settings.write')->name('sites.contact-recipient.update');
    Route::put('/sites/{site}/locales', [InternalSiteController::class, 'updateLocales'])->middleware('internal-api.capability:site-settings.write')->name('sites.locales.update');
    Route::patch('/sites/{site}/timezone', [InternalSiteController::class, 'updateTimezone'])->middleware('internal-api.capability:site-settings.write')->name('sites.timezone.update');
    Route::get('/blocks', [InternalContentResourceController::class, 'blocks'])->name('blocks.index');
    Route::get('/blocks/{block}', [InternalContentResourceController::class, 'block'])->name('blocks.show');
    Route::patch('/blocks/{block}', [InternalContentResourceController::class, 'updateBlock'])->middleware('internal-api.capability:content.apply')->name('blocks.update');
    Route::get('/engagement/comments', [InternalEngagementController::class, 'comments'])->middleware('internal-api.capability:engagement.read')->name('engagement.comments.index');
    Route::patch('/engagement/comments/{commentEntry}', [InternalEngagementController::class, 'updateCommentStatus'])->middleware('internal-api.capability:engagement.moderate')->name('engagement.comments.update');
    Route::get('/engagement/ratings', [InternalEngagementController::class, 'ratings'])->middleware('internal-api.capability:engagement.read')->name('engagement.ratings.index');
    Route::post('/content/validate', [InternalContentPlanController::class, 'validatePlan'])->middleware('internal-api.capability:content.validate')->name('content.validate');
    Route::post('/content/apply', [InternalContentPlanController::class, 'apply'])->middleware('internal-api.capability:content.apply')->name('content.apply');
  });

Route::middleware(['web', 'install.required', AllowPagePreviewAccess::class])
  ->prefix('webadmin')
  ->name('admin.')
  ->group(function () {
    Route::get('/pages/{page}/preview', [PageController::class, 'preview'])->name('pages.preview');
  });

Route::middleware(['web', 'install.required', UseCmsAuthenticationRedirect::class, 'admin.access', CoalesceSearchIndexing::class])
  ->prefix('webadmin')
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
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::match(['put', 'patch'], '/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/locale', [ProfileController::class, 'updateLocale'])->name('profile.locale.update');
    Route::match(['put', 'patch'], '/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // Support — tickets are filed on WebBlocks Workbench, not stored here.
    // Top-level rather than under System: `access-system` belongs to whoever
    // maintains the installation, and the editor who cannot publish a page is
    // the person with something to report.
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
    Route::get('/support/new', [SupportController::class, 'create'])->name('support.create');
    Route::post('/support', [SupportController::class, 'store'])->name('support.store');
    Route::get('/support/{ticket}', [SupportController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket}/replies', [SupportController::class, 'comment'])->name('support.comment');
    Route::get('/pages/converter', [PageConverterController::class, 'index'])->name('pages.converter.index');
    Route::post('/pages/converter/analyze', [PageConverterController::class, 'analyze'])->name('pages.converter.analyze');
    Route::post('/pages/converter/create-draft', [PageConverterController::class, 'createDraft'])->name('pages.converter.create-draft');
    Route::post('/pages/{page}/workflow', [PageController::class, 'updateWorkflow'])->name('pages.workflow');
    Route::post('/pages/{page}/publish-page-owned-blocks', [PageController::class, 'publishPageOwnedBlocks'])->name('pages.publish-page-owned-blocks');
    Route::post('/pages/import-json', [PageImportController::class, 'store'])->name('pages.import.store');
    Route::delete('/pages/bulk', [PageController::class, 'bulkDestroy'])->name('pages.bulk-destroy');
    Route::post('/pages/{page}/assets/{type}', [PageAssetController::class, 'store'])->name('pages.assets.store');
    Route::put('/pages/{page}/assets/{page_asset}', [PageAssetController::class, 'update'])->name('pages.assets.update');
    Route::delete('/pages/{page}/assets/{page_asset}', [PageAssetController::class, 'destroy'])->name('pages.assets.destroy');
    Route::put('/sites/{site}/assets/{type}', [SiteAssetController::class, 'update'])->name('sites.assets.update');
    Route::get('/pages/{page}/duplicate', [PageDuplicateController::class, 'create'])->name('pages.duplicate.create');
    Route::post('/pages/{page}/duplicate', [PageDuplicateController::class, 'store'])->name('pages.duplicate.store');
    Route::get('/pages/{page}/move-site', [PageSiteMoveController::class, 'create'])->name('pages.move-site.create');
    Route::post('/pages/{page}/move-site', [PageSiteMoveController::class, 'store'])->name('pages.move-site.store');
    Route::get('/pages/{page}/revisions', [PageRevisionController::class, 'index'])->name('pages.revisions.index');
    Route::get('/pages/{page}/revisions/{revision}', [PageRevisionController::class, 'show'])->name('pages.revisions.show');
    Route::post('/pages/{page}/revisions/{revision}/candidate', [PageRevisionController::class, 'prepareCandidate'])->name('pages.revisions.candidate.prepare');
    Route::post('/pages/{page}/revision-candidates/{candidate}/apply', [PageRevisionController::class, 'applyCandidate'])->name('pages.revisions.candidate.apply');
    Route::delete('/pages/{page}/revision-candidates/{candidate}', [PageRevisionController::class, 'discardCandidate'])->name('pages.revisions.candidate.discard');
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
    Route::post('media/fetch', [MediaController::class, 'fetchRemote'])->name('media.fetch');
    Route::post('media/folders', [MediaController::class, 'storeFolder'])->name('media.folders.store');
    Route::delete('media/bulk', [MediaController::class, 'bulkDestroy'])->name('media.bulk-destroy');
    Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');
    Route::get('media/{media}/edit', [MediaController::class, 'edit'])->name('media.edit');
    Route::post('media/{media}/transforms/regenerate', [MediaController::class, 'regenerateTransforms'])->name('media.transforms.regenerate');
    Route::put('media/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::post('navigation/reorder', [NavigationItemController::class, 'reorder'])->name('navigation.reorder');
    Route::patch('navigation/{navigation}/visibility', [NavigationItemController::class, 'toggleVisibility'])->name('navigation.visibility');
    Route::resource('navigation', NavigationItemController::class)->except(['show']);
    Route::get('contact-messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
    Route::delete('contact-messages/bulk', [AdminContactMessageController::class, 'bulkDestroy'])->name('contact-messages.bulk-destroy');
    Route::get('contact-messages/{contactMessage}', [AdminContactMessageController::class, 'show'])->name('contact-messages.show');
    Route::patch('contact-messages/{contactMessage}/status', [AdminContactMessageController::class, 'updateStatus'])->name('contact-messages.status');
    Route::delete('contact-messages/{contactMessage}', [AdminContactMessageController::class, 'destroy'])->name('contact-messages.destroy');
    Route::get('engagement', [EngagementController::class, 'index'])->name('engagement.index');
    Route::get('engagement/comments', [EngagementController::class, 'comments'])->name('engagement.comments.index');
    Route::patch('engagement/comments/{commentEntry}/status', [EngagementController::class, 'updateCommentStatus'])->name('engagement.comments.status');
    Route::delete('engagement/comments/{commentEntry}', [EngagementController::class, 'destroyComment'])->name('engagement.comments.destroy');
    Route::get('engagement/ratings', [EngagementController::class, 'ratings'])->name('engagement.ratings.index');
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
      Route::delete('users/bulk', [UserController::class, 'bulkDestroy'])->name('users.bulk-destroy')->middleware('can:manage-users');
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
      Route::get('embedded-applications/{embedded_application}/assets', [EmbeddedApplicationAssetController::class, 'index'])->name('embedded-applications.assets.index');
      Route::post('embedded-applications/{embedded_application}/assets', [EmbeddedApplicationAssetController::class, 'store'])->name('embedded-applications.assets.store');
      Route::put('embedded-applications/{embedded_application}/assets/{type}/{filename}', [EmbeddedApplicationAssetController::class, 'update'])->name('embedded-applications.assets.update');
      Route::delete('embedded-applications/{embedded_application}/assets/{type}/{filename}', [EmbeddedApplicationAssetController::class, 'destroy'])->name('embedded-applications.assets.destroy');
      Route::resource('embedded-applications', EmbeddedApplicationController::class)->except(['show'])->parameters(['embedded-applications' => 'embedded_application']);
      Route::get('site-transfers/exports', [SiteExportController::class, 'index'])->name('site-transfers.exports.index');
      Route::post('site-transfers/exports', [SiteExportController::class, 'store'])->name('site-transfers.exports.store');
      Route::delete('site-transfers/exports/bulk', [SiteExportController::class, 'bulkDestroy'])->name('site-transfers.exports.bulk-destroy');
      Route::get('site-transfers/exports/{siteExport}', [SiteExportController::class, 'show'])->name('site-transfers.exports.show');
      Route::get('site-transfers/exports/{siteExport}/download', [SiteExportController::class, 'download'])->name('site-transfers.exports.download');
      Route::delete('site-transfers/exports/{siteExport}', [SiteExportController::class, 'destroy'])->name('site-transfers.exports.destroy');
      Route::get('site-transfers/imports', [SiteImportController::class, 'index'])->name('site-transfers.imports.index');
      Route::get('site-transfers/imports/create', [SiteImportController::class, 'create'])->name('site-transfers.imports.create');
      Route::post('site-transfers/imports/inspect', [SiteImportController::class, 'inspect'])->name('site-transfers.imports.inspect');
      Route::delete('site-transfers/imports/bulk', [SiteImportController::class, 'bulkDestroy'])->name('site-transfers.imports.bulk-destroy');
      Route::get('site-transfers/imports/{siteImport}', [SiteImportController::class, 'show'])->name('site-transfers.imports.show');
      Route::post('site-transfers/imports/{siteImport}/run', [SiteImportController::class, 'run'])->name('site-transfers.imports.run');
      Route::post('site-transfers/imports/{siteImport}/steps', [SiteImportController::class, 'step'])->name('site-transfers.imports.step');
      Route::delete('site-transfers/imports/{siteImport}/imported-site', [SiteImportController::class, 'discard'])->name('site-transfers.imports.discard');
      Route::delete('site-transfers/imports/{siteImport}', [SiteImportController::class, 'destroy'])->name('site-transfers.imports.destroy');
      Route::get('system/backups', [SystemBackupController::class, 'index'])->name('system.backups.index');
      Route::post('system/backups', [SystemBackupController::class, 'store'])->name('system.backups.store');
      Route::get('system/backups/upload', [SystemBackupController::class, 'createUpload'])->name('system.backups.upload');
      Route::post('system/backups/upload', [SystemBackupController::class, 'upload'])->name('system.backups.upload.store');
      Route::delete('system/backups/bulk', [SystemBackupController::class, 'bulkDestroy'])->name('system.backups.bulk-destroy');
      Route::post('system/backups/cleanup', [SystemBackupController::class, 'cleanup'])->name('system.backups.cleanup');
      Route::delete('system/backups/{backup}', [SystemBackupController::class, 'destroy'])->name('system.backups.destroy');
      Route::get('system/backups/{backup}', [SystemBackupController::class, 'show'])->name('system.backups.show');
      Route::get('system/backups/{backup}/download', [SystemBackupController::class, 'download'])->name('system.backups.download');
      Route::post('system/backups/{backup}/restore', [SystemBackupController::class, 'restore'])->name('system.backups.restore');
      Route::delete('system/backups/{backup}/restores/{restore}', [SystemBackupController::class, 'destroyRestore'])->name('system.backups.restores.destroy');
      Route::get('system/cleanup', [MaintenanceCleanupController::class, 'index'])->name('system.cleanup.index');
      Route::put('system/cleanup', [MaintenanceCleanupController::class, 'update'])->name('system.cleanup.update');
      Route::post('system/cleanup/{category}', [MaintenanceCleanupController::class, 'run'])->name('system.cleanup.run');
      Route::get('system/settings', [SystemSettingsController::class, 'edit'])->name('system.settings.edit');
      Route::get('system/information', SystemInformationController::class)->name('system.information');
      Route::put('system/settings', [SystemSettingsController::class, 'update'])->name('system.settings.update');
      Route::post('system/settings/mail/test', [SystemSettingsController::class, 'sendMailTest'])->name('system.settings.mail.test');
      Route::get('system/api-tokens', [CmsApiTokenController::class, 'index'])->name('system.api-tokens.index');
      Route::post('system/api-tokens', [CmsApiTokenController::class, 'store'])->name('system.api-tokens.store');
      Route::post('system/api-tokens/{token}/revoke', [CmsApiTokenController::class, 'revoke'])->name('system.api-tokens.revoke');
      Route::put('system/api-tokens/{token}', [CmsApiTokenController::class, 'update'])->name('system.api-tokens.update');
      Route::delete('system/api-tokens/{token}', [CmsApiTokenController::class, 'destroy'])->name('system.api-tokens.destroy');
      Route::get('plugins/catalog', [PluginCatalogController::class, 'index'])->name('plugins.catalog.index');
      Route::get('plugins/catalog/{handle}', [PluginCatalogController::class, 'show'])->name('plugins.catalog.show');
      Route::post('plugins/catalog/{handle}/install', [PluginCatalogController::class, 'install'])->name('plugins.catalog.install');
      Route::get('system/plugins', [SystemPluginController::class, 'index'])->name('system.plugins.index');
      Route::post('system/plugins/upload', [SystemPluginController::class, 'upload'])->name('system.plugins.upload');
      Route::post('system/plugins/{plugin}/update-from-catalog', [SystemPluginController::class, 'updateFromCatalog'])->name('system.plugins.update-from-catalog');
      Route::post('system/plugins/{plugin}/enable', [SystemPluginController::class, 'enable'])->name('system.plugins.enable');
      Route::post('system/plugins/{plugin}/setup', [SystemPluginController::class, 'setup'])->name('system.plugins.setup');
      Route::post('system/plugins/{plugin}/disable', [SystemPluginController::class, 'disable'])->name('system.plugins.disable');
      Route::delete('system/plugins/{plugin}/uninstall', [SystemPluginController::class, 'uninstall'])->name('system.plugins.uninstall');
      Route::get('system/plugins/{plugin}', [SystemPluginController::class, 'show'])->name('system.plugins.show');
      Route::get('system/search', [SystemSearchController::class, 'index'])->name('system.search.index');
      Route::post('system/search/rebuild', [SystemSearchController::class, 'rebuild'])->name('system.search.rebuild');
      Route::get('system/updates', [SystemUpdateController::class, 'index'])->name('system.updates.index');
      Route::get('system/updates/indicator', [SystemUpdateController::class, 'indicator'])->name('system.updates.indicator');
      Route::get('system/updates/check', [SystemUpdateController::class, 'check'])->name('system.updates.check');
      Route::post('system/updates', [SystemUpdateController::class, 'store'])->name('system.updates.store');
      Route::get('system/icons', [IconCatalogController::class, 'index'])->name('system.icons.index');
      Route::post('system/icons/sync-webblocks-ui', [IconCatalogController::class, 'sync'])->name('system.icons.sync-webblocks-ui');
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
