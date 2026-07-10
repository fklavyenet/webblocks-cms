<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers\Public;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\CheckoutUnavailableException;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\StartCheckout;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways\CommerceGatewayManager;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\I18n\ProductLocalizer;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Tax\TaxCalculator;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\WebBlocksCommerceSchema;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class CommerceCheckoutController extends Controller
{
  public function __construct(
    private readonly WebBlocksCommerceSchema $schema,
    private readonly CommerceGatewayManager $gateways,
    private readonly StartCheckout $checkout,
    private readonly TaxCalculator $tax,
    private readonly ProductLocalizer $localizer,
  ) {}

  public function buy(Request $request, string $product): View
  {
    $product = $this->publicProduct($product);
    $localized = $this->localizer->localize($product, $request->query('locale'));

    return view($this->view('buy'), [
      'title' => 'Buy '.$localized['title'],
      'product' => $product,
      'displayTitle' => $localized['title'],
      'displayDescription' => $localized['description'],
      'site' => $product->site,
      'taxLine' => $this->tax->calculate($product->price_amount, $product->taxClass()),
      'checkoutReady' => $product->isAvailableForCheckout() && $this->gateways->supportsCheckout(),
      'checkoutUnavailableMessage' => $this->checkoutUnavailableMessage($product),
    ]);
  }

  public function checkout(Request $request, string $product): RedirectResponse
  {
    $product = $this->publicProduct($product);

    try {
      $session = $this->checkout->forProduct($product);
    } catch (CheckoutUnavailableException $exception) {
      return redirect()
        ->route('webblocks.commerce.products.buy', $product->slug)
        ->withErrors(['checkout' => $exception->getMessage()]);
    }

    return redirect()->away($session->redirectUrl);
  }

  public function success(Request $request, string $order): View
  {
    $order = $this->publicOrder($order);
    $checkoutId = $request->query('checkout_id');

    if ($checkoutId !== null && $checkoutId !== $order->gateway_checkout_id) {
      abort(404);
    }

    return $this->statusView($order, 'Payment Processing', 'The hosted checkout returned successfully. The order stays pending until a signed gateway webhook confirms payment.');
  }

  public function cancel(Request $request, string $order): View
  {
    $order = $this->publicOrder($order);

    return $this->statusView($order, 'Checkout Cancelled', 'No payment was recorded. You can return to the product and start checkout again.');
  }

  private function publicProduct(string $slug): CommerceProduct
  {
    abort_unless($this->schema->isReady(), 404);

    return CommerceProduct::query()
      ->where('slug', $slug)
      ->where('status', CommerceProduct::STATUS_ACTIVE)
      ->with('site')
      ->firstOrFail();
  }

  private function publicOrder(string $order): CommerceOrder
  {
    abort_unless($this->schema->isReady(), 404);

    return CommerceOrder::query()
      ->with(['items.product', 'site'])
      ->findOrFail($order);
  }

  private function checkoutUnavailableMessage(CommerceProduct $product): ?string
  {
    if (! $product->isAvailableForCheckout()) {
      return 'This product is not available for checkout.';
    }

    if (! $this->gateways->supportsCheckout()) {
      return $this->gateways->unavailableMessage();
    }

    return null;
  }

  private function statusView(CommerceOrder $order, string $heading, string $message): View
  {
    return view($this->view('checkout-status'), [
      'title' => $heading,
      'heading' => $heading,
      'message' => $message,
      'order' => $order,
      'site' => $order->site,
    ]);
  }

  private function view(string $name): string
  {
    return WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::plugins.webblocks-commerce.public.'.$name;
  }
}
