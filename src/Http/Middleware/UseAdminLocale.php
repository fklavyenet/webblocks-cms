<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;

class UseAdminLocale
{
  public function __construct(
    private readonly AdminLocaleResolver $localeResolver,
  ) {}

  public function handle(Request $request, Closure $next): Response
  {
    app()->setLocale($this->localeResolver->locale($request->user()));

    return $next($request);
  }
}
