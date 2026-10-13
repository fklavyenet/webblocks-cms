<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Http\Controllers\Public\PageController;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;

class ServeCmsPageBeforeRedirectCatchAll
{
  public function __construct(
    private readonly PageRouteResolver $routeResolver,
    private readonly PageController $pageController,
  ) {}

  public function handle(Request $request, Closure $next): Response
  {
    $path = (string) $request->route('webblocksRedirectManagerPath', '');

    if ($path !== '' && $this->routeResolver->findPublishedPageByPath($request, $path)) {
      return app(Pipeline::class)->send($request)
        ->through(app(Router::class)->resolveMiddleware(['throttle:cms-public-pages']))
        ->then(function (Request $request) use ($path): Response {
          $result = $this->pageController->show($request, $path);

          return $result instanceof Response ? $result : response($result);
        });
    }

    return $next($request);
  }
}
