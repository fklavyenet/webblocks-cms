<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client;

use Illuminate\Support\ServiceProvider;
use WebBlocks\Cms\Support\Updates\Client\Apply\FullRootApplyStrategy;
use WebBlocks\Cms\Support\Updates\Client\Apply\PackageApplyStrategy;
use WebBlocks\Cms\Support\Updates\Client\Backup\FilesystemBackupManager;
use WebBlocks\Cms\Support\Updates\Client\Contracts\ApplyStrategy;
use WebBlocks\Cms\Support\Updates\Client\Contracts\BackupManager;
use WebBlocks\Cms\Support\Updates\Client\Contracts\InstalledVersionStore;
use WebBlocks\Cms\Support\Updates\Client\Contracts\PostApplyRunner;
use WebBlocks\Cms\Support\Updates\Client\Contracts\RunRecorder;
use WebBlocks\Cms\Support\Updates\Client\Contracts\TelemetryProvider;
use WebBlocks\Cms\Support\Updates\Client\Persistence\NullInstalledVersionStore;
use WebBlocks\Cms\Support\Updates\Client\PostApply\MigratingPostApplyRunner;
use WebBlocks\Cms\Support\Updates\Client\Runs\FileRunRecorder;
use WebBlocks\Cms\Support\Updates\Client\Support\Version\ConfigVersionResolver;
use WebBlocks\Cms\Support\Updates\Client\Support\Version\VersionResolver;
use WebBlocks\Cms\Support\Updates\Client\Telemetry\FileInstallationTelemetry;
use WebBlocks\Cms\Support\Updates\Client\Telemetry\NullTelemetryProvider;
use WebBlocks\Cms\Support\Updates\Client\Updates\RunHeartbeat;

/**
 * Product-owned registration entry point for generated Client source copies.
 * The Composer package provider remains separate so embedded consumers do not
 * depend on package discovery or paths outside their own release artifact.
 */
final class EmbeddedRuntimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/defaults.php', 'publisher-client');
        $this->app['config']->set('publisher-client.distribution', 'embedded');

        $this->app->bind(VersionResolver::class, function ($app) {
            $configured = $app['config']->get('publisher-client.version.resolver', ConfigVersionResolver::class);

            return $app->make($configured);
        });

        $this->app->bind(TelemetryProvider::class, function ($app): TelemetryProvider {
            return (bool) $app['config']->get('publisher-client.telemetry.enabled', true)
                ? $app->make(FileInstallationTelemetry::class)
                : $app->make(NullTelemetryProvider::class);
        });

        $this->app->bind(ApplyStrategy::class, function ($app): ApplyStrategy {
            return match ((string) $app['config']->get('publisher-client.apply.strategy', 'package')) {
                'full-root' => $app->make(FullRootApplyStrategy::class),
                default => $app->make(PackageApplyStrategy::class),
            };
        });

        $this->app->bind(BackupManager::class, FilesystemBackupManager::class);
        $this->app->singleton(RunHeartbeat::class);
        $this->app->bind(RunRecorder::class, FileRunRecorder::class);
        $this->app->bind(InstalledVersionStore::class, NullInstalledVersionStore::class);
        $this->app->bind(PostApplyRunner::class, MigratingPostApplyRunner::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'publisher-client');
        $this->loadTranslationsFrom(__DIR__.'/Resources/lang', 'publisher-client');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\CheckCommand::class,
                Console\UpdateCommand::class,
                Console\PublishUpdateCommand::class,
                Console\PrepareUpdateCommand::class,
                Console\KeygenCommand::class,
                Console\RunsCommand::class,
            ]);
        }
    }
}
