<?php

namespace App\Providers;

use App\Support\Locales\LocaleResolver;
use App\Support\Install\InstallState;
use App\Support\Pages\PageRouteResolver;
use App\Support\Database\DestructiveDatabaseCommandGuard;
use App\Support\Sites\SiteResolver;
use App\Support\System\InstalledVersionStore;
use App\Support\System\SystemSettings;
use App\Support\Visitors\VisitorConsent;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerInstallRuntimeFallbacks();

        $this->app->singleton(SiteResolver::class);
        $this->app->singleton(LocaleResolver::class);
        $this->app->singleton(PageRouteResolver::class);
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

            Config::set('app.name', $systemSettings->appName());
            Config::set('app.slogan', $systemSettings->appSlogan());
            Config::set('app.locale', $systemSettings->defaultLocaleCode());
            Config::set('app.fallback_locale', $systemSettings->defaultLocaleCode());
            Config::set('app.timezone', $systemSettings->timezone());
            date_default_timezone_set((string) config('app.timezone', 'UTC'));
        } catch (Throwable) {
            // Keep config fallbacks when the database is unavailable during bootstrap.
        }

        // Public navigation now renders explicitly through Navigation Auto blocks.

        View::composer(['layouts.public', 'pages.show'], function ($view): void {
            $request = request();
            $consent = app(VisitorConsent::class);

            $view->with('visitorPrivacy', [
                'banner_enabled' => $consent->bannerEnabled(),
                'has_choice' => $consent->hasStoredChoice($request),
                'server_choice' => $consent->storedChoice($request),
            ]);
        });

        View::composer('layouts.admin', function ($view): void {
            $view->with('installedVersionDisplay', app(InstalledVersionStore::class)->displayVersion());
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
}
