<?php

namespace WebBlocks\Cms\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as BaseAuthenticate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class UseCmsAuthenticationRedirect extends BaseAuthenticate
{
  protected function redirectTo(Request $request): ?string
  {
    if ($request->expectsJson()) {
      return null;
    }

    return Route::has('webblocks.auth.login')
      ? route('webblocks.auth.login')
      : '/webadmin/login';
  }
}
