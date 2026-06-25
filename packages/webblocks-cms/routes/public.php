<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\AdminApi\SiteDomainApiController;
use WebBlocks\Cms\Http\Controllers\Public\ContactMessageController;
use WebBlocks\Cms\Http\Controllers\Public\PackagePublicStatusController;
use WebBlocks\Cms\Http\Controllers\Public\PageController;
use WebBlocks\Cms\Http\Controllers\Public\PublicPrivacyConsentController;
use WebBlocks\Cms\Http\Controllers\Public\PublicSearchController;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Support\Pages\PagePath;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

if (config(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_STATUS_ROUTE_LOADING_CONFIG, false)) {
  Route::middleware(['web', 'install.required'])
    ->get(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_PATH, PackagePublicStatusController::class)
    ->name(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_NAME);
}

Route::middleware(['web', 'install.required'])->get('/', [PageController::class, 'home'])->name('home');

Route::middleware(['web', 'install.required'])->get('/{locale}', [PageController::class, 'home'])
  ->where('locale', Locale::routePattern())
  ->name('localized.home');

Route::middleware(['web', 'install.required'])->get('/search', PublicSearchController::class)->name('search');
Route::middleware(['web', 'install.required'])->get('/search.json', [PublicSearchController::class, 'json'])->name('search.json');
Route::middleware(['web', 'install.required'])->get('/{locale}/search', PublicSearchController::class)
  ->where('locale', Locale::routePattern())
  ->name('localized.search');
Route::middleware(['web', 'install.required'])->get('/{locale}/search.json', [PublicSearchController::class, 'json'])
  ->where('locale', Locale::routePattern())
  ->name('localized.search.json');

Route::middleware(['web', 'install.required', 'internal-api.token'])->prefix('admin-api')->name('admin-api.')->group(function () {
  Route::get('/sites', [SiteDomainApiController::class, 'indexSites'])->name('sites.index');
  Route::get('/sites/{site}/domains', [SiteDomainApiController::class, 'indexDomains'])->name('sites.domains.index');
  Route::post('/sites/{site}/domains', [SiteDomainApiController::class, 'storeDomain'])->name('sites.domains.store');
  Route::delete('/sites/{site}/domains/{domain}', [SiteDomainApiController::class, 'destroyDomain'])->name('sites.domains.destroy');
  Route::get('/domains/{domain}/status', [SiteDomainApiController::class, 'domainStatus'])->name('domains.status');
});

Route::middleware(['web', 'install.required'])->post('/contact-messages', [ContactMessageController::class, 'store'])
  ->middleware('throttle:contact-form-submissions')
  ->name('contact-messages.store');

Route::middleware(['web', 'install.required'])->prefix('privacy-consent')->name('public.privacy-consent.')->group(function () {
  Route::post('/sync', [PublicPrivacyConsentController::class, 'sync'])->name('sync');
});

Route::middleware(['web', 'install.required'])->get('/p/{path}', [PageController::class, 'legacy'])
  ->where('path', '.*')
  ->name('pages.legacy');
Route::middleware(['web', 'install.required'])->get('/{locale}/p/{path}', [PageController::class, 'legacy'])
  ->where('locale', Locale::routePattern())
  ->where('path', '.*')
  ->name('localized.pages.legacy');

Route::middleware(['web', 'install.required'])->get('/{locale}/{slug}', [PageController::class, 'show'])
  ->where('locale', Locale::routePattern())
  ->where('slug', PagePath::routePattern())
  ->name('localized.pages.show');
Route::middleware(['web', 'install.required'])->get('/{slug}', [PageController::class, 'show'])
  ->where('slug', PagePath::routePattern())
  ->name('pages.show');
