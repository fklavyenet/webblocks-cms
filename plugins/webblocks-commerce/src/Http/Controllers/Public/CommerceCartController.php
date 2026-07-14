<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers\Public;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceCart;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Cart\CartException;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Cart\CartService;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\CheckoutUnavailableException;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\StartCheckout;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways\CommerceGatewayManager;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Tax\TaxCalculator;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\WebBlocksCommerceSchema;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class CommerceCartController extends Controller
{
  private const SESSION_KEY = 'webblocks_commerce_cart_token';

  public function __construct(
    private readonly WebBlocksCommerceSchema $schema,
    private readonly CartService $carts,
    private readonly StartCheckout $checkout,
    private readonly CommerceGatewayManager $gateways,
    private readonly TaxCalculator $tax,
    private readonly LocaleResolver $locales,
    private readonly CmsTranslator $translator,
  ) {}

  public function show(Request $request): View
  {
    abort_unless($this->schema->isReady(), 404);

    $cart = $this->currentCart($request);
    $summary = $cart !== null ? $this->carts->summary($cart) : $this->emptySummary();
    $localeCode = $cart?->locale ?: $this->locales->current($request, $cart?->site)->code;

    return view($this->view('cart'), [
      'title' => $this->translator->plugin(
        'webblocks-commerce',
        'public.cart.title',
        $localeCode,
        fallback: 'Shopping Cart',
      ),
      'cart' => $cart,
      'summary' => $summary,
      'site' => $cart?->site,
      'publicLocaleCode' => $localeCode,
      'checkoutReady' => $summary['items'] !== []
        && collect($summary['items'])->every(fn (array $line): bool => (bool) ($line['available'] ?? false))
        && $this->gateways->supportsCheckout()
        && is_string($summary['currency'])
        && $this->gateways->supportsCurrency($summary['currency']),
      'checkoutUnavailableMessage' => $this->checkoutUnavailableMessage($summary['currency']),
      'testOrderMode' => $this->gateways->gatewayKey() === 'fake',
      'defaultShippingCountry' => $this->tax->storeCountry() ?? 'DE',
    ]);
  }

  public function add(Request $request, string $product): RedirectResponse
  {
    abort_unless($this->schema->isReady(), 404);

    $validator = Validator::make($request->all(), [
      'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
    ]);

    if ($validator->fails()) {
      return redirect()->route('webblocks.commerce.cart.show')->withErrors($validator);
    }

    $productModel = $this->publicProduct($product);
    $cart = $this->currentCart($request) ?? $this->newCart($request, $productModel);

    try {
      if ($cart->site_id !== null && $productModel->site_id !== null && $cart->site_id !== $productModel->site_id) {
        throw new CartException('Products from different sites cannot share one cart.');
      }

      $this->carts->addProduct($cart, $productModel, (int) $request->input('quantity', 1));
    } catch (CartException $exception) {
      return redirect()->route('webblocks.commerce.cart.show')->withErrors(['cart' => $exception->getMessage()]);
    }

    return redirect()
      ->route('webblocks.commerce.cart.show')
      ->with('commerce_success', $productModel->title.' was added to your cart.');
  }

  public function update(Request $request, string $product): RedirectResponse
  {
    abort_unless($this->schema->isReady(), 404);

    $validator = Validator::make($request->all(), [
      'quantity' => ['required', 'integer', 'min:0', 'max:99'],
    ]);

    if ($validator->fails()) {
      return redirect()->route('webblocks.commerce.cart.show')->withErrors($validator);
    }

    $cart = $this->currentCart($request);

    if ($cart === null) {
      return redirect()->route('webblocks.commerce.cart.show');
    }

    try {
      $quantity = (int) $request->input('quantity');
      $productModel = $quantity === 0 ? $this->product($product) : $this->publicProduct($product);
      $this->carts->setQuantity($cart, $productModel, $quantity);
    } catch (CartException $exception) {
      return redirect()->route('webblocks.commerce.cart.show')->withErrors(['cart' => $exception->getMessage()]);
    }

    return redirect()->route('webblocks.commerce.cart.show')->with('commerce_success', 'Cart updated.');
  }

  public function remove(Request $request, string $product): RedirectResponse
  {
    abort_unless($this->schema->isReady(), 404);

    $cart = $this->currentCart($request);

    if ($cart !== null) {
      $this->carts->removeProduct($cart, $this->product($product));
    }

    return redirect()->route('webblocks.commerce.cart.show')->with('commerce_success', 'Product removed.');
  }

  public function checkout(Request $request): RedirectResponse
  {
    abort_unless($this->schema->isReady(), 404);

    $validator = Validator::make($request->all(), [
      'customer_name' => ['required', 'string', 'max:160'],
      'customer_email' => ['required', 'email', 'max:254'],
      'customer_phone' => ['nullable', 'string', 'max:40'],
      'shipping_address_line_1' => ['required', 'string', 'max:255'],
      'shipping_address_line_2' => ['nullable', 'string', 'max:255'],
      'shipping_postal_code' => ['required', 'string', 'max:32'],
      'shipping_city' => ['required', 'string', 'max:120'],
      'shipping_country_code' => ['required', 'alpha', 'size:2'],
    ]);

    if ($validator->fails()) {
      return redirect()->route('webblocks.commerce.cart.show')->withErrors($validator)->withInput();
    }

    $cart = $this->currentCart($request);

    if ($cart === null) {
      return redirect()->route('webblocks.commerce.cart.show')->withErrors(['checkout' => 'Your cart is empty.']);
    }

    $validated = $validator->validated();
    $metadata = $cart->metadata ?? [];
    $metadata['customer'] = [
      'name' => (string) $validated['customer_name'],
      'email' => (string) $validated['customer_email'],
      'phone' => $this->nullableString($validated['customer_phone'] ?? null),
    ];
    $metadata['shipping_address'] = [
      'line_1' => (string) $validated['shipping_address_line_1'],
      'line_2' => $this->nullableString($validated['shipping_address_line_2'] ?? null),
      'postal_code' => (string) $validated['shipping_postal_code'],
      'city' => (string) $validated['shipping_city'],
      'country_code' => strtoupper((string) $validated['shipping_country_code']),
    ];

    $cart->update([
      'customer_email' => (string) $validated['customer_email'],
      'metadata' => $metadata,
    ]);

    try {
      $session = $this->checkout->forCart($cart);
    } catch (CheckoutUnavailableException $exception) {
      return redirect()->route('webblocks.commerce.cart.show')->withErrors(['checkout' => $exception->getMessage()]);
    }

    $request->session()->forget(self::SESSION_KEY);

    return redirect()->away($session->redirectUrl);
  }

  private function currentCart(Request $request): ?CommerceCart
  {
    $token = $request->session()->get(self::SESSION_KEY);

    if (! is_string($token) || trim($token) === '') {
      return null;
    }

    $cart = $this->carts->findOpenByToken($token);

    if ($cart === null) {
      $request->session()->forget(self::SESSION_KEY);

      return null;
    }

    return $cart->loadMissing('site');
  }

  private function newCart(Request $request, CommerceProduct $product): CommerceCart
  {
    $requestedLocale = $request->input('locale');
    $locale = is_string($requestedLocale)
      ? $this->locales->enabled($requestedLocale, $product->site)
      : null;

    $cart = $this->carts->create(
      $product->site_id,
      $locale?->code ?? $this->locales->current($request, $product->site)->code,
      $product->currency,
    );
    $request->session()->put(self::SESSION_KEY, $cart->token);

    return $cart->loadMissing('site');
  }

  private function publicProduct(string $product): CommerceProduct
  {
    return CommerceProduct::query()
      ->whereKey($product)
      ->where('status', CommerceProduct::STATUS_ACTIVE)
      ->with('site')
      ->firstOrFail();
  }

  private function product(string $product): CommerceProduct
  {
    return CommerceProduct::query()->whereKey($product)->firstOrFail();
  }

  /**
   * @return array<string, mixed>
   */
  private function emptySummary(): array
  {
    return [
      'currency' => null,
      'prices_include_tax' => true,
      'items' => [],
      'subtotal_amount' => 0,
      'tax_amount' => 0,
      'total_amount' => 0,
    ];
  }

  private function checkoutUnavailableMessage(mixed $currency): ?string
  {
    if (! $this->gateways->supportsCheckout()) {
      return $this->gateways->unavailableMessage();
    }

    if (is_string($currency) && ! $this->gateways->supportsCurrency($currency)) {
      return $this->gateways->currencyUnavailableMessage($currency);
    }

    return null;
  }

  private function view(string $name): string
  {
    return WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::plugins.webblocks-commerce.public.'.$name;
  }

  private function nullableString(mixed $value): ?string
  {
    if (! is_string($value)) {
      return null;
    }

    $value = trim($value);

    return $value === '' ? null : $value;
  }
}
