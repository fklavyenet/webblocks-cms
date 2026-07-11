<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
      return response($this->pageController->show($request, $path));
    }

    return $next($request);
  }
}
