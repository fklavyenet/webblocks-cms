<?php

namespace App\Providers;

use App\Support\Database\DestructiveDatabaseCommandGuard;
use App\Support\Install\InstallState;
use App\Support\Locales\LocaleResolver;
use App\Support\Pages\PageLayoutManager;
use App\Support\Pages\PageRouteResolver;
use App\Support\PublicRendering\SiteAssetResolver;
use App\Support\SitePromotion\SitePromotionApplier;
use App\Support\Sites\SiteResolver;
use App\Support\System\BackupRestoreArchiveExtractor;
use App\Support\System\BackupRestoreArchiveInspector;
use App\Support\System\DatabaseDumpWriter;
use App\Support\System\DatabaseRestoreRunner;
use App\Support\System\InstalledVersionStore;
use App\Support\System\SystemBackupManager;
use App\Support\System\SystemBackupRestoreMaintenanceRunner;
use App\Support\System\SystemBackupRestoreManager;
use App\Support\System\SystemSettings;
use App\Support\System\UploadedSystemBackupManager;
use App\Support\Visitors\VisitorConsent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;
use WebBlocks\Cms\Support\SitePromotion\SitePromotionApplier as PackageSitePromotionApplier;
use WebBlocks\Cms\Support\System\BackupRestoreArchiveExtractor as PackageBackupRestoreArchiveExtractor;
use WebBlocks\Cms\Support\System\BackupRestoreArchiveInspector as PackageBackupRestoreArchiveInspector;
use WebBlocks\Cms\Support\System\DatabaseDumpWriter as PackageDatabaseDumpWriter;
use WebBlocks\Cms\Support\System\DatabaseRestoreRunner as PackageDatabaseRestoreRunner;
use WebBlocks\Cms\Support\System\SystemBackupManager as PackageSystemBackupManager;
use WebBlocks\Cms\Support\System\SystemBackupRestoreMaintenanceRunner as PackageSystemBackupRestoreMaintenanceRunner;
use WebBlocks\Cms\Support\System\SystemBackupRestoreManager as PackageSystemBackupRestoreManager;
use WebBlocks\Cms\Support\System\UploadedSystemBackupManager as PackageUploadedSystemBackupManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerInstallRuntimeFallbacks();
        $this->registerPackageCompatibilityAliases();

        $this->app->singleton(SiteResolver::class);
        $this->app->singleton(LocaleResolver::class);
        $this->app->singleton(PageLayoutManager::class);
        $this->app->singleton(PageRouteResolver::class);
        $this->app->singleton(SiteAssetResolver::class);
        $this->app->singleton(SystemSettings::class);
        $this->app->singleton(VisitorConsent::class);
        $this->app->singleton(DestructiveDatabaseCommandGuard::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            $systemSettings = app(SystemSettings::class);

            Config::set('app.locale', $systemSettings->defaultLocaleCode());
            Config::set('app.fallback_locale', $systemSettings->defaultLocaleCode());
            Config::set('app.timezone', $systemSettings->timezone());
            date_default_timezone_set((string) config('app.timezone', 'UTC'));
        } catch (Throwable) {
            // Keep config fallbacks when the database is unavailable during bootstrap.
        }

        // Public navigation now renders explicitly through Navigation Auto blocks.

        View::composer(['layouts.public', 'pages.show', 'search.show', 'webblocks-cms::layouts.public', 'webblocks-cms::pages.show', 'webblocks-cms::search.show'], function ($view): void {
            $request = request();
            $consent = app(VisitorConsent::class);
            $resolvedPublicSite = null;

            try {
                $resolvedPublicSite = app(PageRouteResolver::class)->resolvedSite($request)->site;
            } catch (Throwable) {
                // Keep public fallbacks safe when no site can be resolved.
            }

            $view->with('visitorPrivacy', [
                'banner_enabled' => $consent->bannerEnabled(),
                'has_choice' => $consent->hasStoredChoice($request),
                'server_choice' => $consent->storedChoice($request),
            ]);
            $view->with('resolvedPublicSite', $resolvedPublicSite);
        });

        View::composer('layouts.admin', function ($view): void {
            $systemSettings = app(SystemSettings::class);

            $view->with('installedVersionDisplay', app(InstalledVersionStore::class)->displayVersion());
            $view->with('adminProjectIdentity', $systemSettings->adminProjectIdentity());
            $view->with('adminBrowserTitle', $systemSettings->adminBrowserTitle($view->getData()['title'] ?? null));
        });

        RateLimiter::for('contact-form-submissions', function (Request $request) {
            return Limit::perMinute((int) config('contact.rate_limit_per_minute', 5))
                ->by($request->ip().'|'.((string) $request->input('block_id')));
        });

        if ($this->app->runningInConsole()) {
            Event::listen(CommandStarting::class, function (CommandStarting $event): void {
                app(DestructiveDatabaseCommandGuard::class)->ensureAllowed($event->command);
            });
        }
    }

    private function registerInstallRuntimeFallbacks(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if (! app()->runningInConsole() && app(InstallState::class)->shouldUseRuntimeFallbacks()) {
            Config::set('session.driver', 'file');
            Config::set('cache.default', 'file');
            Config::set('queue.default', 'sync');
        }
    }

    private function registerPackageCompatibilityAliases(): void
    {
        $this->app->alias(DatabaseDumpWriter::class, PackageDatabaseDumpWriter::class);
        $this->app->alias(BackupRestoreArchiveInspector::class, PackageBackupRestoreArchiveInspector::class);
        $this->app->alias(BackupRestoreArchiveExtractor::class, PackageBackupRestoreArchiveExtractor::class);
        $this->app->alias(DatabaseRestoreRunner::class, PackageDatabaseRestoreRunner::class);
        $this->app->alias(SystemBackupManager::class, PackageSystemBackupManager::class);
        $this->app->alias(SystemBackupRestoreMaintenanceRunner::class, PackageSystemBackupRestoreMaintenanceRunner::class);
        $this->app->alias(SystemBackupRestoreManager::class, PackageSystemBackupRestoreManager::class);
        $this->app->alias(UploadedSystemBackupManager::class, PackageUploadedSystemBackupManager::class);
        $this->app->alias(SitePromotionApplier::class, PackageSitePromotionApplier::class);
    }
}
