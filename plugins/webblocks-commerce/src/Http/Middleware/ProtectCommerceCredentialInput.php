<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectCommerceCredentialInput
{
  public function __construct(
    private readonly ExceptionHandler $exceptions,
  ) {}

  public function handle(Request $request, Closure $next): Response
  {
    if (method_exists($this->exceptions, 'dontFlash')) {
      $this->exceptions->dontFlash([
        'paypal_client_id',
        'paypal_client_secret',
        'paypal_webhook_id',
        'sumup_api_key',
        'sumup_merchant_code',
      ]);
    }

    return $next($request);
  }
}
