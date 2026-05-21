<?php

namespace Tests\Feature;

use App\Console\Commands\SyncWebBlocksUiIconsCommand;
use App\Http\Controllers\Admin\IconCatalogController;
use App\Http\Controllers\Admin\SlotTypeController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Requests\Admin\IconCatalogItemUpdateRequest;
use App\Http\Requests\Admin\SystemSettingsRequest;
use App\Models\Block;
use App\Models\ContactMessage;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\PublicSearchIndex;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\SystemSetting;
use App\Models\VisitorEvent;
use App\Support\Admin\AdminPagination;
use App\Support\Blocks\BlockDeletionManager;
use App\Support\Blocks\BlockPayloadWriter;
use App\Support\Blocks\BlockTranslationResolver;
use App\Support\BlockTypes\BlockTypeContractRegistry;
use App\Support\BlockTypes\BlockTypeIndexState;
use App\Support\Icons\IconCatalog;
use App\Support\Icons\WebBlocksIconManifestSyncer;
use App\Support\Media\MediaIndexState;
use App\Support\Media\MediaKindResolver;
use App\Support\Media\MediaUsageFilter;
use App\Support\Media\MediaUsageResolver;
use App\Support\Navigation\NavigationTree;
use App\Support\Pages\PageDuplicateValidator;
use App\Support\Pages\PageDuplicator;
use App\Support\Pages\PageIndexState;
use App\Support\Pages\PageJsonImporter;
use App\Support\Pages\PageLayoutManager;
use App\Support\Pages\PageLayoutSlotComparison;
use App\Support\Pages\PageLayoutSlotSyncer;
use App\Support\Pages\PageRevisionManager;
use App\Support\Pages\PageSiteMover;
use App\Support\Pages\PageSiteMoveValidator;
use App\Support\Pages\PageWorkflowManager;
use App\Support\SharedSlots\SharedSlotRevisionManager;
use App\Support\SharedSlots\SharedSlotSchema;
use App\Support\SharedSlots\SharedSlotSourcePageManager;
use App\Support\WebBlocks;
use Database\Seeders\CoreCatalogSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Database\Seeders\IconCatalogSeeder;
use Database\Seeders\LayoutTypeSeeder;
use Database\Seeders\PageTypeSeeder;
use Database\Seeders\SlotTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Console\BlockTypeContractsAuditCommand;
use WebBlocks\Cms\Console\ImportDemoMedia;
use WebBlocks\Cms\Console\PackageStatusCommand;
use WebBlocks\Cms\Console\ResetPrimitiveBlocksCommand;
use WebBlocks\Cms\Console\SearchRebuildCommand;
use WebBlocks\Cms\Console\SiteCloneCommand;
use WebBlocks\Cms\Console\SiteDeleteCommand;
use WebBlocks\Cms\Console\SiteExportCommand;
use WebBlocks\Cms\Console\SiteImportCommand;
use WebBlocks\Cms\Console\SitePromotionApplyCommand;
use WebBlocks\Cms\Console\SitePromotionDryRunCommand;
use WebBlocks\Cms\Console\SitePromotionInspectCommand;
use WebBlocks\Cms\Console\SyncCoreBlockTypesCommand;
use WebBlocks\Cms\Console\SyncWebBlocksUiIconsCommand as PackageSyncWebBlocksUiIconsCommand;
use WebBlocks\Cms\Console\SystemBackupRestoreCommand;
use WebBlocks\Cms\Database\Seeders\CoreCatalogSeeder as PackageCoreCatalogSeeder;
use WebBlocks\Cms\Database\Seeders\IconCatalogSeeder as PackageIconCatalogSeeder;
use WebBlocks\Cms\Database\Seeders\LayoutTypeSeeder as PackageLayoutTypeSeeder;
use WebBlocks\Cms\Database\Seeders\PageTypeSeeder as PackagePageTypeSeeder;
use WebBlocks\Cms\Database\Seeders\SlotTypeSeeder as PackageSlotTypeSeeder;
use WebBlocks\Cms\Http\Controllers\Admin\IconCatalogController as PackageIconCatalogController;
use WebBlocks\Cms\Http\Controllers\Admin\SlotTypeController as PackageSlotTypeController;
use WebBlocks\Cms\Http\Controllers\Admin\SystemSettingsController as PackageSystemSettingsController;
use WebBlocks\Cms\Http\Requests\Admin\IconCatalogItemUpdateRequest as PackageIconCatalogItemUpdateRequest;
use WebBlocks\Cms\Http\Requests\Admin\SystemSettingsRequest as PackageSystemSettingsRequest;
use WebBlocks\Cms\Models\Block as PackageBlock;
use WebBlocks\Cms\Models\ContactMessage as PackageContactMessage;
use WebBlocks\Cms\Models\Locale as PackageLocale;
use WebBlocks\Cms\Models\Page as PackagePage;
use WebBlocks\Cms\Models\PageSlot as PackagePageSlot;
use WebBlocks\Cms\Models\PageTranslation as PackagePageTranslation;
use WebBlocks\Cms\Models\PublicSearchIndex as PackagePublicSearchIndex;
use WebBlocks\Cms\Models\Site as PackageSite;
use WebBlocks\Cms\Models\SiteDomain as PackageSiteDomain;
use WebBlocks\Cms\Models\SystemSetting as PackageSystemSetting;
use WebBlocks\Cms\Models\VisitorEvent as PackageVisitorEvent;
use WebBlocks\Cms\Support\Admin\AdminPagination as PackageAdminPagination;
use WebBlocks\Cms\Support\Blocks\BlockDeletionManager as PackageBlockDeletionManager;
use WebBlocks\Cms\Support\Blocks\BlockPayloadWriter as PackageBlockPayloadWriter;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver as PackageBlockTranslationResolver;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeContractRegistry as PackageBlockTypeContractRegistry;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeIndexState as PackageBlockTypeIndexState;
use WebBlocks\Cms\Support\Icons\IconCatalog as PackageIconCatalog;
use WebBlocks\Cms\Support\Icons\WebBlocksIconManifestSyncer as PackageWebBlocksIconManifestSyncer;
use WebBlocks\Cms\Support\Media\MediaIndexState as PackageMediaIndexState;
use WebBlocks\Cms\Support\Media\MediaKindResolver as PackageMediaKindResolver;
use WebBlocks\Cms\Support\Media\MediaUsageFilter as PackageMediaUsageFilter;
use WebBlocks\Cms\Support\Media\MediaUsageResolver as PackageMediaUsageResolver;
use WebBlocks\Cms\Support\Navigation\NavigationTree as PackageNavigationTree;
use WebBlocks\Cms\Support\Pages\PageDuplicateValidator as PackagePageDuplicateValidator;
use WebBlocks\Cms\Support\Pages\PageDuplicator as PackagePageDuplicator;
use WebBlocks\Cms\Support\Pages\PageIndexState as PackagePageIndexState;
use WebBlocks\Cms\Support\Pages\PageJsonImporter as PackagePageJsonImporter;
use WebBlocks\Cms\Support\Pages\PageLayoutManager as PackagePageLayoutManager;
use WebBlocks\Cms\Support\Pages\PageLayoutSlotComparison as PackagePageLayoutSlotComparison;
use WebBlocks\Cms\Support\Pages\PageLayoutSlotSyncer as PackagePageLayoutSlotSyncer;
use WebBlocks\Cms\Support\Pages\PageRevisionManager as PackagePageRevisionManager;
use WebBlocks\Cms\Support\Pages\PageSiteMover as PackagePageSiteMover;
use WebBlocks\Cms\Support\Pages\PageSiteMoveValidator as PackagePageSiteMoveValidator;
use WebBlocks\Cms\Support\Pages\PageWorkflowManager as PackagePageWorkflowManager;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotRevisionManager as PackageSharedSlotRevisionManager;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSchema as PackageSharedSlotSchema;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager as PackageSharedSlotSourcePageManager;
use WebBlocks\Cms\Support\System\Updates\SystemUpdater as PackageSystemUpdater;
use WebBlocks\Cms\Support\System\Updates\UpdateCheckResult as PackageUpdateCheckResult;
use WebBlocks\Cms\Support\System\Updates\UpdateCommandRunner as PackageUpdateCommandRunner;
use WebBlocks\Cms\Support\System\Updates\UpdateException as PackageUpdateException;
use WebBlocks\Cms\Support\System\Updates\UpdateInstaller as PackageUpdateInstaller;
use WebBlocks\Cms\Support\System\Updates\UpdatePackageDownloader as PackageUpdatePackageDownloader;
use WebBlocks\Cms\Support\System\Updates\UpdatePackageExtractor as PackageUpdatePackageExtractor;
use WebBlocks\Cms\Support\System\Updates\UpdateResult as PackageUpdateResult;
use WebBlocks\Cms\Support\System\Updates\UpdateServerClient as PackageUpdateServerClient;
use WebBlocks\Cms\Support\System\Updates\UpdateWorkspaceManager as PackageUpdateWorkspaceManager;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PackageServiceProviderBootstrapTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function discovered_package_provider_loads_without_changing_current_root_runtime_behavior(): void
    {
        $this->assertTrue(class_exists(WebBlocksCmsServiceProvider::class));
        $this->assertTrue($this->app->providerIsLoaded(WebBlocksCmsServiceProvider::class));

        $router = $this->app['router'];
        $viewHints = view()->getFinder()->getHints();

        $this->assertFileExists(base_path('packages/webblocks-cms/config/webblocks-cms.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/routes/'.WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_FILE));
        $this->assertFileExists(base_path('packages/webblocks-cms/routes/'.WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_FILE));
        $this->assertFileExists(base_path('packages/webblocks-cms/routes/'.WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_FILE));
        $this->assertFileExists(base_path('packages/webblocks-cms/database/migrations/README.md'));
        $this->assertFileExists(base_path('packages/webblocks-cms/database/seeders/README.md'));
        $this->assertFileExists(base_path('packages/webblocks-cms/database/seeders/CoreCatalogSeeder.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/database/seeders/IconCatalogSeeder.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/database/seeders/PageTypeSeeder.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/database/seeders/LayoutTypeSeeder.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/database/seeders/SlotTypeSeeder.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/public/README.md'));
        $this->assertFileExists(base_path('packages/webblocks-cms/public/cms/css/public.css'));
        $this->assertFileExists(base_path('packages/webblocks-cms/public/cms/js/public/public-search-modal.js'));
        $this->assertFileExists(base_path('packages/webblocks-cms/public/cms/js/public/sidebar-navigation.js'));
        $this->assertFileExists(base_path('packages/webblocks-cms/public/cms/package-boundary.json'));
        $this->assertFileExists(base_path('packages/webblocks-cms/stubs/README.md'));
        $this->assertFileExists(base_path('packages/webblocks-cms/stubs/starter/README.md'));
        $this->assertFileExists(base_path('packages/webblocks-cms/stubs/starter/composer.json.stub'));
        $this->assertFileExists(base_path('packages/webblocks-cms/stubs/starter/env.example.stub'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Admin/AdminPagination.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/BlockTypes/BlockTypeIndexState.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Console/SyncWebBlocksUiIconsCommand.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Http/Controllers/Admin/IconCatalogController.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Http/Controllers/Admin/SlotTypeController.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Http/Controllers/Admin/SystemSettingsController.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Http/Requests/Admin/IconCatalogItemUpdateRequest.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Http/Requests/Admin/SystemSettingsRequest.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Icons/IconCatalog.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Icons/WebBlocksIconManifestSyncer.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Blocks/BlockDeletionManager.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Blocks/BlockPayloadWriter.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Blocks/BlockTranslationResolver.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Media/MediaKindResolver.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Media/MediaUsageFilter.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Media/MediaUsageResolver.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Navigation/NavigationTree.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/BlockTypes/BlockTypeContractRegistry.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Pages/PageWorkflowManager.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Pages/PageRevisionManager.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Pages/PageLayoutManager.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Pages/PageLayoutSlotComparison.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Pages/PageLayoutSlotSyncer.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Pages/PageDuplicator.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/System/Updates/SystemUpdater.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/System/Updates/UpdateCheckResult.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/System/Updates/UpdateCommandRunner.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/System/Updates/UpdateException.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/System/Updates/UpdateInstaller.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/System/Updates/UpdatePackageDownloader.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/System/Updates/UpdatePackageExtractor.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/System/Updates/UpdateResult.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/System/Updates/UpdateServerClient.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/System/Updates/UpdateWorkspaceManager.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Pages/PageDuplicateValidator.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Pages/PageJsonImporter.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Pages/PageSiteMover.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Pages/PageSiteMoveValidator.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/SharedSlots/SharedSlotSchema.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/SharedSlots/SharedSlotRevisionManager.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/SharedSlots/SharedSlotSourcePageManager.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/admin/system/icons/index.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/admin/system/icons/partials/edit-modal.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/admin/pages/index.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/admin/shared-slots/index.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/admin/page-layouts/index.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/admin/page-layout-slots/create.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/admin/blocks/index.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/admin/media/index.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/admin/navigation/index.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/admin/block-types/index.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/admin/slot-types/index.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/admin/system/settings.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/admin/system/updates.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/admin/system/backups/index.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/admin/system/search.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/admin/blocks/types/fallback.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/admin/blocks/types/fallback-inline.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/pages/partials/blocks/hero.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/pages/partials/blocks/columns.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/resources/views/pages/partials/blocks/gallery.blade.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Media/MediaIndexState.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Pages/PageIndexState.php'));
        $this->assertFileExists(base_path('database/seeders/CoreCatalogSeeder.php'));
        $this->assertFileExists(base_path('database/seeders/IconCatalogSeeder.php'));
        $this->assertFileExists(base_path('database/seeders/PageTypeSeeder.php'));
        $this->assertFileExists(base_path('database/seeders/LayoutTypeSeeder.php'));
        $this->assertFileExists(base_path('database/seeders/SlotTypeSeeder.php'));
        $this->assertFileExists(base_path('app/Console/Commands/SyncWebBlocksUiIconsCommand.php'));
        $this->assertFileExists(base_path('app/Support/Admin/AdminPagination.php'));
        $this->assertFileExists(base_path('app/Support/BlockTypes/BlockTypeIndexState.php'));
        $this->assertFileExists(base_path('app/Http/Controllers/Admin/IconCatalogController.php'));
        $this->assertFileExists(base_path('app/Http/Controllers/Admin/SlotTypeController.php'));
        $this->assertFileExists(base_path('app/Http/Controllers/Admin/SystemSettingsController.php'));
        $this->assertFileExists(base_path('app/Http/Requests/Admin/IconCatalogItemUpdateRequest.php'));
        $this->assertFileExists(base_path('app/Http/Requests/Admin/SystemSettingsRequest.php'));
        $this->assertFileExists(base_path('app/Support/Icons/IconCatalog.php'));
        $this->assertFileExists(base_path('app/Support/Icons/WebBlocksIconManifestSyncer.php'));
        $this->assertFileExists(base_path('app/Support/Blocks/BlockDeletionManager.php'));
        $this->assertFileExists(base_path('app/Support/Blocks/BlockPayloadWriter.php'));
        $this->assertFileExists(base_path('app/Support/Blocks/BlockTranslationResolver.php'));
        $this->assertFileExists(base_path('app/Support/Media/MediaKindResolver.php'));
        $this->assertFileExists(base_path('app/Support/Media/MediaUsageFilter.php'));
        $this->assertFileExists(base_path('app/Support/Media/MediaUsageResolver.php'));
        $this->assertFileExists(base_path('app/Support/Navigation/NavigationTree.php'));
        $this->assertFileExists(base_path('app/Support/BlockTypes/BlockTypeContractRegistry.php'));
        $this->assertFileExists(base_path('app/Support/Pages/PageWorkflowManager.php'));
        $this->assertFileExists(base_path('app/Support/Pages/PageRevisionManager.php'));
        $this->assertFileExists(base_path('app/Support/Pages/PageLayoutManager.php'));
        $this->assertFileExists(base_path('app/Support/Pages/PageLayoutSlotComparison.php'));
        $this->assertFileExists(base_path('app/Support/Pages/PageLayoutSlotSyncer.php'));
        $this->assertFileExists(base_path('app/Support/Pages/PageDuplicator.php'));
        $this->assertFileExists(base_path('app/Support/Pages/PageDuplicateValidator.php'));
        $this->assertFileExists(base_path('app/Support/Pages/PageJsonImporter.php'));
        $this->assertFileExists(base_path('app/Support/Pages/PageSiteMover.php'));
        $this->assertFileExists(base_path('app/Support/Pages/PageSiteMoveValidator.php'));
        $this->assertFileExists(base_path('app/Support/SharedSlots/SharedSlotSchema.php'));
        $this->assertFileExists(base_path('app/Support/SharedSlots/SharedSlotRevisionManager.php'));
        $this->assertFileExists(base_path('app/Support/SharedSlots/SharedSlotSourcePageManager.php'));
        $this->assertFileExists(resource_path('views/admin/system/icons/index.blade.php'));
        $this->assertFileExists(resource_path('views/admin/system/icons/partials/edit-modal.blade.php'));
        $this->assertFileExists(resource_path('views/admin/pages/index.blade.php'));
        $this->assertFileExists(resource_path('views/admin/shared-slots/index.blade.php'));
        $this->assertFileExists(resource_path('views/admin/page-layouts/index.blade.php'));
        $this->assertFileExists(resource_path('views/admin/page-layout-slots/create.blade.php'));
        $this->assertFileExists(resource_path('views/admin/blocks/index.blade.php'));
        $this->assertFileExists(resource_path('views/admin/media/index.blade.php'));
        $this->assertFileExists(resource_path('views/admin/navigation/index.blade.php'));
        $this->assertFileExists(resource_path('views/admin/block-types/index.blade.php'));
        $this->assertFileExists(resource_path('views/admin/slot-types/index.blade.php'));
        $this->assertFileExists(resource_path('views/admin/system/settings.blade.php'));
        $this->assertFileExists(resource_path('views/pages/partials/blocks/hero.blade.php'));
        $this->assertFileExists(resource_path('views/pages/partials/blocks/columns.blade.php'));
        $this->assertFileExists(resource_path('views/pages/partials/blocks/gallery.blade.php'));
        $this->assertFileExists(base_path('app/Support/Media/MediaIndexState.php'));
        $this->assertFileExists(base_path('app/Support/Pages/PageIndexState.php'));
        $this->assertNull($router->getRoutes()->getByName(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_NAME));
        $this->assertNull($router->getRoutes()->getByName(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_NAME));
        $this->assertNull($router->getRoutes()->getByName(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_NAME));
        $this->assertNotNull($router->getRoutes()->getByName(WebBlocksCmsServiceProvider::ICON_ADMIN_INDEX_ROUTE_NAME));
        $this->assertNotNull($router->getRoutes()->getByName(WebBlocksCmsServiceProvider::ICON_ADMIN_UPDATE_ROUTE_NAME));
        $this->assertArrayHasKey(WebBlocksCmsServiceProvider::VIEW_NAMESPACE, $viewHints);
        $this->assertContains(
            base_path('packages/webblocks-cms/resources/views'),
            $viewHints[WebBlocksCmsServiceProvider::VIEW_NAMESPACE]
        );
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::diagnostics.package-status'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.runtime-status'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::layouts.admin'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::public.runtime-status'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.icons.index'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.icons.partials.edit-modal'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.partials.page-header'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.partials.flash'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.partials.listing-filters'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.partials.page-actions'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.partials.pagination'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.partials.audit-actor'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::components.admin.form-actions'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.slot-types.index'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.settings'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.updates'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.backups.index'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.search'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.blocks.types.fallback'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.blocks.types.fallback-inline'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.hero'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.columns'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.gallery'));
        $this->assertTrue(view()->exists('admin.partials.page-header'));
        $this->assertTrue(view()->exists('layouts.admin'));
        $this->assertTrue(view()->exists('admin.partials.flash'));
        $this->assertTrue(view()->exists('admin.partials.listing-filters'));
        $this->assertTrue(view()->exists('admin.partials.page-actions'));
        $this->assertTrue(view()->exists('admin.partials.pagination'));
        $this->assertTrue(view()->exists('admin.partials.audit-actor'));
        $this->assertTrue(view()->exists('admin.slot-types.index'));
        $this->assertTrue(view()->exists('admin.system.settings'));
        $this->assertTrue(view()->exists('admin.system.updates'));
        $this->assertTrue(view()->exists('admin.system.backups.index'));
        $this->assertTrue(view()->exists('admin.system.search'));
        $this->assertTrue(view()->exists('admin.blocks.types.fallback'));
        $this->assertTrue(view()->exists('admin.blocks.types.fallback-inline'));
        $this->assertTrue(view()->exists('pages.partials.blocks.hero'));
        $this->assertTrue(view()->exists('pages.partials.blocks.columns'));
        $this->assertTrue(view()->exists('pages.partials.blocks.gallery'));
        $this->assertFalse(view()->exists('diagnostics.package-status'));
        $this->assertTrue(view()->exists('welcome'));
        $this->assertSame(false, config(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_LOADING_CONFIG));
        $this->assertSame(true, config(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_LOADING_CONFIG));
        $this->assertSame(false, config(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_STATUS_ROUTE_LOADING_CONFIG));
        $this->assertSame(true, config(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_LOADING_CONFIG));
        $this->assertSame(false, config(WebBlocksCmsServiceProvider::PACKAGE_MIGRATION_LOADING_CONFIG));
        $this->assertSame('fklavyenet/webblocks-cms', WebBlocksCmsServiceProvider::PACKAGE_COMPOSER_NAME);
        $this->assertSame('fklavyenet/webblocks-cms-starter', WebBlocksCmsServiceProvider::STARTER_PACKAGE_NAME);
        $this->assertSame([
            PackageStatusCommand::class,
            SearchRebuildCommand::class,
            PackageSyncWebBlocksUiIconsCommand::class,
            BlockTypeContractsAuditCommand::class,
            ImportDemoMedia::class,
            ResetPrimitiveBlocksCommand::class,
            SiteCloneCommand::class,
            SiteDeleteCommand::class,
            SiteExportCommand::class,
            SiteImportCommand::class,
            SitePromotionApplyCommand::class,
            SitePromotionDryRunCommand::class,
            SitePromotionInspectCommand::class,
            SyncCoreBlockTypesCommand::class,
            SystemBackupRestoreCommand::class,
        ], WebBlocksCmsServiceProvider::PACKAGE_CONSOLE_COMMANDS);
        $this->assertSame('composer require fklavyenet/webblocks-cms', WebBlocksCmsServiceProvider::TARGET_INSTALL_COMMAND);
        $this->assertSame('composer update fklavyenet/webblocks-cms', WebBlocksCmsServiceProvider::TARGET_UPDATE_COMMAND);
        $this->assertSame('public/vendor/webblocks-cms', WebBlocksCmsServiceProvider::ASSETS_PUBLISH_TARGET);
        $this->assertSame('stubs/vendor/webblocks-cms', WebBlocksCmsServiceProvider::STUBS_PUBLISH_TARGET);
        $this->assertSame('public/cms', WebBlocksCmsServiceProvider::ROOT_RUNTIME_ASSET_COMPATIBILITY_PATH);
    }

    #[Test]
    public function package_diagnostic_view_renders_through_the_package_namespace_without_overriding_root_view_resolution(): void
    {
        $rendered = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::diagnostics.package-status', [
            'viewNamespace' => WebBlocksCmsServiceProvider::VIEW_NAMESPACE,
            'packageBasePath' => base_path('packages/webblocks-cms'),
        ])->render();

        $welcomeViewPath = app('view.finder')->find('welcome');

        $this->assertStringContainsString('WebBlocks CMS package diagnostic view', $rendered);
        $this->assertStringContainsString('View namespace: webblocks-cms', $rendered);
        $this->assertStringContainsString('Package base path:', $rendered);
        $this->assertStringContainsString('Package transition consolidation is complete for all safely movable CMS-owned source. Root runtime remains authoritative for install, auth, profile, migrations, root public/cms runtime asset URLs, and compatibility wrappers.', $rendered);
        $this->assertSame(resource_path('views/welcome.blade.php'), $welcomeViewPath);
    }

    #[Test]
    public function package_admin_and_public_views_render_through_the_package_namespace_without_overriding_root_views(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $adminRendered = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.runtime-status', [
            'packageRouteName' => WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_NAME,
            'packageRoutePath' => WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_PATH,
        ])->render();

        $publicRendered = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::public.runtime-status', [
            'packageRouteName' => WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_NAME,
            'packageRoutePath' => WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_PATH,
        ])->render();

        $this->assertStringContainsString('Package Admin Runtime Status', $adminRendered);
        $this->assertStringContainsString('Package-owned admin routes and views now cover the safely movable CMS runtime slices, while install/auth, users, updates, backups, export/import, promotion, and root asset URLs remain root-authoritative boundaries.', $adminRendered);
        $this->assertStringContainsString('Package Public Runtime Status', $publicRendered);
        $this->assertStringContainsString('the main public layout, page shell, and search views now render from the package namespace too.', $publicRendered);
        $this->assertSame(resource_path('views/welcome.blade.php'), app('view.finder')->find('welcome'));
    }

    #[Test]
    public function package_diagnostic_route_is_explicitly_guarded_and_not_loaded_in_normal_runtime(): void
    {
        $this->assertFalse(config(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_LOADING_CONFIG, false));
        $this->assertTrue(config(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_LOADING_CONFIG, false));
        $this->assertFalse(config(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_STATUS_ROUTE_LOADING_CONFIG, false));
        $this->assertTrue(config(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_LOADING_CONFIG, false));
        $this->assertNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_NAME));
        $this->assertNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_NAME));
        $this->assertNotNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::ICON_ADMIN_INDEX_ROUTE_NAME));
        $this->assertNotNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::ICON_ADMIN_UPDATE_ROUTE_NAME));
        $this->assertNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_NAME));
        $this->assertFalse($this->routePathExists(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_PATH));
        $this->assertFalse($this->routePathExists(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_PATH));
        $this->assertFalse($this->routePathExists(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_PATH));
    }

    #[Test]
    public function guarded_package_diagnostic_route_can_be_loaded_explicitly_without_conflicting_with_root_runtime_routes(): void
    {
        $this->assertNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_NAME));
        $this->assertNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_NAME));
        $this->assertNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_NAME));
        $this->assertFalse($this->routePathExists(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_PATH));
        $this->assertFalse($this->routePathExists(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_PATH));
        $this->assertFalse($this->routePathExists(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_PATH));

        config()->set(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_LOADING_CONFIG, true);
        config()->set(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_LOADING_CONFIG, true);
        config()->set(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_STATUS_ROUTE_LOADING_CONFIG, true);
        config()->set(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_LOADING_CONFIG, true);
        config()->set(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_STATUS_ROUTE_LOADING_CONFIG, true);

        $provider = new class($this->app) extends WebBlocksCmsServiceProvider
        {
            /**
             * @var array<int, string>
             */
            public array $loadedRouteFiles = [];

            public function bootPackageRoutesForTest(): void
            {
                $this->bootRoutes();
            }

            protected function loadRoutesFrom($path): void
            {
                $this->loadedRouteFiles[] = $path;
            }
        };

        $provider->bootPackageRoutesForTest();

        $this->assertSame([
            base_path('packages/webblocks-cms/routes/'.WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_FILE),
            base_path('packages/webblocks-cms/routes/'.WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_FILE),
            base_path('packages/webblocks-cms/routes/'.WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_FILE),
        ], $provider->loadedRouteFiles);
        $this->assertNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_NAME));
        $this->assertNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_NAME));
        $this->assertNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_NAME));
        $this->assertFalse($this->routePathExists(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_PATH));
        $this->assertFalse($this->routePathExists(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_PATH));
        $this->assertFalse($this->routePathExists(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_PATH));
        $this->assertSame(resource_path('views/welcome.blade.php'), app('view.finder')->find('welcome'));
    }

    #[Test]
    public function package_migrations_stay_inert_while_assets_and_stubs_are_publishable(): void
    {
        $provider = new class($this->app) extends WebBlocksCmsServiceProvider
        {
            /**
             * @var array<int, string>
             */
            public array $loadedMigrationPaths = [];

            /**
             * @var array<int, array{paths: array<string, string>, group: string|null}>
             */
            public array $publishCalls = [];

            public function bootMigrationBoundaryForTest(): void
            {
                $this->bootMigrations();
            }

            public function bootPublishingBoundariesForTest(): void
            {
                $this->bootAssetPublishing();
                $this->bootStubPublishing();
            }

            protected function loadMigrationsFrom($paths): void
            {
                foreach ((array) $paths as $path) {
                    $this->loadedMigrationPaths[] = $path;
                }
            }

            public function publishes(array $paths, $groups = null): void
            {
                $this->publishCalls[] = [
                    'paths' => $paths,
                    'group' => $groups,
                ];
            }
        };

        $provider->bootMigrationBoundaryForTest();
        $provider->bootPublishingBoundariesForTest();

        $this->assertFalse(config(WebBlocksCmsServiceProvider::PACKAGE_MIGRATION_LOADING_CONFIG, false));
        $this->assertSame([], $provider->loadedMigrationPaths);
        $this->assertSame([
            [
                'paths' => [
                    base_path('packages/webblocks-cms/public') => public_path(str_replace('public/', '', WebBlocksCmsServiceProvider::ASSETS_PUBLISH_TARGET)),
                ],
                'group' => WebBlocksCmsServiceProvider::ASSETS_PUBLISH_TAG,
            ],
            [
                'paths' => [
                    base_path('packages/webblocks-cms/stubs') => base_path(WebBlocksCmsServiceProvider::STUBS_PUBLISH_TARGET),
                ],
                'group' => WebBlocksCmsServiceProvider::STUBS_PUBLISH_TAG,
            ],
        ], $provider->publishCalls);
        $this->assertFileExists(base_path('packages/webblocks-cms/public/cms/css/admin.css'));
        $this->assertFileExists(base_path('packages/webblocks-cms/public/cms/js/admin/core.js'));
        $this->assertFileExists(base_path('packages/webblocks-cms/public/cms/js/admin-sortable-list.js'));
        $this->assertFileExists(public_path('cms/css/admin.css'));
        $this->assertFileExists(public_path('cms/js/admin/core.js'));
        $this->assertFileExists(public_path('cms/js/admin-sortable-list.js'));
    }

    #[Test]
    public function package_seeders_and_runtime_support_classes_keep_root_compatibility_entrypoints(): void
    {
        $this->assertTrue(class_exists(PackageCoreCatalogSeeder::class));
        $this->assertTrue(class_exists(PackageIconCatalogSeeder::class));
        $this->assertTrue(class_exists(PackagePageTypeSeeder::class));
        $this->assertTrue(class_exists(PackageLayoutTypeSeeder::class));
        $this->assertTrue(class_exists(PackageSlotTypeSeeder::class));
        $this->assertTrue(class_exists(PackageAdminPagination::class));
        $this->assertTrue(class_exists(PackageBlockTypeIndexState::class));
        $this->assertTrue(class_exists(PackageSyncWebBlocksUiIconsCommand::class));
        $this->assertTrue(class_exists(PackageIconCatalogController::class));
        $this->assertTrue(class_exists(PackageSlotTypeController::class));
        $this->assertTrue(class_exists(PackageSystemSettingsController::class));
        $this->assertTrue(class_exists(PackageIconCatalogItemUpdateRequest::class));
        $this->assertTrue(class_exists(PackageSystemSettingsRequest::class));
        $this->assertTrue(class_exists(PackageIconCatalog::class));
        $this->assertTrue(class_exists(PackageWebBlocksIconManifestSyncer::class));
        $this->assertTrue(class_exists(PackageMediaIndexState::class));
        $this->assertTrue(class_exists(PackagePageIndexState::class));
        $this->assertTrue(class_exists(PackageBlockDeletionManager::class));
        $this->assertTrue(class_exists(PackageBlockPayloadWriter::class));
        $this->assertTrue(class_exists(PackageBlockTranslationResolver::class));
        $this->assertTrue(class_exists(PackageMediaKindResolver::class));
        $this->assertTrue(class_exists(PackageMediaUsageFilter::class));
        $this->assertTrue(class_exists(PackageMediaUsageResolver::class));
        $this->assertTrue(class_exists(PackageNavigationTree::class));
        $this->assertTrue(class_exists(PackageBlockTypeContractRegistry::class));
        $this->assertTrue(class_exists(PackagePageWorkflowManager::class));
        $this->assertTrue(class_exists(PackagePageRevisionManager::class));
        $this->assertTrue(class_exists(PackagePageLayoutManager::class));
        $this->assertTrue(class_exists(PackagePageLayoutSlotComparison::class));
        $this->assertTrue(class_exists(PackagePageLayoutSlotSyncer::class));
        $this->assertTrue(class_exists(PackagePageDuplicator::class));
        $this->assertTrue(class_exists(PackagePageDuplicateValidator::class));
        $this->assertTrue(class_exists(PackagePageJsonImporter::class));
        $this->assertTrue(class_exists(PackagePageSiteMover::class));
        $this->assertTrue(class_exists(PackagePageSiteMoveValidator::class));
        $this->assertTrue(class_exists(PackageSharedSlotSchema::class));
        $this->assertTrue(class_exists(PackageSharedSlotRevisionManager::class));
        $this->assertTrue(class_exists(PackageSharedSlotSourcePageManager::class));
        $this->assertTrue(class_exists(PackageLocale::class));
        $this->assertTrue(class_exists(PackageSite::class));
        $this->assertTrue(class_exists(PackageSiteDomain::class));
        $this->assertTrue(class_exists(PackagePage::class));
        $this->assertTrue(class_exists(PackagePageTranslation::class));
        $this->assertTrue(class_exists(PackagePageSlot::class));
        $this->assertTrue(class_exists(PackageBlock::class));
        $this->assertTrue(class_exists(PackageContactMessage::class));
        $this->assertTrue(class_exists(PackagePublicSearchIndex::class));
        $this->assertTrue(class_exists(PackageVisitorEvent::class));
        $this->assertTrue(class_exists(PackageSystemSetting::class));

        $this->assertTrue(is_subclass_of(CoreCatalogSeeder::class, PackageCoreCatalogSeeder::class));
        $this->assertTrue(is_subclass_of(IconCatalogSeeder::class, PackageIconCatalogSeeder::class));
        $this->assertTrue(is_subclass_of(PageTypeSeeder::class, PackagePageTypeSeeder::class));
        $this->assertTrue(is_subclass_of(LayoutTypeSeeder::class, PackageLayoutTypeSeeder::class));
        $this->assertTrue(is_subclass_of(SlotTypeSeeder::class, PackageSlotTypeSeeder::class));
        $this->assertTrue(is_subclass_of(AdminPagination::class, PackageAdminPagination::class));
        $this->assertTrue(is_subclass_of(BlockTypeIndexState::class, PackageBlockTypeIndexState::class));
        $this->assertTrue(is_subclass_of(SyncWebBlocksUiIconsCommand::class, PackageSyncWebBlocksUiIconsCommand::class));
        $this->assertTrue(is_subclass_of(IconCatalogController::class, PackageIconCatalogController::class));
        $this->assertTrue(is_subclass_of(SlotTypeController::class, PackageSlotTypeController::class));
        $this->assertTrue(is_subclass_of(SystemSettingsController::class, PackageSystemSettingsController::class));
        $this->assertTrue(is_subclass_of(IconCatalogItemUpdateRequest::class, PackageIconCatalogItemUpdateRequest::class));
        $this->assertTrue(is_subclass_of(SystemSettingsRequest::class, PackageSystemSettingsRequest::class));
        $this->assertTrue(is_subclass_of(IconCatalog::class, PackageIconCatalog::class));
        $this->assertTrue(is_subclass_of(WebBlocksIconManifestSyncer::class, PackageWebBlocksIconManifestSyncer::class));
        $this->assertTrue(is_subclass_of(MediaIndexState::class, PackageMediaIndexState::class));
        $this->assertTrue(is_subclass_of(PageIndexState::class, PackagePageIndexState::class));
        $this->assertTrue(is_subclass_of(BlockDeletionManager::class, PackageBlockDeletionManager::class));
        $this->assertTrue(is_subclass_of(BlockPayloadWriter::class, PackageBlockPayloadWriter::class));
        $this->assertTrue(is_subclass_of(BlockTranslationResolver::class, PackageBlockTranslationResolver::class));
        $this->assertTrue(is_subclass_of(MediaKindResolver::class, PackageMediaKindResolver::class));
        $this->assertTrue(is_subclass_of(MediaUsageFilter::class, PackageMediaUsageFilter::class));
        $this->assertTrue(is_subclass_of(MediaUsageResolver::class, PackageMediaUsageResolver::class));
        $this->assertTrue(is_subclass_of(NavigationTree::class, PackageNavigationTree::class));
        $this->assertTrue(is_subclass_of(BlockTypeContractRegistry::class, PackageBlockTypeContractRegistry::class));
        $this->assertTrue(is_subclass_of(PageWorkflowManager::class, PackagePageWorkflowManager::class));
        $this->assertTrue(is_subclass_of(PageRevisionManager::class, PackagePageRevisionManager::class));
        $this->assertTrue(is_subclass_of(PageLayoutManager::class, PackagePageLayoutManager::class));
        $this->assertTrue(is_subclass_of(PageLayoutSlotComparison::class, PackagePageLayoutSlotComparison::class));
        $this->assertTrue(is_subclass_of(PageLayoutSlotSyncer::class, PackagePageLayoutSlotSyncer::class));
        $this->assertTrue(is_subclass_of(PageDuplicator::class, PackagePageDuplicator::class));
        $this->assertTrue(is_subclass_of(PageDuplicateValidator::class, PackagePageDuplicateValidator::class));
        $this->assertTrue(is_subclass_of(PageJsonImporter::class, PackagePageJsonImporter::class));
        $this->assertTrue(is_subclass_of(PageSiteMover::class, PackagePageSiteMover::class));
        $this->assertTrue(is_subclass_of(PageSiteMoveValidator::class, PackagePageSiteMoveValidator::class));
        $this->assertTrue(is_subclass_of(SharedSlotSchema::class, PackageSharedSlotSchema::class));
        $this->assertTrue(is_subclass_of(SharedSlotRevisionManager::class, PackageSharedSlotRevisionManager::class));
        $this->assertTrue(is_subclass_of(SharedSlotSourcePageManager::class, PackageSharedSlotSourcePageManager::class));
        $this->assertTrue(is_subclass_of(Locale::class, PackageLocale::class));
        $this->assertTrue(is_subclass_of(Site::class, PackageSite::class));
        $this->assertTrue(is_subclass_of(SiteDomain::class, PackageSiteDomain::class));
        $this->assertTrue(is_subclass_of(Page::class, PackagePage::class));
        $this->assertTrue(is_subclass_of(PageTranslation::class, PackagePageTranslation::class));
        $this->assertTrue(is_subclass_of(PageSlot::class, PackagePageSlot::class));
        $this->assertTrue(is_subclass_of(Block::class, PackageBlock::class));
        $this->assertTrue(is_subclass_of(ContactMessage::class, PackageContactMessage::class));
        $this->assertTrue(is_subclass_of(PublicSearchIndex::class, PackagePublicSearchIndex::class));
        $this->assertTrue(is_subclass_of(VisitorEvent::class, PackageVisitorEvent::class));
        $this->assertTrue(is_subclass_of(SystemSetting::class, PackageSystemSetting::class));
    }

    #[Test]
    public function icon_catalog_runtime_uses_package_route_and_command_authority_with_root_compatibility_wrappers(): void
    {
        $iconRoute = app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::ICON_ADMIN_INDEX_ROUTE_NAME);
        $constructor = (new \ReflectionClass(PackageSyncWebBlocksUiIconsCommand::class))->getConstructor();
        $syncerParameterType = $constructor?->getParameters()[0]?->getType();

        $this->assertNotNull($iconRoute);
        $this->assertSame(
            PackageIconCatalogController::class.'@index',
            $iconRoute->getAction('controller')
        );
        $this->assertInstanceOf(\ReflectionNamedType::class, $syncerParameterType);
        $this->assertSame(PackageWebBlocksIconManifestSyncer::class, $syncerParameterType->getName());
        $this->assertTrue(is_subclass_of(SyncWebBlocksUiIconsCommand::class, PackageSyncWebBlocksUiIconsCommand::class));
        $this->assertTrue(is_subclass_of(IconCatalogController::class, PackageIconCatalogController::class));
        $this->assertTrue(is_subclass_of(WebBlocksIconManifestSyncer::class, PackageWebBlocksIconManifestSyncer::class));
    }

    protected function routePathExists(string $path): bool
    {
        $expectedPath = ltrim($path, '/');

        foreach (app('router')->getRoutes() as $route) {
            if ($route->uri() === $expectedPath) {
                return true;
            }
        }

        return false;
    }

    #[Test]
    public function package_default_update_config_is_available_under_the_existing_config_key(): void
    {
        $packageConfigPath = base_path('packages/webblocks-cms/config/webblocks-updates.php');

        $this->assertFileExists($packageConfigPath);
        $this->assertSame('https://updates.webblocksui.com', config('webblocks-updates.server_url'));
        $this->assertSame('stable', config('webblocks-updates.channel'));
        $this->assertSame('1', config('webblocks-updates.api_version'));
        $this->assertSame(WebBlocks::HANDLE, config('webblocks-updates.product'));
        $this->assertSame(WebBlocks::VERSION, config('webblocks-updates.current_version'));
    }

    #[Test]
    public function package_default_contact_config_is_available_under_the_existing_config_key(): void
    {
        $packageConfigPath = base_path('packages/webblocks-cms/config/contact.php');

        $this->assertFileExists($packageConfigPath);
        $this->assertSame(3, config('contact.minimum_submit_seconds'));
        $this->assertSame(5, config('contact.rate_limit_per_minute'));
        $this->assertSame(
            'Thanks for your message. We will get back to you soon.',
            config('contact.success_message')
        );
    }

    #[Test]
    public function package_default_demo_media_config_is_available_under_the_existing_config_key(): void
    {
        $packageConfigPath = base_path('packages/webblocks-cms/config/demo_media.php');

        $this->assertFileExists($packageConfigPath);
        $this->assertCount(9, config('demo_media.items', []));
        $this->assertSame('home-hero-01', config('demo_media.items.0.key'));
        $this->assertSame('gallery-04', config('demo_media.items.8.key'));
    }

    #[Test]
    public function package_default_cms_config_is_available_under_the_existing_config_key(): void
    {
        $packageConfigPath = base_path('packages/webblocks-cms/config/cms.php');

        $this->assertFileExists($packageConfigPath);
        $this->assertSame('DISABLED', config('cms.install.git_protection.disabled_push_url'));
        $this->assertSame(15, config('cms.install.git_protection.timeout_seconds'));
        $this->assertSame('auto', config('cms.backup.execution'));
        $this->assertSame('webblocks_visitor_consent', config('cms.visitor_reports.consent_cookie_name'));
        $this->assertContains('googleother', config('cms.visitor_reports.ignored_user_agents', []));
    }

    #[Test]
    public function package_runtime_boundary_config_defaults_keep_guarded_slices_disabled(): void
    {
        $packageConfigPath = base_path('packages/webblocks-cms/config/webblocks-cms.php');

        $this->assertFileExists($packageConfigPath);
        $this->assertFalse(config(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_LOADING_CONFIG));
        $this->assertTrue(config(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_LOADING_CONFIG));
        $this->assertFalse(config(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_STATUS_ROUTE_LOADING_CONFIG));
        $this->assertTrue(config(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_LOADING_CONFIG));
        $this->assertFalse(config(WebBlocksCmsServiceProvider::PACKAGE_MIGRATION_LOADING_CONFIG));
    }

    #[Test]
    public function root_update_config_remains_the_install_override_after_package_merge(): void
    {
        $this->assertFileExists(config_path('webblocks-updates.php'));

        config()->set('webblocks-updates.server_url', 'https://override.example.test');
        config()->set('webblocks-updates.installer.lock_name', 'custom-system-updates-lock');

        $this->assertSame('https://override.example.test', config('webblocks-updates.server_url'));
        $this->assertSame('custom-system-updates-lock', config('webblocks-updates.installer.lock_name'));
        $this->assertSame(WebBlocks::VERSION, config('webblocks-updates.current_version'));
    }

    #[Test]
    public function root_contact_config_remains_the_install_override_after_package_merge(): void
    {
        $this->assertFileExists(config_path('contact.php'));

        config()->set('contact.rate_limit_per_minute', 9);
        config()->set('contact.success_message', 'Custom success message');

        $this->assertSame(9, config('contact.rate_limit_per_minute'));
        $this->assertSame('Custom success message', config('contact.success_message'));
    }

    #[Test]
    public function root_demo_media_config_remains_the_install_override_after_package_merge(): void
    {
        $this->assertFileExists(config_path('demo_media.php'));

        config()->set('demo_media.items', [
            [
                'key' => 'custom-demo-item',
                'topic' => 'custom',
                'title' => 'Custom demo item',
                'folder' => 'Custom',
                'source_url' => 'https://example.test/custom-demo-item.jpg',
                'alt' => 'Custom demo item',
            ],
        ]);

        $this->assertCount(1, config('demo_media.items', []));
        $this->assertSame('custom-demo-item', config('demo_media.items.0.key'));
    }

    #[Test]
    public function root_cms_config_remains_the_install_override_after_package_merge(): void
    {
        $this->assertFileExists(config_path('cms.php'));

        config()->set('cms.backup.execution', 'mysqldump');
        config()->set('cms.visitor_reports.enabled', false);
        config()->set('cms.install.git_protection.disabled_push_url', 'NO-PUSH');

        $this->assertSame('mysqldump', config('cms.backup.execution'));
        $this->assertFalse(config('cms.visitor_reports.enabled'));
        $this->assertSame('NO-PUSH', config('cms.install.git_protection.disabled_push_url'));
    }
}
