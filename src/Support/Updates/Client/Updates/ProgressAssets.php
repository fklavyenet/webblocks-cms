<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Updates;

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Support\Updates\Client\Http\Controllers\ProgressAssetController;
use WebBlocks\Cms\Support\Updates\Client\PublisherClient;

final class ProgressAssets
{
    public static function url(): string { return url('/publisher-client/assets/'.PublisherClient::VERSION.'/progress'); }
    public static function register(): void
    {
        $name = 'publisher-client.progress-asset.'.PublisherClient::VERSION;
        if (!Route::has($name)) { Route::get('/publisher-client/assets/'.PublisherClient::VERSION.'/progress', ProgressAssetController::class)->name($name); }
    }
}
