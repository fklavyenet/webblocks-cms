<?php

namespace App\Providers;

use App\Support\ProjectLayer\ProjectLayer;
use Illuminate\Support\ServiceProvider;

class ProjectLayerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProjectLayer::class);

        $projectLayer = $this->app->make(ProjectLayer::class);
        $projectLayer->loadConfig();
        $projectLayer->registerConfiguredProviders();
    }

    public function boot(): void
    {
        $projectLayer = $this->app->make(ProjectLayer::class);

        if ($projectLayer->hasViews()) {
            $this->loadViewsFrom($projectLayer->viewsPath(), 'project');
        }

        $projectLayer->loadWebRoutes();
        $projectLayer->loadApiRoutes();
        $projectLayer->loadConsoleRoutes();
    }
}
