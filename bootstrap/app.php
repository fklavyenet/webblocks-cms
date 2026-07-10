<?php

use App\Console\Commands\ProjectInitCommand;
use App\Http\Middleware\RedirectIfInstalled;
use App\Http\Middleware\RedirectIfNotInstalled;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use WebBlocks\Cms\Http\Middleware\RequireAdminAccess;
use WebBlocks\Cms\Http\Middleware\RequireInternalApiToken;

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
    ProjectInitCommand::class,
  ])
  ->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
      'admin.access' => RequireAdminAccess::class,
      'internal-api.token' => RequireInternalApiToken::class,
      'install.complete' => RedirectIfInstalled::class,
      'install.required' => RedirectIfNotInstalled::class,
    ]);

    // Opt-in reverse-proxy support: only trust forwarded headers when
    // TRUSTED_PROXIES is set (e.g. "*" behind Caddy/Nginx). Default is no
    // trust, so direct deployments are unaffected.
    $trustedProxies = trim((string) env('TRUSTED_PROXIES', ''));

    if ($trustedProxies !== '') {
      $middleware->trustProxies(
        at: $trustedProxies === '*' ? '*' : array_map('trim', explode(',', $trustedProxies)),
      );
    }
  })
  ->withExceptions(function (Exceptions $exceptions): void {
    //
  })->create();
