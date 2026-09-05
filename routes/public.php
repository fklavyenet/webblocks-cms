<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\AdminApi\SiteDomainApiController;
use WebBlocks\Cms\Http\Controllers\Public\CommentEntryController;
use WebBlocks\Cms\Http\Controllers\Public\ContactMessageController;
use WebBlocks\Cms\Http\Controllers\Public\ContentRatingController;
use WebBlocks\Cms\Http\Controllers\Public\EmbeddedApplicationEntryController;
use WebBlocks\Cms\Http\Controllers\Public\PackagePublicStatusController;
use WebBlocks\Cms\Http\Controllers\Public\PageController;
use WebBlocks\Cms\Http\Controllers\Public\PluginAssetController;
use WebBlocks\Cms\Http\Controllers\Public\PublicPrivacyConsentController;
use WebBlocks\Cms\Http\Controllers\Public\PublicSearchController;
use WebBlocks\Cms\Http\Middleware\AddCmsIdentificationHeader;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Support\Pages\PagePath;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

$publicPageMiddleware = ['web', 'install.required', AddCmsIdentificationHeader::class];

Route::middleware($publicPageMiddleware)
  ->get('/cms/plugins/{plugin}/{path}', PluginAssetController::class)
  ->where('plugin', '[a-z0-9][a-z0-9]*(?:-[a-z0-9]+)*')
  ->where('path', '.+')
  ->name('plugin-assets.show');

Route::middleware($publicPageMiddleware)
  ->get('/webblocks-applications/{application}/index.html', EmbeddedApplicationEntryController::class)
  ->where('application', '[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?')
  ->name('embedded-applications.entry');

if (config(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_STATUS_ROUTE_LOADING_CONFIG, false)) {
  Route::middleware($publicPageMiddleware)
    ->get(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_PATH, PackagePublicStatusController::class)
    ->name(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_NAME);
}

Route::middleware($publicPageMiddleware)->get('/', [PageController::class, 'home'])->name('home');

Route::middleware($publicPageMiddleware)->get('/{locale}', [PageController::class, 'home'])
  ->where('locale', Locale::routePattern())
  ->name('localized.home');

Route::middleware($publicPageMiddleware)->get('/search', PublicSearchController::class)->name('search');
Route::middleware($publicPageMiddleware)->get('/search.json', [PublicSearchController::class, 'json'])->name('search.json');
Route::middleware($publicPageMiddleware)->get('/{locale}/search', PublicSearchController::class)
  ->where('locale', Locale::routePattern())
  ->name('localized.search');
Route::middleware($publicPageMiddleware)->get('/{locale}/search.json', [PublicSearchController::class, 'json'])
  ->where('locale', Locale::routePattern())
  ->name('localized.search.json');

// Domain records decide which hostname resolves to which site, which is why
// the browser admin keeps them behind system-level access. These routes only
// ever checked that a token was valid, so any token at all could add or remove
// a domain. Reads ride on content.read; writes need their own capabilities.
$siteDomainRoutes = function (): void {
  Route::get('/sites/{site}/domains', [SiteDomainApiController::class, 'indexDomains'])->middleware('internal-api.capability:content.read')->name('sites.domains.index');
  Route::post('/sites/{site}/domains', [SiteDomainApiController::class, 'storeDomain'])->middleware('internal-api.capability:domains.write')->name('sites.domains.store');
  Route::put('/sites/{site}/domains/{domain}', [SiteDomainApiController::class, 'updateDomain'])->middleware('internal-api.capability:domains.write')->name('sites.domains.update');
  Route::post('/sites/{site}/domains/{domain}/primary', [SiteDomainApiController::class, 'setPrimaryDomain'])->middleware('internal-api.capability:domains.write')->name('sites.domains.primary');
  Route::delete('/sites/{site}/domains/{domain}', [SiteDomainApiController::class, 'destroyDomain'])->middleware('internal-api.capability:domains.delete')->name('sites.domains.destroy');
  Route::get('/domains/{domain}/status', [SiteDomainApiController::class, 'domainStatus'])->middleware('internal-api.capability:content.read')->name('domains.status');
};

// Canonical home, alongside every other Internal Content API endpoint. The CSRF
// exemption is path-based on webadmin/api/*, so token clients reach the writes
// here without the exemption gap the legacy prefix has.
Route::middleware(['web', 'install.required', 'throttle:internal-content-api', 'internal-api.token'])
  ->prefix('webadmin/api')
  ->name('internal-content-api.')
  ->group($siteDomainRoutes);

// Legacy prefix, kept working for existing provisioning tools.
Route::middleware(['web', 'install.required', 'throttle:internal-content-api', 'internal-api.token'])
  ->prefix('admin-api')
  ->name('admin-api.')
  ->group(function () use ($siteDomainRoutes): void {
    Route::get('/sites', [SiteDomainApiController::class, 'indexSites'])->middleware('internal-api.capability:content.read')->name('sites.index');
    $siteDomainRoutes();
  });

Route::middleware(['web', 'install.required'])->post('/contact-messages', [ContactMessageController::class, 'store'])
  ->middleware('throttle:contact-form-submissions')
  ->name('contact-messages.store');

Route::middleware(['web', 'install.required'])->post('/content-ratings', [ContentRatingController::class, 'store'])
  ->middleware('throttle:engagement-ratings')
  ->name('content-ratings.store');

Route::middleware(['web', 'install.required'])->post('/comment-entries', [CommentEntryController::class, 'store'])
  ->middleware('throttle:engagement-comments')
  ->name('comment-entries.store');

Route::middleware(['web', 'install.required'])->prefix('privacy-consent')->name('public.privacy-consent.')->group(function () {
  Route::post('/sync', [PublicPrivacyConsentController::class, 'sync'])->name('sync');
});

Route::middleware($publicPageMiddleware)->get('/p/{path}', [PageController::class, 'legacy'])
  ->where('path', '.*')
  ->name('pages.legacy');
Route::middleware($publicPageMiddleware)->get('/{locale}/p/{path}', [PageController::class, 'legacy'])
  ->where('locale', Locale::routePattern())
  ->where('path', '.*')
  ->name('localized.pages.legacy');

Route::middleware($publicPageMiddleware)->get('/{locale}/{slug}', [PageController::class, 'show'])
  ->where('locale', Locale::routePattern())
  ->where('slug', PagePath::routePattern())
  ->name('localized.pages.show');
Route::middleware($publicPageMiddleware)->get('/{slug}', [PageController::class, 'show'])
  ->where('slug', PagePath::routePattern())
  ->name('pages.show');
