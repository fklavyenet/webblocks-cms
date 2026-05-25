<?php

namespace WebBlocks\Cms\Console;

use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebBlocks\Cms\Support\System\Updates\UpdateMigrationRunner;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PackageStatusCommand extends Command
{
  protected $signature = 'webblocks:package-status
  {--view-check : Render the package diagnostic view through the package namespace}';

  protected $description = 'Show read-only WebBlocks CMS package transition status';

  public function handle(): int
  {
    $packageRoot = dirname(__DIR__, 2);
    $rootComposer = $this->composerJson(base_path('composer.json'));
    $packageComposer = $this->composerJson($packageRoot.'/composer.json');
    $diagnosticView = 'diagnostics.package-status';
    $namespacedDiagnosticView = WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::'.$diagnosticView;
    $diagnosticRouteFile = WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_FILE;
    $diagnosticRouteName = WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_NAME;
    $diagnosticRoutePath = WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_PATH;
    $adminRouteFile = WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_FILE;
    $adminRouteName = WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_NAME;
    $adminRoutePath = WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_PATH;
    $publicRouteFile = WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_FILE;
    $publicRouteName = WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_NAME;
    $publicRoutePath = WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_PATH;
    $publicHomeRouteLoaded = app('router')->getRoutes()->getByName('home') !== null;
    $packageMigrationLoadingConfig = WebBlocksCmsServiceProvider::PACKAGE_MIGRATION_LOADING_CONFIG;
    $configFiles = $this->phpFiles($packageRoot.'/config');
    $routeFiles = $this->resourceFiles($packageRoot.'/routes');
    $viewFiles = $this->resourceFiles($packageRoot.'/resources/views');
    $packageBlockViewFiles = $this->resourceFiles($packageRoot.'/resources/views/pages/partials/blocks');
    $rootBlockViewFiles = $this->resourceFiles(resource_path('views/pages/partials/blocks'));
    $migrationFiles = $this->resourceFiles($packageRoot.'/database/migrations');
    $updateMigrationStrategy = app(UpdateMigrationRunner::class)->strategyReport(base_path());
    $seederFiles = $this->resourceFiles($packageRoot.'/database/seeders');
    $publicFiles = $this->resourceFiles($packageRoot.'/public');
    $stubFiles = $this->resourceFiles($packageRoot.'/stubs');
    $shouldCheckView = (bool) $this->option('view-check');

    $providerDiscoveryPresent = $this->composerProviderDiscoveryPresent($packageComposer, WebBlocksCmsServiceProvider::class);
    $packageComposerNamePresent = $this->composerPackageNamePresent(
      $packageComposer,
      WebBlocksCmsServiceProvider::PACKAGE_COMPOSER_NAME
    );
    $rootComposerNamePresent = $this->composerPackageNamePresent(
      $rootComposer,
      WebBlocksCmsServiceProvider::PACKAGE_COMPOSER_NAME
    );

    $this->line('Package: '.WebBlocksCmsServiceProvider::PACKAGE_COMPOSER_NAME);
    $this->line('Mode: read-only diagnostic only');
    $this->newLine();

    $this->line('Package resource boundary status');
    $this->line('Package base path: '.$packageRoot);
    $this->line('Package src path present: '.$this->yesNo(is_dir($packageRoot.'/src')));
    $this->line('Package config path present: '.$this->yesNo(is_dir($packageRoot.'/config')));
    $this->line('Package config files present: '.($configFiles === [] ? 'none' : implode(', ', array_map('basename', $configFiles))));
    $this->line('Expected package config defaults:');

    foreach (WebBlocksCmsServiceProvider::PACKAGE_CONFIG_DEFAULTS as $file) {
      $this->line(sprintf(
        '- %s: package default=%s, root override=%s',
        $file,
        $this->yesNo(is_file($packageRoot.'/config/'.$file)),
        $this->yesNo(is_file(config_path($file)))
      ));
    }

    $this->line('Package routes path present: '.$this->yesNo(is_dir($packageRoot.'/routes')));
    $this->line('Package route files status: '.$this->resourceStatus($routeFiles));
    $this->line('Package route Composer readiness: '.$this->yesNo($providerDiscoveryPresent && $this->expectedFilesPresent(
      $packageRoot.'/routes',
      WebBlocksCmsServiceProvider::PACKAGE_ROUTE_FILES
    )).' (provider discovery plus guarded route files)');
    $this->line('Package source authority domains: '.implode('; ', WebBlocksCmsServiceProvider::PACKAGE_SOURCE_AUTHORITY_DOMAINS).'.');
    $this->line('Expected package diagnostic route file exists: '.$this->yesNo(is_file($packageRoot.'/routes/'.$diagnosticRouteFile)).' ('.$diagnosticRouteFile.')');
    $this->line('Package diagnostic route loading guard enabled: '.$this->yesNo($this->diagnosticRouteLoadingEnabled()).' ('.WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_LOADING_CONFIG.')');
    $this->line('Package diagnostic route loaded in active runtime: '.$this->yesNo($this->routeIsRegistered($diagnosticRouteName, $diagnosticRoutePath)).' ('.$diagnosticRouteName.' at '.$diagnosticRoutePath.')');
    $this->line('Expected package admin route file exists: '.$this->yesNo(is_file($packageRoot.'/routes/'.$adminRouteFile)).' ('.$adminRouteFile.')');
    $this->line('Package admin route file loading enabled: '.$this->yesNo($this->packageAdminRouteLoadingEnabled()).' ('.WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_LOADING_CONFIG.')');
    $this->line('Package admin slice loaded in active runtime: '.$this->yesNo($this->routeIsRegistered($adminRouteName, $adminRoutePath)).' ('.$adminRouteName.' at '.$adminRoutePath.')');
    $this->line('Package icon catalog admin routes loaded in active runtime: '.$this->yesNo(
      app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::ICON_ADMIN_INDEX_ROUTE_NAME) !== null
      && app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::ICON_ADMIN_UPDATE_ROUTE_NAME) !== null
    ).' ('.WebBlocksCmsServiceProvider::ICON_ADMIN_INDEX_ROUTE_NAME.', '.WebBlocksCmsServiceProvider::ICON_ADMIN_UPDATE_ROUTE_NAME.')');
    $this->line('Expected package public route file exists: '.$this->yesNo(is_file($packageRoot.'/routes/'.$publicRouteFile)).' ('.$publicRouteFile.')');
    $this->line('Package public route file loading enabled: '.$this->yesNo($this->packagePublicRouteLoadingEnabled()).' ('.WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_LOADING_CONFIG.')');
    $this->line('Package public slice loaded in active runtime: '.$this->yesNo($this->routeIsRegistered($publicRouteName, $publicRoutePath)).' ('.$publicRouteName.' at '.$publicRoutePath.')');
    $this->line('Package public runtime routes loaded in active runtime: '.$this->yesNo($publicHomeRouteLoaded).' (home, localized.home, search, pages.show, contact-messages.store, public.privacy-consent.sync, admin-api.*)');
    $this->line('Active runtime route loading remains root-authoritative: partial (install, auth, and profile stay root-owned while CMS admin and public runtime routes now load from the package).');
    $this->line('Root route compatibility state: root routes now remain only for install, auth, profile, and project-level extension points.');
    $this->line('Package resources/views path present: '.$this->yesNo(is_dir($packageRoot.'/resources/views')));
    $this->line('Package view files status: '.$this->resourceStatus($viewFiles));
    $this->line('Package view Composer readiness: '.$this->yesNo($providerDiscoveryPresent && $this->expectedFilesPresent(
      $packageRoot.'/resources/views',
      WebBlocksCmsServiceProvider::PACKAGE_VIEW_FILES
    )).' (provider discovery plus package view namespace)');
    $this->line('Package admin slice view exists: '.$this->yesNo(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.runtime-status')).' ('.WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.runtime-status)');
    $this->line('Package public slice view exists: '.$this->yesNo(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::public.runtime-status')).' ('.WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::public.runtime-status)');
    $this->line('Package icon catalog views present: '.$this->expectedFilesStatus(
      $packageRoot.'/resources/views',
      WebBlocksCmsServiceProvider::ICON_VIEW_FILES
    ));
    $this->line('Package admin runtime views present: '.$this->expectedFilesStatus(
      $packageRoot.'/resources/views',
      WebBlocksCmsServiceProvider::ADMIN_RUNTIME_VIEW_FILES
    ));
    $this->line('Package shared admin partial views present: '.$this->expectedFilesStatus(
      $packageRoot.'/resources/views',
      WebBlocksCmsServiceProvider::SHARED_ADMIN_VIEW_FILES
    ));
    $this->line('Root icon catalog view compatibility wrappers present: '.$this->rootCompatibilityFilesStatus(
      resource_path('views'),
      WebBlocksCmsServiceProvider::ROOT_ICON_VIEW_WRAPPER_FILES
    ));
    $this->line('Root admin runtime view compatibility wrappers present: '.$this->rootCompatibilityFilesStatus(
      resource_path('views'),
      WebBlocksCmsServiceProvider::ROOT_ADMIN_RUNTIME_VIEW_WRAPPER_FILES
    ));
    $this->line('Root shared admin partial compatibility wrappers present: '.$this->rootCompatibilityFilesStatus(
      resource_path('views'),
      WebBlocksCmsServiceProvider::ROOT_SHARED_ADMIN_VIEW_WRAPPER_FILES
    ));
    $this->line('Icon catalog view package authority state: '.$this->yesNo(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.icons.index')).' (package controller renders '.WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.icons.index)');
    $this->line('Shared admin partial package authority state: '.$this->yesNo($this->sharedAdminPartialsUsePackageAuthority()).' (package-owned admin views can render shared page headers, flash messages, listing filters, pagination, page actions, audit actor output, and form actions through the package namespace while root Blade wrappers remain available for compatibility).');
    $this->line('Package public block renderer partials present: '.$this->directoryResourceStatus(
      $packageBlockViewFiles,
      'files under package pages/partials/blocks'
    ));
    $this->line('Root public block renderer compatibility wrappers present: '.$this->matchingResourceFilesStatus(
      $packageBlockViewFiles,
      $rootBlockViewFiles,
      'matching root wrappers'
    ));
    $this->line('Public block renderer package authority state: '.$this->yesNo($this->expectedViewsExist([
      WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.hero',
      WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.columns',
      WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.gallery',
      WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.sticky-navbar',
      WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.sidebar-navigation',
      WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.fallback',
      WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.missing-renderer',
    ])).' (Block::publicRenderView() now prefers package block partials first; root pages.partials.blocks.* remains available for install-specific or custom fallback)');
    $this->line('Root view compatibility state: mixed (admin layout, icon, public layout, public page shell, public search, slot entry views, and core public block renderers now render through the package namespace, while many admin wrappers and install-specific root block fallbacks remain root-accessible).');
    $this->line('Package database/migrations path present: '.$this->yesNo(is_dir($packageRoot.'/database/migrations')));
    $this->line('Package migration boundary status: '.$this->resourceBoundaryStatus($migrationFiles));
    $this->line('Package migration files status: '.$this->resourceStatus($migrationFiles));
    $this->line('Package migration loading guard enabled: '.$this->yesNo($this->packageMigrationLoadingEnabled()).' ('.$packageMigrationLoadingConfig.')');
    $this->line('Package migrations loaded in active runtime: no');
    $this->line('Legacy root migration compatibility state: yes (root database/migrations remains authoritative for source-maintained installs).');
    $this->line('Package update migration readiness: package consumer System Updates use packages/webblocks-cms/database/migrations/updates when PHP migrations are present and skip host application migrations otherwise.');
    $this->line('Detected System Update migration strategy: '.$updateMigrationStrategy['strategy'].' ('.$updateMigrationStrategy['reason'].')');
    $this->line('Package database/seeders path present: '.$this->yesNo(is_dir($packageRoot.'/database/seeders')));
    $this->line('Package seeder boundary status: '.$this->resourceBoundaryStatus($seederFiles));
    $this->line('Package seeder files status: '.$this->resourceStatus($seederFiles));
    $this->line('Package catalog seeders present: '.$this->expectedFilesStatus(
      $packageRoot.'/database/seeders',
      WebBlocksCmsServiceProvider::PACKAGE_SEEDER_FILES
    ));
    $this->line('Root catalog seeder compatibility wrappers present: '.$this->rootCompatibilityFilesStatus(
      base_path('database/seeders'),
      WebBlocksCmsServiceProvider::PACKAGE_SEEDER_FILES
    ));
    $this->line('Package public path present: '.$this->yesNo(is_dir($packageRoot.'/public')));
    $this->line('Package public asset boundary status: '.$this->resourceBoundaryStatus($publicFiles));
    $this->line('Package public assets status: '.$this->resourceStatus($publicFiles));
    $this->line('Package public asset files present: '.$this->expectedFilesStatus(
      $packageRoot.'/public',
      WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ASSET_FILES
    ));
    $this->line('Package public asset publish readiness: '.$this->yesNo($this->expectedFilesPresent(
      $packageRoot.'/public',
      WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ASSET_FILES
    )).' (tag '.WebBlocksCmsServiceProvider::ASSETS_PUBLISH_TAG.' publishes package assets to '.WebBlocksCmsServiceProvider::ASSETS_PUBLISH_TARGET.')');
    $this->line('Root compatibility public assets present: '.$this->rootCompatibilityFilesStatus(
      public_path(),
      WebBlocksCmsServiceProvider::ROOT_PUBLIC_ASSET_COMPATIBILITY_FILES
    ));
    $this->line('Active public runtime asset URLs remain root compatibility paths: '.$this->yesNo(
      $this->packagePublicLayoutUsesRootCompatibilityAssets($packageRoot)
    ).' (package public layout still references root '.WebBlocksCmsServiceProvider::ROOT_RUNTIME_ASSET_COMPATIBILITY_PATH.' assets for active runtime compatibility)');
    $this->line('Root runtime asset compatibility path: '.WebBlocksCmsServiceProvider::ROOT_RUNTIME_ASSET_COMPATIBILITY_PATH.' (active admin and public runtime asset URLs still resolve here).');
    $this->line('Legacy root public asset compatibility state: yes (root '.WebBlocksCmsServiceProvider::ROOT_RUNTIME_ASSET_COMPATIBILITY_PATH.' and install-owned public/site remain the active runtime asset paths, even though the package now also carries the public layout CSS and JS plus admin CSS, JS, and CMS brand source files it needs).');
    $this->line('Future package public asset Composer readiness: partial (package-owned public rendering assets plus admin CSS, JS, and CMS brand source files now exist, but current WebBlocks UI CDN pinning and root '.WebBlocksCmsServiceProvider::ROOT_RUNTIME_ASSET_COMPATIBILITY_PATH.' runtime asset flow stay unchanged).');
    $this->line('Package stubs path present: '.$this->yesNo(is_dir($packageRoot.'/stubs')));
    $this->line('Package stub boundary status: '.$this->resourceBoundaryStatus($stubFiles));
    $this->line('Package stubs status: '.$this->resourceStatus($stubFiles));
    $this->line('Package starter stubs present: '.$this->expectedFilesStatus(
      $packageRoot.'/stubs',
      WebBlocksCmsServiceProvider::PACKAGE_STUB_FILES
    ));
    $this->line('Package stub publish readiness: '.$this->yesNo($this->expectedFilesPresent(
      $packageRoot.'/stubs',
      WebBlocksCmsServiceProvider::PACKAGE_STUB_FILES
    )).' (tag '.WebBlocksCmsServiceProvider::STUBS_PUBLISH_TAG.' publishes starter stubs to '.WebBlocksCmsServiceProvider::STUBS_PUBLISH_TARGET.')');
    $this->line('Starter stub readiness: yes (package-owned starter stubs are present; a separate starter package is still intentionally not created here).');
    $this->line('Package service provider loaded: '.$this->yesNo($this->laravel->providerIsLoaded(WebBlocksCmsServiceProvider::class)));
    $this->line('Package view namespace registered: '.$this->yesNo($this->viewNamespaceIsRegistered()).' ('.WebBlocksCmsServiceProvider::VIEW_NAMESPACE.')');
    $this->line('Package diagnostic view exists: '.$this->yesNo(view()->exists($namespacedDiagnosticView)).' ('.$namespacedDiagnosticView.')');
    $this->line('Package low-risk runtime support moves present: '.$this->expectedFilesStatus(
      $packageRoot.'/src/Support',
      WebBlocksCmsServiceProvider::LOW_RISK_RUNTIME_SUPPORT_FILES
    ));
    $this->line('Root runtime support compatibility wrappers present: '.$this->rootCompatibilityFilesStatus(
      base_path('app/Support'),
      WebBlocksCmsServiceProvider::LOW_RISK_RUNTIME_SUPPORT_FILES
    ));
    $this->line('Package icon runtime moves present: '.$this->expectedFilesStatus(
      $packageRoot.'/src',
      WebBlocksCmsServiceProvider::ICON_RUNTIME_FILES
    ));
    $this->line('Package admin runtime moves present: '.$this->expectedFilesStatus(
      $packageRoot.'/src',
      WebBlocksCmsServiceProvider::ADMIN_RUNTIME_FILES
    ));
    $this->line('Root icon runtime compatibility wrappers present: '.$this->rootCompatibilityFilesStatus(
      base_path('app'),
      WebBlocksCmsServiceProvider::ROOT_ICON_RUNTIME_WRAPPER_FILES
    ));
    $this->line('Root admin runtime compatibility wrappers present: '.$this->rootCompatibilityFilesStatus(
      base_path('app'),
      WebBlocksCmsServiceProvider::ROOT_ADMIN_RUNTIME_WRAPPER_FILES
    ));
    $this->line('Package public rendering support moves present: '.$this->expectedFilesStatus(
      $packageRoot.'/src',
      WebBlocksCmsServiceProvider::PUBLIC_RENDERING_RUNTIME_FILES
    ));
    $this->line('Root public rendering support compatibility wrappers present: '.$this->rootCompatibilityFilesStatus(
      base_path('app'),
      WebBlocksCmsServiceProvider::ROOT_PUBLIC_RENDERING_RUNTIME_WRAPPER_FILES
    ));
    $this->line('Package public model foundation moves present: '.$this->expectedFilesStatus(
      $packageRoot.'/src',
      WebBlocksCmsServiceProvider::PUBLIC_MODEL_FOUNDATION_FILES
    ));
    $this->line('Root public model compatibility wrappers present: '.$this->rootCompatibilityFilesStatus(
      base_path('app'),
      WebBlocksCmsServiceProvider::ROOT_PUBLIC_MODEL_WRAPPER_FILES
    ));
    $this->line('Root compatibility wrappers: '.implode('; ', WebBlocksCmsServiceProvider::ROOT_COMPATIBILITY_WRAPPER_DOMAINS).'.');
    $this->line('Public model foundation package authority state: yes (Block, ContactMessage, Locale, Page, PageSlot, PageTranslation, PublicSearchIndex, Site, SiteDomain, SystemSetting, and VisitorEvent now live under package Models without root App\\Models wrappers).');
    $this->line('User model ownership state: root-owned permanently for now (User remains app-owned and was not moved into the package).');
    $this->line('Icon catalog admin route package authority state: '.$this->yesNo($this->routeUsesController(
      WebBlocksCmsServiceProvider::ICON_ADMIN_INDEX_ROUTE_NAME,
      'WebBlocks\\Cms\\Http\\Controllers\\Admin\\IconCatalogController'
    )).' ('.WebBlocksCmsServiceProvider::ICON_ADMIN_INDEX_ROUTE_NAME.' uses the package controller directly)');
    $this->line('Core admin runtime package authority state: yes (Pages, Blocks, Media, Shared Slots, Navigation, Block Types, and Page Layouts now execute from package controllers, requests, support classes, and view trees without root App\\... wrappers).');
    $this->line('Site and Locale admin runtime package authority state: '.$this->yesNo($this->siteLocaleAdminRuntimeUsesPackageAuthority()).' (Sites, Site Domains, Site Variables, and Locales now execute from package controllers, requests, support classes, models, and view trees without root App\\... wrappers).');
    $this->line('Operational admin runtime package authority state: '.$this->yesNo($this->operationalAdminRuntimeUsesPackageAuthority()).' (Dashboard, Contact Messages admin review, Visitor Reports, Slot Types, System Search, and System Settings now execute from package controllers, requests where applicable, support classes, and view trees without root App\\... wrappers).');
    $this->line('Icon catalog sync command package authority state: '.$this->yesNo($this->syncCommandUsesPackageImplementation()).' ('.WebBlocksCmsServiceProvider::ICON_SYNC_COMMAND_NAME.' is registered by the package provider with the package command)');
    $this->line('Package diagnostic view render check: '.$this->diagnosticViewRenderStatus(
      $shouldCheckView,
      $namespacedDiagnosticView,
      $packageRoot
    ));
    $this->line('Package Composer package name present: '.$this->yesNo($packageComposerNamePresent).' ('.WebBlocksCmsServiceProvider::PACKAGE_COMPOSER_NAME.')');
    $this->line('Package Composer provider discovery present: '.$this->yesNo($providerDiscoveryPresent).' ('.WebBlocksCmsServiceProvider::class.')');
    $this->line('Package Composer seeder autoload present: '.$this->yesNo($this->composerSeederAutoloadPresent($packageComposer)).' (WebBlocks\\Cms\\Database\\Seeders\\)');
    $this->line('Root Composer package name present: '.$this->yesNo($rootComposerNamePresent).' ('.WebBlocksCmsServiceProvider::PACKAGE_COMPOSER_NAME.')');
    $this->line('Root Composer package type package-ready: '.$this->yesNo($this->composerPackageTypePresent($rootComposer, 'library')).' (library)');
    $this->line('Root Composer package autoload present: '.$this->yesNo($this->composerAutoloadPresent($rootComposer, 'WebBlocks\\Cms\\', 'packages/webblocks-cms/src/')).' (WebBlocks\\Cms\\ => packages/webblocks-cms/src/)');
    $this->line('Root Composer provider discovery present: '.$this->yesNo($this->composerProviderDiscoveryPresent($rootComposer, WebBlocksCmsServiceProvider::class)).' ('.WebBlocksCmsServiceProvider::class.')');
    $this->line('Target Composer install flow: '.WebBlocksCmsServiceProvider::TARGET_INSTALL_COMMAND.' (tagged root package metadata now supports direct consumer installs, while the maintenance repo runtime remains authoritative for local source development).');
    $this->line('Target Composer update flow: '.WebBlocksCmsServiceProvider::TARGET_UPDATE_COMMAND.' followed by package-aware migration handling, catalog sync, block-types:sync-core, cache clear, asset publish or sync when needed, package diagnostics, and installed-version sync when release state is real.');
    $this->line('Root migration/update/install/auth blockers: '.implode('; ', WebBlocksCmsServiceProvider::STARTER_SPLIT_BLOCKERS).'.');
    $this->line('Starter split readiness: not ready (package transition consolidation is complete for all safely movable CMS-owned source, but the remaining install/auth, User, root runtime asset path, and root migration or update authority blockers still prevent a starter split).');
    $this->line('Starter foundation readiness: partial (root package metadata now supports direct Composer installation and provider discovery, while the maintenance repo still carries the remaining install/auth, User, root runtime asset path, and root migration or update authority blockers; '.WebBlocksCmsServiceProvider::STARTER_PACKAGE_NAME.' is intentionally not created yet).');
    $this->line('Composer-managed update target note: future Composer-managed package updates remain the target boundary, while current root Composer and runtime update flow stay authoritative.');
    $this->newLine();

    $this->line('Transition note: root runtime remains authoritative unless a resource has been intentionally moved and wired.');
    $this->line('This command performs no publishing, migrations, cache clearing, file writes, database writes, install-state changes, or update-state changes.');

    return self::SUCCESS;
  }

  protected function diagnosticViewRenderStatus(bool $shouldCheckView, string $viewName, string $packageRoot): string
  {
    if (! $shouldCheckView) {
      return 'not run (use --view-check)';
    }

    if (! view()->exists($viewName)) {
      return 'failed (diagnostic view missing)';
    }

    try {
      view($viewName, [
        'viewNamespace' => WebBlocksCmsServiceProvider::VIEW_NAMESPACE,
        'packageBasePath' => $packageRoot,
      ])->render();
    } catch (\Throwable $exception) {
      return 'failed ('.$exception::class.': '.$exception->getMessage().')';
    }

    return 'success';
  }

  protected function diagnosticRouteLoadingEnabled(): bool
  {
    return (bool) config(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_LOADING_CONFIG, false);
  }

  protected function packageMigrationLoadingEnabled(): bool
  {
    return (bool) config(WebBlocksCmsServiceProvider::PACKAGE_MIGRATION_LOADING_CONFIG, false);
  }

  protected function packageAdminRouteLoadingEnabled(): bool
  {
    return (bool) config(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_LOADING_CONFIG, false);
  }

  protected function packagePublicRouteLoadingEnabled(): bool
  {
    return (bool) config(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_LOADING_CONFIG, false);
  }

  protected function routeIsRegistered(string $name, string $path): bool
  {
    $route = app('router')->getRoutes()->getByName($name);

    if ($route === null) {
      return false;
    }

    return '/'.$route->uri() === $path;
  }

  protected function routeUsesController(string $name, string $controller): bool
  {
    $route = app('router')->getRoutes()->getByName($name);

    if ($route === null) {
      return false;
    }

    $routeController = (string) ($route->getAction('controller') ?? '');

    return $routeController === $controller || str_starts_with($routeController, $controller.'@');
  }

  protected function syncCommandUsesPackageImplementation(): bool
  {
    $packageCommandClass = 'WebBlocks\\Cms\\Console\\SyncWebBlocksUiIconsCommand';
    $syncerClass = 'WebBlocks\\Cms\\Support\\Icons\\WebBlocksIconManifestSyncer';

    if (! class_exists($packageCommandClass) || ! class_exists($syncerClass)) {
      return false;
    }

    $constructor = (new \ReflectionClass($packageCommandClass))->getConstructor();

    if ($constructor === null) {
      return false;
    }

    $parameter = $constructor->getParameters()[0] ?? null;
    $type = $parameter?->getType();

    return $type instanceof \ReflectionNamedType && $type->getName() === $syncerClass;
  }

  protected function siteLocaleAdminRuntimeUsesPackageAuthority(): bool
  {
    return $this->routeUsesController('admin.sites.index', 'WebBlocks\\Cms\\Http\\Controllers\\Admin\\SiteController')
      && $this->routeUsesController('admin.sites.domains.index', 'WebBlocks\\Cms\\Http\\Controllers\\Admin\\SiteDomainController')
      && $this->routeUsesController('admin.sites.variables.store', 'WebBlocks\\Cms\\Http\\Controllers\\Admin\\SiteVariableController')
      && $this->routeUsesController('admin.locales.index', 'WebBlocks\\Cms\\Http\\Controllers\\Admin\\LocaleController')
      && view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.sites.index')
      && view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.sites.form')
      && view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.sites.domains.index')
      && view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.locales.index');
  }

  protected function operationalAdminRuntimeUsesPackageAuthority(): bool
  {
    return $this->routeUsesController('admin.dashboard', 'WebBlocks\\Cms\\Http\\Controllers\\Admin\\DashboardController')
      && $this->routeUsesController('admin.contact-messages.index', 'WebBlocks\\Cms\\Http\\Controllers\\Admin\\ContactMessageController')
      && $this->routeUsesController('admin.reports.visitors.index', 'WebBlocks\\Cms\\Http\\Controllers\\Admin\\VisitorReportController')
      && $this->routeUsesController('admin.system.search.index', 'WebBlocks\\Cms\\Http\\Controllers\\Admin\\SystemSearchController')
      && $this->routeUsesController('admin.slot-types.index', 'WebBlocks\\Cms\\Http\\Controllers\\Admin\\SlotTypeController')
      && $this->routeUsesController('admin.system.settings.edit', 'WebBlocks\\Cms\\Http\\Controllers\\Admin\\SystemSettingsController')
      && view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.dashboard')
      && view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.contact-messages.index')
      && view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.reports.visitors.index')
      && view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.slot-types.index')
      && view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.search')
      && view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.settings');
  }

  protected function sharedAdminPartialsUsePackageAuthority(): bool
  {
    return $this->expectedViewsExist([
      WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.partials.page-header',
      WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.partials.flash',
      WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.partials.listing-filters',
      WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.partials.pagination',
      WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.partials.page-actions',
      WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.partials.audit-actor',
      WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::components.admin.form-actions',
    ]);
  }

  protected function yesNo(bool $value): string
  {
    return $value ? 'yes' : 'no';
  }

  protected function resourceStatus(array $files): string
  {
    if ($files === []) {
      return 'reserved only';
    }

    return 'package files present ('.implode(', ', $files).')';
  }

  protected function resourceBoundaryStatus(array $files): string
  {
    if ($files === []) {
      return 'reserved boundary only';
    }

    return 'pilot files present';
  }

  protected function viewNamespaceIsRegistered(): bool
  {
    return array_key_exists(
      WebBlocksCmsServiceProvider::VIEW_NAMESPACE,
      view()->getFinder()->getHints()
    );
  }

  /**
     * @param  array<int, string>  $expectedFiles
     */
  protected function expectedFilesStatus(string $basePath, array $expectedFiles): string
  {
    $missingFiles = $this->missingExpectedFiles($basePath, $expectedFiles);

    if ($missingFiles !== []) {
      return 'no (missing '.implode(', ', $missingFiles).')';
    }

    return 'yes ('.implode(', ', array_map(
      fn (string $file): string => pathinfo($file, PATHINFO_FILENAME),
      $expectedFiles
    )).')';
  }

  /**
     * @param  array<int, string>  $expectedFiles
     */
  protected function rootCompatibilityFilesStatus(string $basePath, array $expectedFiles): string
  {
    $missingFiles = $this->missingExpectedFiles($basePath, $expectedFiles);

    if ($missingFiles !== []) {
      return 'no (missing '.implode(', ', $missingFiles).')';
    }

    return 'yes';
  }

  /**
     * @param  array<int, string>  $files
     */
  protected function directoryResourceStatus(array $files, string $label): string
  {
    if ($files === []) {
      return 'no';
    }

    return 'yes ('.count($files).' '.$label.')';
  }

  /**
     * @param  array<int, string>  $expectedFiles
     * @param  array<int, string>  $actualFiles
     */
  protected function matchingResourceFilesStatus(array $expectedFiles, array $actualFiles, string $label): string
  {
    $missingFiles = array_values(array_diff($expectedFiles, $actualFiles));

    if ($missingFiles !== []) {
      return 'no (missing '.implode(', ', $missingFiles).')';
    }

    return 'yes ('.count($expectedFiles).' '.$label.')';
  }

  /**
     * @param  array<int, string>  $views
     */
  protected function expectedViewsExist(array $views): bool
  {
    foreach ($views as $view) {
      if (! view()->exists($view)) {
        return false;
      }
    }

    return true;
  }

  protected function composerSeederAutoloadPresent(array $composer): bool
  {
    return ($composer['autoload']['psr-4']['WebBlocks\\Cms\\Database\\Seeders\\'] ?? null) === 'database/seeders/';
  }

  protected function composerPackageNamePresent(array $composer, string $packageName): bool
  {
    return ($composer['name'] ?? null) === $packageName;
  }

  protected function composerProviderDiscoveryPresent(array $composer, string $providerClass): bool
  {
    return in_array($providerClass, $composer['extra']['laravel']['providers'] ?? [], true);
  }

  protected function composerPackageTypePresent(array $composer, string $type): bool
  {
    return ($composer['type'] ?? null) === $type;
  }

  protected function composerAutoloadPresent(array $composer, string $namespace, string $path): bool
  {
    return ($composer['autoload']['psr-4'][$namespace] ?? null) === $path;
  }

  /**
     * @param  array<int, string>  $expectedFiles
     * @return array<int, string>
     */
  protected function missingExpectedFiles(string $basePath, array $expectedFiles): array
  {
    return array_values(array_filter(
      $expectedFiles,
      fn (string $file): bool => ! is_file($basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file))
    ));
  }

  /**
     * @param  array<int, string>  $expectedFiles
     */
  protected function expectedFilesPresent(string $basePath, array $expectedFiles): bool
  {
    return $this->missingExpectedFiles($basePath, $expectedFiles) === [];
  }

  protected function composerJson(string $path): array
  {
    if (! is_file($path)) {
      return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : [];
  }

  protected function packagePublicLayoutUsesRootCompatibilityAssets(string $packageRoot): bool
  {
    $layoutPath = $packageRoot.'/resources/views/layouts/public.blade.php';

    if (! is_file($layoutPath)) {
      return false;
    }

    $contents = (string) file_get_contents($layoutPath);

    return str_contains($contents, "asset('cms/css/public.css')")
      && str_contains($contents, "asset('cms/js/public/public-search-modal.js')")
      && str_contains($contents, "asset('cms/js/public/sidebar-navigation.js')")
      && str_contains($contents, "public_path('cms/css/public.css')")
      && str_contains($contents, "public_path('cms/js/public/public-search-modal.js')")
      && str_contains($contents, "public_path('cms/js/public/sidebar-navigation.js')");
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
     * @return array<int, string>
     */
  protected function resourceFiles(string $path): array
  {
    if (! is_dir($path)) {
      return [];
    }

    $files = [];

    foreach ($this->packageFiles($path) as $file) {
      $files[] = ltrim(str_replace($path, '', $file->getPathname()), DIRECTORY_SEPARATOR);
    }

    sort($files);

    return $files;
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
