<?php

namespace WebBlocks\Cms;

use FilesystemIterator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler as ExceptionsHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebBlocks\Cms\Console\AdminTranslationAuditCommand;
use WebBlocks\Cms\Console\BlockTypeContractsAuditCommand;
use WebBlocks\Cms\Console\CatalogRepairCommand;
use WebBlocks\Cms\Console\ContactMailDiagnoseCommand;
use WebBlocks\Cms\Console\DoctorNativeLocalCommand;
use WebBlocks\Cms\Console\GenerateUpdateSigningKeyCommand;
use WebBlocks\Cms\Console\ImportDemoMedia;
use WebBlocks\Cms\Console\InstallWebBlocksCmsCommand;
use WebBlocks\Cms\Console\MaintenanceCleanupCommand;
use WebBlocks\Cms\Console\MediaVariantsCommand;
use WebBlocks\Cms\Console\PackageStatusCommand;
use WebBlocks\Cms\Console\PrunePromotedStagedUpdatesCommand;
use WebBlocks\Cms\Console\PublishUpdateCommand;
use WebBlocks\Cms\Console\ResetPrimitiveBlocksCommand;
use WebBlocks\Cms\Console\SearchRebuildCommand;
use WebBlocks\Cms\Console\SiteCloneCommand;
use WebBlocks\Cms\Console\SiteDeleteCommand;
use WebBlocks\Cms\Console\SiteExportCommand;
use WebBlocks\Cms\Console\SiteImportCommand;
use WebBlocks\Cms\Console\SitePromotionApplyCommand;
use WebBlocks\Cms\Console\SitePromotionDryRunCommand;
use WebBlocks\Cms\Console\SitePromotionInspectCommand;
use WebBlocks\Cms\Console\SmokeNativeLocalCommand;
use WebBlocks\Cms\Console\StarterContentCommand;
use WebBlocks\Cms\Console\SyncCoreBlockTypesCommand;
use WebBlocks\Cms\Console\SyncWebBlocksUiIconsCommand;
use WebBlocks\Cms\Console\SystemBackupCleanupCommand;
use WebBlocks\Cms\Console\SystemBackupRestoreCommand;
use WebBlocks\Cms\Console\SystemUpdatePruneRunsCommand;
use WebBlocks\Cms\Console\SystemUpdateRunsCommand;
use WebBlocks\Cms\Http\Middleware\AuthorizePluginPermission;
use WebBlocks\Cms\Http\Middleware\RedirectIfInstalled;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Http\Middleware\RequireAdminAccess;
use WebBlocks\Cms\Http\Middleware\RequireInternalApiCapability;
use WebBlocks\Cms\Http\Middleware\RequireInternalApiToken;
use WebBlocks\Cms\Http\Middleware\UseCmsAuthenticationRedirect;
use WebBlocks\Cms\Models\BlockMedia;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\InternalContentApi\InternalApiRateLimit;
use WebBlocks\Cms\Support\NativeLocal\NativeLocalProbe;
use WebBlocks\Cms\Support\NativeLocal\SystemNativeLocalProbe;
use WebBlocks\Cms\Support\Plugins\InstalledPluginDefinitionFactory;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Plugins\PluginAccessResolver;
use WebBlocks\Cms\Support\Plugins\PluginAdminExtensionRegistry;
use WebBlocks\Cms\Support\Plugins\PluginApiRouteRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginAuthorizationRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;
use WebBlocks\Cms\Support\Plugins\PluginBlockRegistry;
use WebBlocks\Cms\Support\Plugins\PluginBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\Plugins\PluginCommandRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginMigrationRunner;
use WebBlocks\Cms\Support\Plugins\PluginPermissionRegistry;
use WebBlocks\Cms\Support\Plugins\PluginPublicAssetRegistry;
use WebBlocks\Cms\Support\Plugins\PluginPublicRouteRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRouteRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginRuntimeRefresher;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteTransferDisk;
use WebBlocks\Cms\Support\WebBlocks;

class WebBlocksCmsServiceProvider extends ServiceProvider
{
  public const PACKAGE_NAME = 'webblocks-cms';

  public const VIEW_NAMESPACE = 'webblocks-cms';

  public const DIAGNOSTIC_ROUTE_FILE = 'diagnostics.php';

  public const PACKAGE_ADMIN_ROUTE_FILE = 'admin.php';

  public const PACKAGE_AUTH_ROUTE_FILE = 'auth.php';

  public const PACKAGE_INSTALL_ROUTE_FILE = 'install.php';

  public const PACKAGE_PUBLIC_ROUTE_FILE = 'public.php';

  public const DIAGNOSTIC_ROUTE_NAME = 'webblocks-cms.diagnostics.package-status';

  public const PACKAGE_ADMIN_ROUTE_NAME = 'admin.webblocks-cms.runtime-status';

  public const PACKAGE_PUBLIC_ROUTE_NAME = 'webblocks-cms.public.runtime-status';

  public const DIAGNOSTIC_ROUTE_PATH = '/_webblocks-cms/diagnostics/package-status';

  public const PACKAGE_ADMIN_ROUTE_PATH = '/webadmin/_webblocks-cms/runtime-status';

  public const PACKAGE_PUBLIC_ROUTE_PATH = '/_webblocks-cms/runtime-status';

  public const DIAGNOSTIC_ROUTE_LOADING_CONFIG = 'webblocks-cms.diagnostics.load_routes';

  public const PACKAGE_ADMIN_ROUTE_LOADING_CONFIG = 'webblocks-cms.admin.load_routes';

  public const PACKAGE_ADMIN_STATUS_ROUTE_LOADING_CONFIG = 'webblocks-cms.admin.load_status_route';

  public const PACKAGE_PUBLIC_ROUTE_LOADING_CONFIG = 'webblocks-cms.public.load_routes';

  public const PACKAGE_PUBLIC_STATUS_ROUTE_LOADING_CONFIG = 'webblocks-cms.public.load_status_route';

  public const PACKAGE_MIGRATION_LOADING_CONFIG = 'webblocks-cms.boundaries.load_migrations';

  public const PACKAGE_CONFIG_DEFAULTS = [
    'webblocks-cms.php',
    'cms.php',
    'contact.php',
    'demo_media.php',
    'media_transforms.php',
    'webblocks-plugins.php',
    'webblocks-updates.php',
  ];

  public const PACKAGE_ROUTE_FILES = [
    'admin.php',
    'auth.php',
    'diagnostics.php',
    'install.php',
    'public.php',
  ];

  public const PACKAGE_VIEW_FILES = [
    'admin/runtime-status.blade.php',
    'auth/forgot-password.blade.php',
    'auth/login.blade.php',
    'auth/reset-password.blade.php',
    'admin/partials/audit-actor.blade.php',
    'admin/partials/flash.blade.php',
    'admin/partials/listing-filters.blade.php',
    'admin/partials/page-actions.blade.php',
    'admin/partials/page-header.blade.php',
    'admin/partials/pagination.blade.php',
    'admin/system/icons/index.blade.php',
    'admin/system/icons/partials/edit-modal.blade.php',
    'admin/plugins/catalog/index.blade.php',
    'admin/system/plugins/index.blade.php',
    'admin/system/plugins/settings.blade.php',
    'admin/system/plugins/show.blade.php',
    'admin/system/plugins/setup-required.blade.php',
    'components/brand-mark.blade.php',
    'components/admin/form-actions.blade.php',
    'diagnostics/package-status.blade.php',
    'layouts/admin.blade.php',
    'layouts/public.blade.php',
    'pages/show.blade.php',
    'pages/partials/block.blade.php',
    'pages/partials/slot.blade.php',
    'public/pages/show.blade.php',
    'search/show.blade.php',
    'search/partials/modal.blade.php',
    'public/search/show.blade.php',
    'public/runtime-status.blade.php',
  ];

  public const PUBLIC_RENDERING_RUNTIME_FILES = [
    'Support/Blocks/PublicBodyEndRegistry.php',
    'Support/Blocks/PublicNavbarDrawerRegistry.php',
    'Support/Blocks/PublicOverlayRegistry.php',
    'Support/Blocks/TrustedHtmlOverlayExtractor.php',
    'Support/Pages/PageRouteResolver.php',
    'Support/Pages/PublicPagePresenter.php',
    'Support/Pages/PublicSharedSlotResolver.php',
    'Support/PublicRendering/SiteAssetResolver.php',
    'Support/PublicRendering/SlotWrapperResolver.php',
    'Support/Search/PublicSearchQuery.php',
    'Support/Sites/ResolvedSite.php',
    'Support/Sites/SiteResolver.php',
    'Support/Visitors/VisitorEventLogger.php',
  ];

  public const PUBLIC_MODEL_FOUNDATION_FILES = [
    'Models/Block.php',
    'Models/ContactMessage.php',
    'Models/Locale.php',
    'Models/Page.php',
    'Models/PageSlot.php',
    'Models/PageTranslation.php',
    'Models/PublicSearchIndex.php',
    'Models/Site.php',
    'Models/SiteDomain.php',
    'Models/SystemSetting.php',
    'Models/VisitorEvent.php',
  ];

  public const ROOT_PUBLIC_MODEL_WRAPPER_FILES = [
    'Models/Block.php',
    'Models/ContactMessage.php',
    'Models/Locale.php',
    'Models/Page.php',
    'Models/PageSlot.php',
    'Models/PageTranslation.php',
    'Models/PublicSearchIndex.php',
    'Models/Site.php',
    'Models/SiteDomain.php',
    'Models/SystemSetting.php',
    'Models/VisitorEvent.php',
  ];

  public const ROOT_PUBLIC_RENDERING_RUNTIME_WRAPPER_FILES = [
    'Support/Blocks/PublicBodyEndRegistry.php',
    'Support/Blocks/PublicNavbarDrawerRegistry.php',
    'Support/Blocks/PublicOverlayRegistry.php',
    'Support/Blocks/TrustedHtmlOverlayExtractor.php',
    'Support/Pages/PageRouteResolver.php',
    'Support/Pages/PublicPagePresenter.php',
    'Support/Pages/PublicSharedSlotResolver.php',
    'Support/PublicRendering/SiteAssetResolver.php',
    'Support/PublicRendering/SlotWrapperResolver.php',
    'Support/Search/PublicSearchQuery.php',
    'Support/Sites/ResolvedSite.php',
    'Support/Sites/SiteResolver.php',
    'Support/Visitors/VisitorEventLogger.php',
  ];

  public const ICON_VIEW_FILES = [
    'admin/system/icons/index.blade.php',
    'admin/system/icons/partials/edit-modal.blade.php',
  ];

  public const ADMIN_RUNTIME_VIEW_FILES = [
    'layouts/admin.blade.php',
    'admin/block-types/_form.blade.php',
    'admin/block-types/create.blade.php',
    'admin/block-types/edit.blade.php',
    'admin/block-types/index.blade.php',
    'admin/block-types/partials/contract-items.blade.php',
    'admin/block-types/partials/contract-modal.blade.php',
    'admin/block-types/partials/edit-modal.blade.php',
    'admin/blocks/_form.blade.php',
    'admin/blocks/_type-picker.blade.php',
    'admin/blocks/create.blade.php',
    'admin/blocks/edit.blade.php',
    'admin/blocks/index.blade.php',
    'admin/blocks/partials/column-items-editor-row.blade.php',
    'admin/blocks/partials/column-items-editor.blade.php',
    'admin/contact-messages/index.blade.php',
    'admin/contact-messages/show.blade.php',
    'admin/dashboard.blade.php',
    'admin/media/_asset-card.blade.php',
    'admin/media/asset-picker-panel.blade.php',
    'admin/media/edit.blade.php',
    'admin/media/index.blade.php',
    'admin/navigation/_form.blade.php',
    'admin/navigation/create.blade.php',
    'admin/navigation/edit.blade.php',
    'admin/navigation/index.blade.php',
    'admin/navigation/partials/modal.blade.php',
    'admin/navigation/partials/tree-list.blade.php',
    'admin/domains/index.blade.php',
    'admin/locales/form.blade.php',
    'admin/locales/index.blade.php',
    'admin/page-layout-slots/_form.blade.php',
    'admin/page-layout-slots/create.blade.php',
    'admin/page-layout-slots/edit.blade.php',
    'admin/page-layouts/_form.blade.php',
    'admin/page-layouts/create.blade.php',
    'admin/page-layouts/edit.blade.php',
    'admin/page-layouts/index.blade.php',
    'admin/page-layouts/partials/slots-card.blade.php',
    'admin/pages/_form.blade.php',
    'admin/pages/create.blade.php',
    'admin/pages/duplicate.blade.php',
    'admin/pages/edit.blade.php',
    'admin/pages/index.blade.php',
    'admin/pages/move-site.blade.php',
    'admin/pages/partials/block-outline-item.blade.php',
    'admin/pages/partials/details-modal.blade.php',
    'admin/pages/partials/import-modal.blade.php',
    'admin/pages/partials/inline-block-builder.blade.php',
    'admin/pages/partials/inline-block-fields.blade.php',
    'admin/pages/partials/inline-block-item.blade.php',
    'admin/pages/partials/layout-slot-summary-card.blade.php',
    'admin/pages/partials/page-assets-modal-form.blade.php',
    'admin/pages/partials/page-assets-modals.blade.php',
    'admin/pages/partials/page-assets-tab.blade.php',
    'admin/pages/partials/slot-block-delete-modal.blade.php',
    'admin/pages/partials/slot-block-modal.blade.php',
    'admin/pages/partials/slot-block-picker.blade.php',
    'admin/pages/partials/slot-block-row.blade.php',
    'admin/pages/partials/slots-card.blade.php',
    'admin/pages/revisions/index.blade.php',
    'admin/pages/show.blade.php',
    'admin/pages/slot-blocks.blade.php',
    'admin/pages/translations/form.blade.php',
    'admin/sites/clone.blade.php',
    'admin/sites/delete.blade.php',
    'admin/sites/domains/index.blade.php',
    'admin/sites/domains/partials/manage-modal.blade.php',
    'admin/sites/domains/partials/remove-modal.blade.php',
    'admin/sites/form.blade.php',
    'admin/sites/index.blade.php',
    'admin/sites/assets.blade.php',
    'admin/sites/partials/assets-tab.blade.php',
    'admin/sites/partials/details-modal.blade.php',
    'admin/sites/partials/site-variable-modal-form.blade.php',
    'admin/sites/partials/site-variable-modals.blade.php',
    'admin/sites/partials/theme-tab.blade.php',
    'admin/sites/partials/variables-tab.blade.php',
    'admin/reports/visitors/index.blade.php',
    'admin/slot-types/index.blade.php',
    'admin/plugins/catalog/index.blade.php',
    'admin/shared-slots/_form.blade.php',
    'admin/shared-slots/create.blade.php',
    'admin/shared-slots/edit.blade.php',
    'admin/shared-slots/index.blade.php',
    'admin/shared-slots/revisions/index.blade.php',
    'admin/shared-slots/revisions/show.blade.php',
    'admin/shared-slots/slot-blocks.blade.php',
    'admin/system/plugins/index.blade.php',
    'admin/system/plugins/settings.blade.php',
    'admin/system/plugins/show.blade.php',
    'admin/system/plugins/setup-required.blade.php',
    'admin/system/search.blade.php',
    'admin/system/settings.blade.php',
  ];

  public const ROOT_ICON_VIEW_WRAPPER_FILES = [
    'admin/system/icons/index.blade.php',
    'admin/system/icons/partials/edit-modal.blade.php',
  ];

  public const ROOT_ADMIN_RUNTIME_VIEW_WRAPPER_FILES = [
    'layouts/admin.blade.php',
    'admin/block-types/_form.blade.php',
    'admin/block-types/create.blade.php',
    'admin/block-types/edit.blade.php',
    'admin/block-types/index.blade.php',
    'admin/block-types/partials/contract-items.blade.php',
    'admin/block-types/partials/contract-modal.blade.php',
    'admin/block-types/partials/edit-modal.blade.php',
    'admin/blocks/_form.blade.php',
    'admin/blocks/_type-picker.blade.php',
    'admin/blocks/create.blade.php',
    'admin/blocks/edit.blade.php',
    'admin/blocks/index.blade.php',
    'admin/blocks/partials/column-items-editor-row.blade.php',
    'admin/blocks/partials/column-items-editor.blade.php',
    'admin/contact-messages/index.blade.php',
    'admin/contact-messages/show.blade.php',
    'admin/dashboard.blade.php',
    'admin/media/_asset-card.blade.php',
    'admin/media/asset-picker-panel.blade.php',
    'admin/media/edit.blade.php',
    'admin/media/index.blade.php',
    'admin/navigation/_form.blade.php',
    'admin/navigation/create.blade.php',
    'admin/navigation/edit.blade.php',
    'admin/navigation/index.blade.php',
    'admin/navigation/partials/modal.blade.php',
    'admin/navigation/partials/tree-list.blade.php',
    'admin/domains/index.blade.php',
    'admin/locales/form.blade.php',
    'admin/locales/index.blade.php',
    'admin/page-layout-slots/_form.blade.php',
    'admin/page-layout-slots/create.blade.php',
    'admin/page-layout-slots/edit.blade.php',
    'admin/page-layouts/_form.blade.php',
    'admin/page-layouts/create.blade.php',
    'admin/page-layouts/edit.blade.php',
    'admin/page-layouts/index.blade.php',
    'admin/page-layouts/partials/slots-card.blade.php',
    'admin/pages/_form.blade.php',
    'admin/pages/create.blade.php',
    'admin/pages/duplicate.blade.php',
    'admin/pages/edit.blade.php',
    'admin/pages/index.blade.php',
    'admin/pages/move-site.blade.php',
    'admin/pages/partials/block-outline-item.blade.php',
    'admin/pages/partials/details-modal.blade.php',
    'admin/pages/partials/import-modal.blade.php',
    'admin/pages/partials/inline-block-builder.blade.php',
    'admin/pages/partials/inline-block-fields.blade.php',
    'admin/pages/partials/inline-block-item.blade.php',
    'admin/pages/partials/layout-slot-summary-card.blade.php',
    'admin/pages/partials/page-assets-modal-form.blade.php',
    'admin/pages/partials/page-assets-modals.blade.php',
    'admin/pages/partials/page-assets-tab.blade.php',
    'admin/pages/partials/slot-block-delete-modal.blade.php',
    'admin/pages/partials/slot-block-modal.blade.php',
    'admin/pages/partials/slot-block-picker.blade.php',
    'admin/pages/partials/slot-block-row.blade.php',
    'admin/pages/partials/slots-card.blade.php',
    'admin/pages/revisions/index.blade.php',
    'admin/pages/show.blade.php',
    'admin/pages/slot-blocks.blade.php',
    'admin/pages/translations/form.blade.php',
    'admin/reports/visitors/index.blade.php',
    'admin/slot-types/index.blade.php',
    'admin/sites/clone.blade.php',
    'admin/sites/delete.blade.php',
    'admin/sites/domains/index.blade.php',
    'admin/sites/domains/partials/manage-modal.blade.php',
    'admin/sites/domains/partials/remove-modal.blade.php',
    'admin/sites/form.blade.php',
    'admin/sites/index.blade.php',
    'admin/sites/assets.blade.php',
    'admin/sites/partials/details-modal.blade.php',
    'admin/sites/partials/site-variable-modal-form.blade.php',
    'admin/sites/partials/site-variable-modals.blade.php',
    'admin/sites/partials/theme-tab.blade.php',
    'admin/sites/partials/variables-tab.blade.php',
    'admin/shared-slots/_form.blade.php',
    'admin/shared-slots/create.blade.php',
    'admin/shared-slots/edit.blade.php',
    'admin/shared-slots/index.blade.php',
    'admin/shared-slots/revisions/index.blade.php',
    'admin/shared-slots/revisions/show.blade.php',
    'admin/shared-slots/slot-blocks.blade.php',
    'admin/system/search.blade.php',
    'admin/system/settings.blade.php',
  ];

  public const SHARED_ADMIN_VIEW_FILES = [
    'admin/partials/audit-actor.blade.php',
    'admin/partials/flash.blade.php',
    'admin/partials/listing-filters.blade.php',
    'admin/partials/page-actions.blade.php',
    'admin/partials/page-header.blade.php',
    'admin/partials/pagination.blade.php',
    'components/admin/form-actions.blade.php',
  ];

  public const ROOT_SHARED_ADMIN_VIEW_WRAPPER_FILES = [
    'admin/partials/audit-actor.blade.php',
    'admin/partials/flash.blade.php',
    'admin/partials/listing-filters.blade.php',
    'admin/partials/page-actions.blade.php',
    'admin/partials/page-header.blade.php',
    'admin/partials/pagination.blade.php',
    'components/admin/form-actions.blade.php',
  ];

  public const PACKAGE_SEEDER_FILES = [
    'CoreCatalogSeeder.php',
    'IconCatalogSeeder.php',
    'PageTypeSeeder.php',
    'LayoutTypeSeeder.php',
    'SlotTypeSeeder.php',
  ];

  public const PACKAGE_PUBLIC_ASSET_FILES = [
    'cms/brand/apple-touch-icon.png',
    'cms/brand/favicon.svg',
    'cms/brand/favicon-16x16.png',
    'cms/brand/favicon-32x32.png',
    'cms/brand/logo-mark-dark.svg',
    'cms/brand/logo-mark-on-accent.svg',
    'cms/brand/logo-mark.svg',
    'cms/css/admin.css',
    'cms/css/email.css',
    'cms/css/guest.css',
    'cms/css/public.css',
    'cms/js/admin/asset-picker.js',
    'cms/js/admin/api-token-capabilities.js',
    'cms/js/admin/api-token-copy.js',
    'cms/js/admin/builder-items.js',
    'cms/js/admin/core.js',
    'cms/js/admin/embedded-application-settings.js',
    'cms/js/admin/gallery-items.js',
    'cms/js/admin/inline-block-builder.js',
    'cms/js/admin/listing-bulk-actions.js',
    'cms/js/admin/media-copy.js',
    'cms/js/admin/page-builder-modals.js',
    'cms/js/admin/page-slot-source-modals.js',
    'cms/js/admin/rich-text-editor.js',
    'cms/js/admin/slot-block-delete-modal.js',
    'cms/js/admin/slot-block-tree.js',
    'cms/js/admin-sortable-list.js',
    'cms/js/public/public-search-modal.js',
    'cms/js/public/sidebar-navigation.js',
    'cms/js/privacy-consent-sync.js',
    'cms/package-boundary.json',
  ];

  public const ROOT_PUBLIC_ASSET_COMPATIBILITY_FILES = [
    'cms/brand/apple-touch-icon.png',
    'cms/brand/favicon.svg',
    'cms/brand/favicon-16x16.png',
    'cms/brand/favicon-32x32.png',
    'cms/brand/logo-mark-dark.svg',
    'cms/brand/logo-mark-on-accent.svg',
    'cms/brand/logo-mark.svg',
    'cms/css/admin.css',
    'cms/css/email.css',
    'cms/css/guest.css',
    'cms/css/public.css',
    'cms/js/admin/asset-picker.js',
    'cms/js/admin/api-token-capabilities.js',
    'cms/js/admin/api-token-copy.js',
    'cms/js/admin/builder-items.js',
    'cms/js/admin/core.js',
    'cms/js/admin/embedded-application-settings.js',
    'cms/js/admin/gallery-items.js',
    'cms/js/admin/inline-block-builder.js',
    'cms/js/admin/listing-bulk-actions.js',
    'cms/js/admin/media-copy.js',
    'cms/js/admin/page-builder-modals.js',
    'cms/js/admin/page-slot-source-modals.js',
    'cms/js/admin/rich-text-editor.js',
    'cms/js/admin/slot-block-delete-modal.js',
    'cms/js/admin/slot-block-tree.js',
    'cms/js/admin-sortable-list.js',
    'cms/js/public/public-search-modal.js',
    'cms/js/public/sidebar-navigation.js',
    'cms/js/privacy-consent-sync.js',
  ];

  public const PACKAGE_STUB_FILES = [
    'starter/README.md',
    'starter/composer.json.stub',
    'starter/env.example.stub',
  ];

  public const LOW_RISK_RUNTIME_SUPPORT_FILES = [
    'Admin/AdminPagination.php',
    'BlockTypes/BlockTypeIndexState.php',
    'Media/MediaIndexState.php',
    'Pages/PageIndexState.php',
  ];

  public const ICON_RUNTIME_FILES = [
    'Console/SyncWebBlocksUiIconsCommand.php',
    'Http/Controllers/Admin/IconCatalogController.php',
    'Http/Requests/Admin/IconCatalogItemUpdateRequest.php',
    'Support/Icons/IconCatalog.php',
    'Support/Icons/WebBlocksIconManifestSyncer.php',
  ];

  public const ROOT_ICON_RUNTIME_WRAPPER_FILES = [
    'Console/Commands/SyncWebBlocksUiIconsCommand.php',
    'Http/Controllers/Admin/IconCatalogController.php',
    'Http/Requests/Admin/IconCatalogItemUpdateRequest.php',
    'Support/Icons/IconCatalog.php',
    'Support/Icons/WebBlocksIconManifestSyncer.php',
  ];

  public const ADMIN_RUNTIME_FILES = [
    'Http/Controllers/Admin/BlockController.php',
    'Http/Controllers/Admin/BlockTypeController.php',
    'Http/Controllers/Admin/ContactMessageController.php',
    'Http/Controllers/Admin/DashboardController.php',
    'Http/Controllers/Admin/LocaleController.php',
    'Http/Controllers/Admin/MediaController.php',
    'Http/Controllers/Admin/NavigationItemController.php',
    'Http/Controllers/Admin/PageAssetController.php',
    'Http/Controllers/Admin/PageController.php',
    'Http/Controllers/Admin/PageDuplicateController.php',
    'Http/Controllers/Admin/PageImportController.php',
    'Http/Controllers/Admin/PageLayoutController.php',
    'Http/Controllers/Admin/PageLayoutSlotController.php',
    'Http/Controllers/Admin/PageRevisionController.php',
    'Http/Controllers/Admin/PageSiteMoveController.php',
    'Http/Controllers/Admin/PageSlotController.php',
    'Http/Controllers/Admin/PageTranslationController.php',
    'Http/Controllers/Admin/SharedSlotController.php',
    'Http/Controllers/Admin/SharedSlotRevisionController.php',
    'Http/Controllers/Admin/SiteController.php',
    'Http/Controllers/Admin/SiteDomainController.php',
    'Http/Controllers/Admin/SiteVariableController.php',
    'Http/Controllers/Admin/SlotTypeController.php',
    'Http/Controllers/Admin/SystemSearchController.php',
    'Http/Controllers/Admin/SystemSettingsController.php',
    'Http/Controllers/Admin/VisitorReportController.php',
    'Http/Requests/Admin/BlockRequest.php',
    'Http/Requests/Admin/BlockTypeRequest.php',
    'Http/Requests/Admin/DuplicatePageRequest.php',
    'Http/Requests/Admin/LocaleRequest.php',
    'Http/Requests/Admin/MediaFolderRequest.php',
    'Http/Requests/Admin/MediaUpdateRequest.php',
    'Http/Requests/Admin/MediaUploadRequest.php',
    'Http/Requests/Admin/MovePageSiteRequest.php',
    'Http/Requests/Admin/NavigationItemReorderRequest.php',
    'Http/Requests/Admin/NavigationItemRequest.php',
    'Http/Requests/Admin/PageAssetRequest.php',
    'Http/Requests/Admin/PageImportRequest.php',
    'Http/Requests/Admin/PageLayoutRequest.php',
    'Http/Requests/Admin/PageLayoutSlotRequest.php',
    'Http/Requests/Admin/PageRequest.php',
    'Http/Requests/Admin/PageTranslationRequest.php',
    'Http/Requests/Admin/SharedSlotRequest.php',
    'Http/Requests/Admin/SiteCloneRequest.php',
    'Http/Requests/Admin/SiteDeleteRequest.php',
    'Http/Requests/Admin/SiteDomainStoreRequest.php',
    'Http/Requests/Admin/SiteDomainUpdateRequest.php',
    'Http/Requests/Admin/SiteRequest.php',
    'Http/Requests/Admin/SiteVariableRequest.php',
    'Http/Requests/Admin/SystemSettingsRequest.php',
    'Http/Requests/Admin/StorePageSlotRequest.php',
    'Http/Requests/Admin/SyncPageLayoutSlotsRequest.php',
    'Http/Requests/Admin/UpdatePageSlotSourceRequest.php',
    'Models/SiteLocale.php',
    'Models/SiteVariable.php',
    'Support/BlockTypes/BlockTypeContractRegistry.php',
    'Support/Blocks/BlockDeletionManager.php',
    'Support/Blocks/BlockPayloadWriter.php',
    'Support/Blocks/BlockTranslationResolver.php',
    'Support/Locales/LocaleLifecycleGuard.php',
    'Support/Locales/LocaleLifecycleReport.php',
    'Support/Media/MediaKindResolver.php',
    'Support/Media/MediaUsageFilter.php',
    'Support/Media/MediaUsageResolver.php',
    'Support/Navigation/NavigationTree.php',
    'Support/Pages/PageDuplicateResult.php',
    'Support/Pages/PageDuplicateValidationResult.php',
    'Support/Pages/PageDuplicateValidator.php',
    'Support/Pages/PageDuplicator.php',
    'Support/Pages/PageJsonImporter.php',
    'Support/Pages/PageLayoutManager.php',
    'Support/Pages/PageLayoutSlotComparison.php',
    'Support/Pages/PageLayoutSlotSyncer.php',
    'Support/Pages/PageRevisionManager.php',
    'Support/Pages/PageSiteMoveResult.php',
    'Support/Pages/PageSiteMoveValidationResult.php',
    'Support/Pages/PageSiteMoveValidator.php',
    'Support/Pages/PageSiteMover.php',
    'Support/Pages/PageWorkflowManager.php',
    'Support/Search/PublicSearchSchema.php',
    'Support/SharedSlots/SharedSlotRevisionManager.php',
    'Support/SharedSlots/SharedSlotSchema.php',
    'Support/SharedSlots/SharedSlotSourcePageManager.php',
    'Support/Sites/SiteCloneOptions.php',
    'Support/Sites/SiteCloneResult.php',
    'Support/Sites/SiteCloneService.php',
    'Support/Sites/SiteDeleteResult.php',
    'Support/Sites/SiteDeleteService.php',
    'Support/Sites/SiteDomainManager.php',
    'Support/Sites/SiteDomainNormalizer.php',
    'Support/Sites/SiteHandle.php',
    'Support/Visitors/VisitorReportsQuery.php',
  ];

  public const ROOT_ADMIN_RUNTIME_WRAPPER_FILES = [
    'Http/Controllers/Admin/BlockController.php',
    'Http/Controllers/Admin/BlockTypeController.php',
    'Http/Controllers/Admin/ContactMessageController.php',
    'Http/Controllers/Admin/DashboardController.php',
    'Http/Controllers/Admin/LocaleController.php',
    'Http/Controllers/Admin/MediaController.php',
    'Http/Controllers/Admin/NavigationItemController.php',
    'Http/Controllers/Admin/PageAssetController.php',
    'Http/Controllers/Admin/PageController.php',
    'Http/Controllers/Admin/PageDuplicateController.php',
    'Http/Controllers/Admin/PageImportController.php',
    'Http/Controllers/Admin/PageLayoutController.php',
    'Http/Controllers/Admin/PageLayoutSlotController.php',
    'Http/Controllers/Admin/PageRevisionController.php',
    'Http/Controllers/Admin/PageSiteMoveController.php',
    'Http/Controllers/Admin/PageSlotController.php',
    'Http/Controllers/Admin/PageTranslationController.php',
    'Http/Controllers/Admin/SharedSlotController.php',
    'Http/Controllers/Admin/SharedSlotRevisionController.php',
    'Http/Controllers/Admin/SiteAssetController.php',
    'Http/Controllers/Admin/SiteController.php',
    'Http/Controllers/Admin/SiteDomainController.php',
    'Http/Controllers/Admin/SiteVariableController.php',
    'Http/Controllers/Admin/SlotTypeController.php',
    'Http/Controllers/Admin/SystemSearchController.php',
    'Http/Controllers/Admin/SystemSettingsController.php',
    'Http/Controllers/Admin/VisitorReportController.php',
    'Http/Requests/Admin/BlockRequest.php',
    'Http/Requests/Admin/BlockTypeRequest.php',
    'Http/Requests/Admin/DuplicatePageRequest.php',
    'Http/Requests/Admin/LocaleRequest.php',
    'Http/Requests/Admin/MediaFolderRequest.php',
    'Http/Requests/Admin/MediaUpdateRequest.php',
    'Http/Requests/Admin/MediaUploadRequest.php',
    'Http/Requests/Admin/MovePageSiteRequest.php',
    'Http/Requests/Admin/NavigationItemReorderRequest.php',
    'Http/Requests/Admin/NavigationItemRequest.php',
    'Http/Requests/Admin/PageAssetRequest.php',
    'Http/Requests/Admin/PageImportRequest.php',
    'Http/Requests/Admin/PageLayoutRequest.php',
    'Http/Requests/Admin/PageLayoutSlotRequest.php',
    'Http/Requests/Admin/PageRequest.php',
    'Http/Requests/Admin/PageTranslationRequest.php',
    'Http/Requests/Admin/SharedSlotRequest.php',
    'Http/Requests/Admin/SiteAssetRequest.php',
    'Http/Requests/Admin/SiteCloneRequest.php',
    'Http/Requests/Admin/SiteDeleteRequest.php',
    'Http/Requests/Admin/SiteDomainStoreRequest.php',
    'Http/Requests/Admin/SiteDomainUpdateRequest.php',
    'Http/Requests/Admin/SiteRequest.php',
    'Http/Requests/Admin/SiteVariableRequest.php',
    'Http/Requests/Admin/SystemSettingsRequest.php',
    'Http/Requests/Admin/StorePageSlotRequest.php',
    'Http/Requests/Admin/SyncPageLayoutSlotsRequest.php',
    'Http/Requests/Admin/UpdatePageSlotSourceRequest.php',
    'Models/SiteLocale.php',
    'Models/SiteVariable.php',
    'Support/BlockTypes/BlockTypeContractRegistry.php',
    'Support/Blocks/BlockDeletionManager.php',
    'Support/Blocks/BlockPayloadWriter.php',
    'Support/Blocks/BlockTranslationResolver.php',
    'Support/Locales/LocaleLifecycleGuard.php',
    'Support/Locales/LocaleLifecycleReport.php',
    'Support/Media/MediaKindResolver.php',
    'Support/Media/MediaUsageFilter.php',
    'Support/Media/MediaUsageResolver.php',
    'Support/Navigation/NavigationTree.php',
    'Support/Pages/PageDuplicateResult.php',
    'Support/Pages/PageDuplicateValidationResult.php',
    'Support/Pages/PageDuplicateValidator.php',
    'Support/Pages/PageDuplicator.php',
    'Support/Pages/PageJsonImporter.php',
    'Support/Pages/PageLayoutManager.php',
    'Support/Pages/PageLayoutSlotComparison.php',
    'Support/Pages/PageLayoutSlotSyncer.php',
    'Support/Pages/PageRevisionManager.php',
    'Support/Pages/PageSiteMoveResult.php',
    'Support/Pages/PageSiteMoveValidationResult.php',
    'Support/Pages/PageSiteMoveValidator.php',
    'Support/Pages/PageSiteMover.php',
    'Support/Pages/PageWorkflowManager.php',
    'Support/Search/PublicSearchSchema.php',
    'Support/SharedSlots/SharedSlotRevisionManager.php',
    'Support/SharedSlots/SharedSlotSchema.php',
    'Support/SharedSlots/SharedSlotSourcePageManager.php',
    'Support/Sites/SiteCloneOptions.php',
    'Support/Sites/SiteCloneResult.php',
    'Support/Sites/SiteCloneService.php',
    'Support/Sites/SiteAssetStore.php',
    'Support/Sites/SiteAssetWriteException.php',
    'Support/Sites/SiteDeleteResult.php',
    'Support/Sites/SiteDeleteService.php',
    'Support/Sites/SiteDomainManager.php',
    'Support/Sites/SiteDomainNormalizer.php',
    'Support/Sites/SiteHandle.php',
    'Support/Sites/SitePublicDirectoryManager.php',
    'Support/Visitors/VisitorReportsQuery.php',
  ];

  public const ICON_ADMIN_INDEX_ROUTE_NAME = 'admin.system.icons.index';

  public const ICON_ADMIN_UPDATE_ROUTE_NAME = 'admin.system.icons.update';

  public const ICON_SYNC_COMMAND_NAME = 'icons:sync-webblocks-ui';

  public const PACKAGE_CONSOLE_COMMANDS = [
    PackageStatusCommand::class,
    PublishUpdateCommand::class,
    PrunePromotedStagedUpdatesCommand::class,
    GenerateUpdateSigningKeyCommand::class,
    ContactMailDiagnoseCommand::class,
    DoctorNativeLocalCommand::class,
    SmokeNativeLocalCommand::class,
    SearchRebuildCommand::class,
    SyncWebBlocksUiIconsCommand::class,
    AdminTranslationAuditCommand::class,
    BlockTypeContractsAuditCommand::class,
    ImportDemoMedia::class,
    MediaVariantsCommand::class,
    MaintenanceCleanupCommand::class,
    ResetPrimitiveBlocksCommand::class,
    SiteCloneCommand::class,
    SiteDeleteCommand::class,
    SiteExportCommand::class,
    SiteImportCommand::class,
    SitePromotionApplyCommand::class,
    SitePromotionDryRunCommand::class,
    SitePromotionInspectCommand::class,
    SyncCoreBlockTypesCommand::class,
    CatalogRepairCommand::class,
    StarterContentCommand::class,
    SystemBackupRestoreCommand::class,
    SystemBackupCleanupCommand::class,
    SystemUpdateRunsCommand::class,
    SystemUpdatePruneRunsCommand::class,
  ];

  public const CONFIG_PUBLISH_TAG = 'webblocks-cms-config';

  public const ASSETS_PUBLISH_TAG = 'webblocks-cms-assets';

  public const STUBS_PUBLISH_TAG = 'webblocks-cms-stubs';

  public const ASSETS_PUBLISH_TARGET = 'public/cms';

  public const STUBS_PUBLISH_TARGET = 'stubs/vendor/webblocks-cms';

  public const PACKAGE_COMPOSER_NAME = 'fklavyenet/webblocks-cms';

  public const STARTER_PACKAGE_NAME = 'fklavyenet/webblocks-cms-starter';

  public const TARGET_INSTALL_COMMAND = 'composer require fklavyenet/webblocks-cms';

  public const TARGET_UPDATE_COMMAND = 'composer update fklavyenet/webblocks-cms';

  public const ROOT_RUNTIME_ASSET_COMPATIBILITY_PATH = 'public/cms';

  public const PACKAGE_SOURCE_AUTHORITY_DOMAINS = [
    'CMS admin and public route trees',
    'package-owned admin controllers, requests, support, and views for safely movable CMS runtime slices',
    'package-owned public layout, page/search shells, and shipped public block renderers',
    'package-owned CMS model foundation except the install-owned User model',
    'package-owned shared admin partials and admin layout, with no root admin layout alias preserved',
    'package-owned seeder, stub, and movable public asset source files',
  ];

  public const ROOT_COMPATIBILITY_WRAPPER_DOMAINS = [
    'minimal host-owned app shell files for auth, install, profile, User, providers, and project-layer boundaries',
    'small operational transition app files that still serve source-maintained install or legacy asset workflows',
    'root Blade wrappers for moved admin/public views and shared admin partials, excluding the removed admin layout alias',
    'root seeder wrappers for moved package seeders',
    'root public/cms runtime copies that keep active asset URLs stable',
  ];

  public const STARTER_SPLIT_BLOCKERS = [
    'install, auth, and profile runtime remain host-owned',
    'App\\Models\\User remains install-owned',
    'root public/cms remains the active runtime asset compatibility path',
    'root migration authority and root update/install operational flows remain authoritative',
  ];

  public function register(): void
  {
    require_once __DIR__.'/Support/helpers.php';

    $this->registerClassAliases();
    $this->registerConfig();
    $this->registerNativeLocalDoctor();
    $this->registerPlugins();
  }

  public function boot(): void
  {
    $this->bootMiddlewareAliases();
    $this->bootAuthorization();
    $this->bootRateLimiters();
    $this->bootCommands();
    $this->bootSchedule();
    $this->bootInternalApiCsrfExclusions();
    $this->bootRoutes();
    $this->bootViews();
    $this->bootNotFoundView();
    $this->bootMigrations();
    $this->bootPublishing();

    // Run after the whole application has booted so every route (including the
    // host app's own routes such as login/register) is registered and can be
    // reserved from plugin catch-all routes.
    $this->app->booted(function (): void {
      app(PluginRouteRegistrar::class)->protectCorePublicRoutesFromPluginCatchAlls();
    });
  }

  protected function bootRateLimiters(): void
  {
    RateLimiter::for('webblocks-auth', function (Request $request) {
      // Per-IP backstop across all admin auth endpoints. The precise
      // per-email+IP lockout lives in LoginController; this caps floods and
      // email-rotation attempts from a single source.
      return Limit::perMinute(30)->by($request->ip());
    });

    RateLimiter::for('contact-form-submissions', function (Request $request) {
      return Limit::perMinute((int) config('contact.rate_limit_per_minute', 5))
        ->by($request->ip().'|'.((string) $request->input('block_id')));
    });

    RateLimiter::for('engagement-ratings', function (Request $request) {
      return Limit::perMinute(20)->by($request->ip().'|'.((string) $request->input('block_id')));
    });

    RateLimiter::for('engagement-comments', function (Request $request) {
      return Limit::perMinute(3)->by($request->ip().'|'.((string) $request->input('block_id')));
    });

    RateLimiter::for('internal-content-api', function (Request $request) {
      return Limit::perMinute(InternalApiRateLimit::perMinute())->by($request->ip().'|'.((string) $request->bearerToken()));
    });

    // Group backstop for every plugin-owned public route. It is deliberately
    // loose enough not to break a read endpoint a block polls, and a plugin
    // that writes is expected to add its own stricter per-route throttle. The
    // key includes the plugin prefix so one noisy plugin cannot exhaust the
    // budget of another.
    RateLimiter::for('plugin-public-routes', function (Request $request) {
      $segments = $request->segments();

      return Limit::perMinute((int) config('webblocks-plugins.public_routes.rate_limit_per_minute', 60))
        ->by($request->ip().'|'.((string) ($segments[1] ?? '')));
    });
  }

  protected function bootCommands(): void
  {
    if (! $this->app->runningInConsole()) {
      return;
    }

    $this->commands(self::PACKAGE_CONSOLE_COMMANDS);
    $this->commands(app(PluginCommandRegistrar::class)->enabledCommands());

    if ($this->installCommandShouldLoad()) {
      $this->commands([InstallWebBlocksCmsCommand::class]);
    }
  }

  protected function bootSchedule(): void
  {
    $this->app->booted(function (): void {
      if (! $this->app->bound(Schedule::class)) {
        return;
      }

      $this->app->make(Schedule::class)
        ->command('system:backups:cleanup')
        ->dailyAt('03:30')
        ->withoutOverlapping()
        ->onOneServer();
    });
  }

  protected function bootInternalApiCsrfExclusions(): void
  {
    /*
     * The internal API only. Plugin webhook endpoints used to be listed here by
     * path, one hardcoded pair per plugin core happened to know about; they now
     * declare `routes.webhooks` and the public route registrar drops the check
     * from that group alone, so the exemption belongs to the routes it covers
     * instead of to a list nothing keeps in step with them.
     */
    $paths = [
      'webadmin/api',
      'webadmin/api/*',
    ];

    foreach ($this->internalApiCsrfMiddlewareClasses() as $middleware) {
      if (class_exists($middleware) && method_exists($middleware, 'except')) {
        $middleware::except($paths);
      }
    }
  }

  /**
   * @return array<int, class-string|string>
   */
  protected function internalApiCsrfMiddlewareClasses(): array
  {
    return [
      'App\\Http\\Middleware\\VerifyCsrfToken',
      'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
      'Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken',
      'Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken',
    ];
  }

  protected function registerConfig(): void
  {
    foreach ($this->configFiles() as $file) {
      $this->mergeConfigFrom($file, pathinfo($file, PATHINFO_FILENAME));
    }

    SiteTransferDisk::registerDefaultConfigIfMissing();
  }

  protected function registerPlugins(): void
  {
    $this->app->singleton(InstalledPluginRepository::class, fn (): InstalledPluginRepository => new InstalledPluginRepository);
    $this->app->singleton(InstalledPluginDefinitionFactory::class, fn (): InstalledPluginDefinitionFactory => new InstalledPluginDefinitionFactory);
    $this->app->singleton(PluginMigrationRunner::class, fn (): PluginMigrationRunner => new PluginMigrationRunner);
    $this->app->singleton(PluginAccessResolver::class, fn (): PluginAccessResolver => new PluginAccessResolver);
    $this->app->singleton(PluginRuntimeRefresher::class, fn (): PluginRuntimeRefresher => new PluginRuntimeRefresher);

    $this->app->singleton(PluginRegistry::class, function (): PluginRegistry {
      $enabled = config('webblocks-plugins.enabled', []);
      $registry = new PluginRegistry(is_array($enabled) ? $enabled : [], useLiveConfig: true);
      $repository = app(InstalledPluginRepository::class);
      $factory = app(InstalledPluginDefinitionFactory::class);

      foreach ($repository->installed() as $installed) {
        $handle = (string) ($installed['manifest']['handle'] ?? '');
        $enabledByConfig = $handle !== '' && (bool) config("webblocks-plugins.enabled.{$handle}", false);

        $registry->register($factory->make($installed['manifest'], $installed['path'], $installed['enabled'] || $enabledByConfig));
      }

      return $registry;
    });

    $this->app->singleton(PluginPermissionRegistry::class, fn ($app): PluginPermissionRegistry => new PluginPermissionRegistry(
      $app->make(PluginRegistry::class)
    ));

    $this->app->singleton(PluginAuthorizationRegistrar::class, fn ($app): PluginAuthorizationRegistrar => new PluginAuthorizationRegistrar(
      $app->make(PluginPermissionRegistry::class),
      $app->make(PluginAccessResolver::class)
    ));

    $this->app->singleton(PluginAdminExtensionRegistry::class, fn ($app): PluginAdminExtensionRegistry => new PluginAdminExtensionRegistry(
      $app->make(PluginRegistry::class)
    ));

    $this->app->singleton(PluginBlockRegistry::class, fn ($app): PluginBlockRegistry => new PluginBlockRegistry(
      $app->make(PluginRegistry::class)
    ));

    $this->app->singleton(PluginBlockCatalog::class, fn ($app): PluginBlockCatalog => new PluginBlockCatalog(
      $app->make(PluginRegistry::class)
    ));

    /*
     * Deliberately not a singleton: it is resolved right after a plugin
     * lifecycle change forgets the registry, and a memoized instance would
     * sync the plugin set that the change replaced.
     */
    $this->app->bind(PluginBlockTypeCatalogSyncer::class, fn ($app): PluginBlockTypeCatalogSyncer => new PluginBlockTypeCatalogSyncer(
      $app->make(PluginRegistry::class),
      $app->make(PluginBlockCatalog::class),
      $app->make(CoreBlockTypeCatalogSyncer::class)
    ));

    $this->app->singleton(PluginPublicAssetRegistry::class, fn ($app): PluginPublicAssetRegistry => new PluginPublicAssetRegistry(
      $app->make(PluginRegistry::class)
    ));
  }

  protected function registerNativeLocalDoctor(): void
  {
    $this->app->singleton(NativeLocalProbe::class, fn (): NativeLocalProbe => new SystemNativeLocalProbe);
  }

  protected function bootRoutes(): void
  {
    $this->loadGuardedRouteFiles(
      $this->packageInstallRoutesShouldLoad(),
      $this->installRouteFiles()
    );

    $this->loadGuardedRouteFiles(
      $this->packageAuthRoutesShouldLoad(),
      $this->authRouteFiles()
    );

    $this->loadGuardedRouteFiles(
      $this->diagnosticRoutesShouldLoad(),
      $this->diagnosticRouteFiles()
    );

    $this->loadGuardedRouteFiles(
      $this->packageAdminRoutesShouldLoad(),
      $this->adminRouteFiles()
    );

    $this->loadGuardedRouteFiles(
      $this->packagePublicRoutesShouldLoad(),
      $this->publicRouteFiles()
    );

    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    app(PluginApiRouteRegistrar::class)->registerEnabledApiRoutes();
    app(PluginPublicRouteRegistrar::class)->registerEnabledPublicRoutes();
  }

  protected function diagnosticRoutesShouldLoad(): bool
  {
    return (bool) config(self::DIAGNOSTIC_ROUTE_LOADING_CONFIG, false);
  }

  protected function packageAdminRoutesShouldLoad(): bool
  {
    if (! (bool) config(self::PACKAGE_ADMIN_ROUTE_LOADING_CONFIG, false)) {
      return false;
    }

    if ((bool) config(self::PACKAGE_ADMIN_STATUS_ROUTE_LOADING_CONFIG, false)
      && app('router')->getRoutes()->getByName(self::PACKAGE_ADMIN_ROUTE_NAME) === null) {
      return true;
    }

    return app('router')->getRoutes()->getByName(self::ICON_ADMIN_INDEX_ROUTE_NAME) === null;
  }

  protected function packageAuthRoutesShouldLoad(): bool
  {
    return app('router')->getRoutes()->getByName('webblocks.auth.login') === null;
  }

  protected function packageInstallRoutesShouldLoad(): bool
  {
    $configured = config('webblocks-cms.install.load_routes');

    if ($configured !== null) {
      return (bool) $configured
        && app('router')->getRoutes()->getByName('webblocks-cms.install.notice') === null;
    }

    return ! $this->runningInsideMaintenanceRepository()
      && app('router')->getRoutes()->getByName('webblocks-cms.install.notice') === null;
  }

  protected function installCommandShouldLoad(): bool
  {
    return $this->app->runningUnitTests() || ! $this->runningInsideMaintenanceRepository();
  }

  protected function packagePublicRoutesShouldLoad(): bool
  {
    if (! (bool) config(self::PACKAGE_PUBLIC_ROUTE_LOADING_CONFIG, false)) {
      return false;
    }

    if ((bool) config(self::PACKAGE_PUBLIC_STATUS_ROUTE_LOADING_CONFIG, false)
      && app('router')->getRoutes()->getByName(self::PACKAGE_PUBLIC_ROUTE_NAME) === null) {
      return true;
    }

    return app('router')->getRoutes()->getByName('home') === null;
  }

  /**
     * @return array<int, string>
     */
  protected function diagnosticRouteFiles(): array
  {
    return $this->namedRouteFiles(self::DIAGNOSTIC_ROUTE_FILE);
  }

  /**
     * @return array<int, string>
     */
  protected function adminRouteFiles(): array
  {
    return $this->namedRouteFiles(self::PACKAGE_ADMIN_ROUTE_FILE);
  }

  protected function authRouteFiles(): array
  {
    return $this->namedRouteFiles(self::PACKAGE_AUTH_ROUTE_FILE);
  }

  protected function installRouteFiles(): array
  {
    return $this->namedRouteFiles(self::PACKAGE_INSTALL_ROUTE_FILE);
  }

  /**
     * @return array<int, string>
     */
  protected function publicRouteFiles(): array
  {
    return $this->namedRouteFiles(self::PACKAGE_PUBLIC_ROUTE_FILE);
  }

  protected function bootViews(): void
  {
    $this->loadTranslationsFrom($this->langPath(), self::VIEW_NAMESPACE);
    $this->bootPluginTranslations();

    if (! is_dir($this->viewsPath())) {
      return;
    }

    $this->loadViewsFrom($this->viewsPath(), self::VIEW_NAMESPACE);
  }

  /**
   * Renders the package's branded 404 for public HTML requests. The host
   * app's own resources/views/errors/404.blade.php keeps winning — Laravel's
   * error-view flow resolves it before this renderable's fallback matters,
   * and the file check below keeps this renderable from shadowing it. JSON
   * requests keep their JSON 404.
   */
  protected function bootNotFoundView(): void
  {
    $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
      if (! $handler instanceof ExceptionsHandler) {
        return;
      }

      $handler->renderable(function (NotFoundHttpException $exception, Request $request) {
        if ($request->expectsJson() || is_file(resource_path('views/errors/404.blade.php'))) {
          return null;
        }

        return response()->view('webblocks-cms::errors.404', ['request' => $request], 404);
      });
    });
  }

  protected function bootPluginTranslations(): void
  {
    foreach (app(PluginRegistry::class)->all() as $plugin) {
      $installPath = $plugin->installPathValue();

      if ($installPath === null) {
        continue;
      }

      $langPath = $installPath.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'lang';

      if (is_dir($langPath)) {
        $this->loadTranslationsFrom($langPath, $plugin->handle());
      }
    }
  }

  protected function bootMigrations(): void
  {
    if (! $this->packageMigrationsShouldLoad()) {
      return;
    }

    if ($this->migrationFiles() === []) {
      return;
    }

    $this->loadMigrationsFrom($this->migrationsPath());
  }

  protected function packageMigrationsShouldLoad(): bool
  {
    return (bool) config(self::PACKAGE_MIGRATION_LOADING_CONFIG, false);
  }

  protected function runningInsideMaintenanceRepository(): bool
  {
    return $this->packagePath() === base_path('packages/webblocks-cms');
  }

  protected function bootMiddlewareAliases(): void
  {
    if (! (bool) config('webblocks-cms.middleware.register_aliases', true)) {
      return;
    }

    Route::aliasMiddleware('admin.access', RequireAdminAccess::class);
    Route::aliasMiddleware('internal-api.capability', RequireInternalApiCapability::class);
    Route::aliasMiddleware('internal-api.token', RequireInternalApiToken::class);
    Route::aliasMiddleware('install.complete', RedirectIfInstalled::class);
    Route::aliasMiddleware('install.required', RedirectIfNotInstalled::class);
    Route::aliasMiddleware('webblocks.auth.redirect', UseCmsAuthenticationRedirect::class);
    Route::aliasMiddleware('plugin.permission', AuthorizePluginPermission::class);
  }

  protected function bootAuthorization(): void
  {
    Gate::define('access-admin', fn ($user) => is_object($user) && method_exists($user, 'canAccessAdmin') && $user->canAccessAdmin());
    Gate::define('manage-users', fn ($user) => app(PluginAccessResolver::class)->isSuperAdmin($user));
    Gate::define('access-system', fn ($user) => app(PluginAccessResolver::class)->canAccessSystem($user));

    app(PluginAuthorizationRegistrar::class)->register();
  }

  protected function registerClassAliases(): void
  {
    $aliases = [
      'App\\Models\\BlockAsset' => BlockMedia::class,
      'App\\Support\\WebBlocks' => WebBlocks::class,
    ];

    foreach ($aliases as $alias => $target) {
      if (! class_exists($alias) && class_exists($target)) {
        class_alias($target, $alias);
      }
    }
  }

  protected function bootPublishing(): void
  {
    if (! $this->app->runningInConsole()) {
      return;
    }

    $this->bootConfigPublishing();
    $this->bootAssetPublishing();
    $this->bootStubPublishing();
  }

  protected function bootConfigPublishing(): void
  {
    foreach ($this->configFiles() as $file) {
      $this->publishes([
        $file => config_path(basename($file)),
      ], self::CONFIG_PUBLISH_TAG);
    }
  }

  protected function bootAssetPublishing(): void
  {
    if (! $this->packageAssetsArePublishable()) {
      return;
    }

    $this->publishes([
      $this->publicPath('cms') => public_path(str_replace('public/', '', self::ASSETS_PUBLISH_TARGET)),
    ], self::ASSETS_PUBLISH_TAG);
  }

  protected function bootStubPublishing(): void
  {
    if (! $this->packageStubsArePublishable()) {
      return;
    }

    $this->publishes([
      $this->stubsPath() => base_path(self::STUBS_PUBLISH_TARGET),
    ], self::STUBS_PUBLISH_TAG);
  }

  protected function packageAssetsArePublishable(): bool
  {
    return $this->directoryHasRealFiles($this->publicPath());
  }

  protected function packageStubsArePublishable(): bool
  {
    return $this->directoryHasRealFiles($this->stubsPath());
  }

  protected function packagePath(string $path = ''): string
  {
    $packagePath = dirname(__DIR__);

    if ($path === '') {
      return $packagePath;
    }

    return $packagePath.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);
  }

  protected function configPath(): string
  {
    return $this->packagePath('config');
  }

  protected function routesPath(): string
  {
    return $this->packagePath('routes');
  }

  protected function viewsPath(): string
  {
    return $this->packagePath('resources/views');
  }

  protected function langPath(): string
  {
    return $this->packagePath('resources/lang');
  }

  protected function migrationsPath(): string
  {
    return $this->packagePath((string) config('webblocks-cms.migrations.fresh_path', 'database/migrations/fresh'));
  }

  protected function publicPath(string $path = ''): string
  {
    return $this->packagePath('public'.($path !== '' ? DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR) : ''));
  }

  protected function stubsPath(): string
  {
    return $this->packagePath('stubs');
  }

  /**
     * @return array<int, string>
     */
  protected function configFiles(): array
  {
    $files = [];

    foreach (self::PACKAGE_CONFIG_DEFAULTS as $file) {
      $path = $this->configPath().DIRECTORY_SEPARATOR.$file;

      if (is_file($path)) {
        $files[] = $path;
      }
    }

    return $files;
  }

  /**
     * @return array<int, string>
     */
  protected function routeFiles(): array
  {
    return $this->phpFiles($this->routesPath());
  }

  /**
     * @return array<int, string>
     */
  protected function migrationFiles(): array
  {
    return $this->phpFiles($this->migrationsPath());
  }

  /**
     * @return array<int, string>
     */
  protected function phpFiles(string $path): array
  {
    if (! is_dir($path)) {
      return [];
    }

    $files = [];

    foreach ($this->packageFiles($path) as $file) {
      if ($file->getExtension() !== 'php') {
        continue;
      }

      $files[] = $file->getPathname();
    }

    sort($files);

    return $files;
  }

  /**
     * @param  array<int, string>  $files
     */
  protected function loadGuardedRouteFiles(bool $shouldLoad, array $files): void
  {
    if (! $shouldLoad) {
      return;
    }

    foreach ($files as $file) {
      $this->loadRoutesFrom($file);
    }
  }

  /**
     * @return array<int, string>
     */
  protected function namedRouteFiles(string $fileName): array
  {
    return array_values(array_filter(
      $this->routeFiles(),
      static fn (string $file): bool => basename($file) === $fileName
    ));
  }

  protected function directoryHasRealFiles(string $path): bool
  {
    if (! is_dir($path)) {
      return false;
    }

    foreach ($this->packageFiles($path) as $file) {
      if ($file->isFile()) {
        return true;
      }
    }

    return false;
  }

  /**
     * @return \Generator<int, SplFileInfo>
     */
  protected function packageFiles(string $path): \Generator
  {
    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
      if (! $file instanceof SplFileInfo || ! $file->isFile()) {
        continue;
      }

      if ($this->isPlaceholderFile($file)) {
        continue;
      }

      yield $file;
    }
  }

  protected function isPlaceholderFile(SplFileInfo $file): bool
  {
    return in_array($file->getFilename(), ['.gitkeep', '.DS_Store', 'README.md'], true)
      || str_starts_with($file->getFilename(), '.');
  }
}
