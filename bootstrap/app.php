<?php

use App\Console\Commands\CmsReleaseCommand;
use App\Console\Commands\ImportDemoMedia;
use App\Console\Commands\ListSystemReleases;
use App\Console\Commands\PublishSystemRelease;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        ImportDemoMedia::class,
        CmsReleaseCommand::class,
        PublishSystemRelease::class,
        ListSystemReleases::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
