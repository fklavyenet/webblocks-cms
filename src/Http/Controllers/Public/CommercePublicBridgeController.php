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

  public function cart(Request $request): Response|View
  {
    return $this->dispatch('show', [$request], 'cart');
  }

  public function cartAdd(Request $request, string $product): RedirectResponse
  {
    return $this->dispatch('add', [$request, $product], 'cart');
  }

  public function cartUpdate(Request $request, string $product): RedirectResponse
  {
    return $this->dispatch('update', [$request, $product], 'cart');
  }

  public function cartRemove(Request $request, string $product): RedirectResponse
  {
    return $this->dispatch('remove', [$request, $product], 'cart');
  }

  public function cartCheckout(Request $request): RedirectResponse
  {
    return $this->dispatch('checkout', [$request], 'cart');
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

  public function sumUpWebhook(Request $request): JsonResponse
  {
    return $this->dispatch('sumUpWebhook', [$request]);
  }

  /**
   * @param  array<int, mixed>  $parameters
   */
  private function dispatch(string $method, array $parameters, ?string $surface = null): mixed
  {
    abort_unless($this->plugins->isEnabled('webblocks-commerce'), 404);

    $webhookMethods = ['paypalWebhook', 'sumUpWebhook'];
    $controller = match (true) {
      in_array($method, $webhookMethods, true) => 'WebBlocks\\Cms\\Plugins\\WebBlocksCommerce\\Http\\Controllers\\Public\\CommerceWebhookController',
      $surface === 'cart' => 'WebBlocks\\Cms\\Plugins\\WebBlocksCommerce\\Http\\Controllers\\Public\\CommerceCartController',
      default => 'WebBlocks\\Cms\\Plugins\\WebBlocksCommerce\\Http\\Controllers\\Public\\CommerceCheckoutController',
    };
    abort_unless(class_exists($controller), 404);

    $dispatchMethod = match ($method) {
      'paypalWebhook' => 'paypal',
      'sumUpWebhook' => 'sumup',
      default => $method,
    };

    return app($controller)->{$dispatchMethod}(...$parameters);
  }
}
