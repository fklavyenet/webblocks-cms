<?php

namespace Project\Providers;

use Illuminate\Support\ServiceProvider;
use Project\Support\UiDocs\SyncUiDocsGettingStarted;
use Project\Support\UiDocs\SyncUiDocsHomeMain;
use Project\Support\UiDocs\SyncUiDocsNavigation;
use Project\Support\UiDocs\WebBlocksUiLocalResolver;

class ProjectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once base_path('project/Support/UiDocs/SyncUiDocsNavigation.php');
        require_once base_path('project/Support/UiDocs/SyncUiDocsGettingStarted.php');
        require_once base_path('project/Support/UiDocs/SyncUiDocsHomeMain.php');
        require_once base_path('project/Support/UiDocs/WebBlocksUiLocalResolver.php');

        $this->app->singleton(SyncUiDocsNavigation::class);
        $this->app->singleton(SyncUiDocsGettingStarted::class);
        $this->app->singleton(SyncUiDocsHomeMain::class);
        $this->app->singleton(WebBlocksUiLocalResolver::class);
    }
}
