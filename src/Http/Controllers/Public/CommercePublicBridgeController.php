<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

class CommercePublicBridgeController extends Controller
{
  public function __construct(
    private readonly PluginRegistry $plugins,
  ) {}

  public function buy(Request $request, string $product): Response|View
  {
    return $this->dispatch('buy', [$request, $product]);
  }

  public function checkout(Request $request, string $product): RedirectResponse
  {
    return $this->dispatch('checkout', [$request, $product]);
  }

  public function success(Request $request, string $order): Response|View
  {
    return $this->dispatch('success', [$request, $order]);
  }

  public function cancel(Request $request, string $order): Response|View
  {
    return $this->dispatch('cancel', [$request, $order]);
  }

  public function paypalWebhook(Request $request): JsonResponse
  {
    return $this->dispatch('paypalWebhook', [$request]);
  }

  /**
   * @param  array<int, mixed>  $parameters
   */
  private function dispatch(string $method, array $parameters): mixed
  {
    abort_unless($this->plugins->isEnabled('webblocks-commerce'), 404);

    $controller = $method === 'paypalWebhook'
      ? 'WebBlocks\\Cms\\Plugins\\WebBlocksCommerce\\Http\\Controllers\\Public\\CommerceWebhookController'
      : 'WebBlocks\\Cms\\Plugins\\WebBlocksCommerce\\Http\\Controllers\\Public\\CommerceCheckoutController';
    abort_unless(class_exists($controller), 404);

    $dispatchMethod = $method === 'paypalWebhook' ? 'paypal' : $method;

    return app($controller)->{$dispatchMethod}(...$parameters);
  }
}
