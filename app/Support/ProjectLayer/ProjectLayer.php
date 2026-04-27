<?php

namespace App\Support\ProjectLayer;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;

class ProjectLayer
{
    public function __construct(
        private readonly Application $app,
    ) {}

    public function exists(): bool
    {
        return is_dir($this->basePath());
    }

    public function loadConfig(): void
    {
        if (! $this->exists() || ! is_dir($this->configPath())) {
            return;
        }

        $files = glob($this->configPath('*.php')) ?: [];
        sort($files);

        foreach ($files as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);
            $loaded = require $file;

            if (! is_array($loaded)) {
                $this->reportWarning(sprintf('Project config file [%s] must return an array.', $file));

                continue;
            }

            $this->app['config']->set(
                'project.'.$key,
                array_replace_recursive((array) $this->app['config']->get('project.'.$key, []), $loaded),
            );
        }
    }

    public function registerConfiguredProviders(): void
    {
        foreach ($this->configuredProviders() as $providerClass) {
            if (! is_string($providerClass) || trim($providerClass) === '') {
                continue;
            }

            $this->loadProviderClassFromProjectPath($providerClass);

            if (! class_exists($providerClass)) {
                $message = sprintf('Project provider [%s] could not be loaded.', $providerClass);

                if ((bool) $this->app['config']->get('app.debug')) {
                    throw new RuntimeException($message);
                }

                $this->reportWarning($message, ['provider' => $providerClass]);

                continue;
            }

            $this->app->register($providerClass);
        }
    }

    public function loadWebRoutes(): void
    {
        $path = $this->routesPath('web.php');

        if (! $this->shouldLoadRoutes($path)) {
            return;
        }

        Route::middleware('web')->group($path);
    }

    public function loadApiRoutes(): void
    {
        $path = $this->routesPath('api.php');

        if (! $this->shouldLoadRoutes($path)) {
            return;
        }

        Route::middleware('api')->prefix('api')->group($path);
    }

    public function loadConsoleRoutes(): void
    {
        $path = $this->routesPath('console.php');

        if (! $this->exists() || ! is_file($path) || ! $this->app->runningInConsole()) {
            return;
        }

        require $path;
    }

    public function viewsPath(): string
    {
        return $this->basePath('resources/views');
    }

    public function hasViews(): bool
    {
        return $this->exists() && is_dir($this->viewsPath());
    }

    public function basePath(string $path = ''): string
    {
        return base_path('project'.($path !== '' ? '/'.$path : ''));
    }

    public function configPath(string $path = ''): string
    {
        return $this->basePath('config'.($path !== '' ? '/'.$path : ''));
    }

    public function routesPath(string $path = ''): string
    {
        return $this->basePath('Routes'.($path !== '' ? '/'.$path : ''));
    }

    private function configuredProviders(): array
    {
        $providers = $this->app['config']->get('project.providers', []);

        return is_array($providers) ? $providers : [];
    }

    private function shouldLoadRoutes(string $path): bool
    {
        return $this->exists()
            && is_file($path)
            && ! $this->app->routesAreCached();
    }

    private function reportWarning(string $message, array $context = []): void
    {
        Log::warning($message, $context);
    }

    private function loadProviderClassFromProjectPath(string $providerClass): void
    {
        if (class_exists($providerClass) || ! str_starts_with($providerClass, 'Project\\')) {
            return;
        }

        $relativePath = str_replace('\\', '/', substr($providerClass, strlen('Project\\'))).'.php';
        $path = $this->basePath($relativePath);

        if (is_file($path)) {
            require_once $path;
        }
    }
}
