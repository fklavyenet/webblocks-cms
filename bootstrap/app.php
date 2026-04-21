<?php

use App\Console\Commands\ImportDemoMedia;
use App\Console\Commands\SiteCloneCommand;
use App\Console\Commands\SystemBackupRestoreCommand;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

foreach ([
    dirname(__DIR__).'/storage/framework/cache/data',
    dirname(__DIR__).'/storage/framework/sessions',
    dirname(__DIR__).'/storage/framework/views',
    dirname(__DIR__).'/storage/framework/testing',
    dirname(__DIR__).'/storage/logs',
    __DIR__.'/cache',
] as $directory) {
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
}

foreach ([
    dirname(__DIR__).'/storage/framework/cache/.gitignore' => "*\n!.gitignore\n",
    dirname(__DIR__).'/storage/framework/cache/data/.gitignore' => "*\n!.gitignore\n",
    dirname(__DIR__).'/storage/framework/sessions/.gitignore' => "*\n!.gitignore\n",
    dirname(__DIR__).'/storage/framework/views/.gitignore' => "*\n!.gitignore\n",
    dirname(__DIR__).'/storage/framework/testing/.gitignore' => "*\n!.gitignore\n",
    dirname(__DIR__).'/storage/logs/.gitignore' => "*\n!.gitignore\n",
    __DIR__.'/cache/.gitignore' => "*\n!.gitignore\n",
] as $path => $contents) {
    if (! is_file($path)) {
        file_put_contents($path, $contents);
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        ImportDemoMedia::class,
        SiteCloneCommand::class,
        SystemBackupRestoreCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
