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
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class CommerceCheckoutController extends Controller
{
  public function __construct(
    private readonly WebBlocksCommerceSchema $schema,
    private readonly CommerceGatewayManager $gateways,
    private readonly StartCheckout $checkout,
    private readonly TaxCalculator $tax,
    private readonly ProductLocalizer $localizer,
    private readonly LocaleResolver $locales,
    private readonly CmsTranslator $translator,
  ) {}

  public function buy(Request $request, string $product): View
  {
    $product = $this->publicProduct($product);
    $requestedLocale = $request->query('locale');
    $locale = is_string($requestedLocale)
      ? $this->locales->enabled($requestedLocale, $product->site)
      : null;
    $localeCode = $locale?->code ?? $this->locales->current($request, $product->site)->code;
    $localized = $this->localizer->localize($product, $localeCode);

    return view($this->view('buy'), [
      'title' => 'Buy '.$localized['title'],
      'product' => $product,
      'displayTitle' => $localized['title'],
      'displayDescription' => $localized['description'],
      'site' => $product->site,
      'publicLocaleCode' => $localeCode,
      'taxLine' => $this->tax->calculate($product->price_amount, $product->taxClass()),
      'checkoutReady' => $product->isAvailableForCheckout()
        && $this->gateways->supportsCheckout()
        && $this->gateways->supportsCurrency($product->currency),
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

    if ($order->gateway === 'fake') {
      return $this->statusView(
        $request,
        $order,
        'status.test_order_heading',
        'status.test_order_message',
        'Test Order Received',
        'Your order and delivery details were saved. No payment was collected, and this test order remains pending.',
      );
    }

    return $this->statusView(
      $request,
      $order,
      'status.payment_processing_heading',
      'status.payment_processing_message',
      'Payment Processing',
      'The hosted checkout returned successfully. The order stays pending until a signed gateway webhook confirms payment.',
    );
  }

  public function cancel(Request $request, string $order): View
  {
    $order = $this->publicOrder($order);

    return $this->statusView(
      $request,
      $order,
      'status.checkout_cancelled_heading',
      'status.checkout_cancelled_message',
      'Checkout Cancelled',
      'No payment was recorded. You can return to the product and start checkout again.',
    );
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

    if (! $this->gateways->supportsCurrency($product->currency)) {
      return $this->gateways->currencyUnavailableMessage($product->currency);
    }

    return null;
  }

  private function statusView(
    Request $request,
    CommerceOrder $order,
    string $headingKey,
    string $messageKey,
    string $headingFallback,
    string $messageFallback,
  ): View {
    $storedLocale = $order->metadata['locale'] ?? null;
    $locale = is_string($storedLocale) ? $this->locales->enabled($storedLocale, $order->site) : null;
    $localeCode = $locale?->code ?? $this->locales->current($request, $order->site)->code;
    $heading = $this->translator->plugin('webblocks-commerce', 'public.'.$headingKey, $localeCode, fallback: $headingFallback);
    $message = $this->translator->plugin('webblocks-commerce', 'public.'.$messageKey, $localeCode, fallback: $messageFallback);

    return view($this->view('checkout-status'), [
      'title' => $heading,
      'heading' => $heading,
      'message' => $message,
      'order' => $order,
      'site' => $order->site,
      'publicLocaleCode' => $localeCode,
    ]);
  }

  private function view(string $name): string
  {
    return WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::plugins.webblocks-commerce.public.'.$name;
  }
}
