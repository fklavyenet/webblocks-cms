<?php

namespace App\Providers;

use App\Support\Locales\LocaleResolver;
use App\Support\Pages\PageRouteResolver;
use App\Support\Sites\SiteResolver;
use App\Support\System\InstalledVersionStore;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SiteResolver::class);
        $this->app->singleton(LocaleResolver::class);
        $this->app->singleton(PageRouteResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Public navigation now renders explicitly through Navigation Auto blocks.

        View::composer('layouts.admin', function ($view): void {
            $view->with('installedVersionDisplay', app(InstalledVersionStore::class)->displayVersion());
        });

        RateLimiter::for('contact-form-submissions', function (Request $request) {
            return Limit::perMinute((int) config('contact.rate_limit_per_minute', 5))
                ->by($request->ip().'|'.((string) $request->input('block_id')));
        });
    }
}
