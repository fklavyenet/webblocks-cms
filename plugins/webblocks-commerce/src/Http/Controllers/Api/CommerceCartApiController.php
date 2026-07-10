<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceCart;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Cart\CartException;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Cart\CartService;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\CheckoutUnavailableException;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\StartCheckout;

/**
 * Plugin-owned, AI-first cart API. Every cart action a human can perform is
 * available here under token auth + the commerce.cart.* capabilities, mounted
 * into the CMS internal API group via the plugin's apiRoutes() hook.
 */
class CommerceCartApiController extends Controller
{
  public function __construct(
    private readonly CartService $carts,
    private readonly StartCheckout $checkout,
  ) {}

  public function store(Request $request): JsonResponse
  {
    if ($unavailable = $this->unavailable()) {
      return $unavailable;
    }

    $validator = Validator::make($request->all(), [
      'site_id' => ['nullable', 'integer'],
      'locale' => ['nullable', 'string', 'max:10'],
      'currency' => ['nullable', 'string', 'size:3'],
    ]);

    if ($validator->fails()) {
      return $this->validationErrors($validator->errors()->toArray());
    }

    $cart = $this->carts->create(
      $request->filled('site_id') ? (int) $request->input('site_id') : null,
      $request->input('locale'),
      $request->input('currency'),
    );

    return $this->ok(['cart' => $this->carts->summary($cart)], 201);
  }

  public function show(string $cart): JsonResponse
  {
    if ($unavailable = $this->unavailable()) {
      return $unavailable;
    }

    $model = $this->carts->findOpenByToken($cart);

    if ($model === null) {
      return $this->apiError('commerce_cart_not_found', 'The requested cart was not found or is no longer open.', 404);
    }

    return $this->ok(['cart' => $this->carts->summary($model)]);
  }

  public function addItem(Request $request, string $cart): JsonResponse
  {
    return $this->withCartAndProduct($request, $cart, function (CommerceCart $model, CommerceProduct $product) use ($request): JsonResponse {
      $this->carts->addProduct($model, $product, (int) $request->input('quantity', 1));

      return $this->ok(['cart' => $this->carts->summary($model)]);
    });
  }

  public function updateItem(Request $request, string $cart, string $product): JsonResponse
  {
    if ($unavailable = $this->unavailable()) {
      return $unavailable;
    }

    $model = $this->carts->findOpenByToken($cart);

    if ($model === null) {
      return $this->apiError('commerce_cart_not_found', 'The requested cart was not found or is no longer open.', 404);
    }

    $productModel = CommerceProduct::query()->whereKey($product)->first();

    if ($productModel === null) {
      return $this->apiError('commerce_product_not_found', 'The requested product was not found.', 404);
    }

    $validator = Validator::make($request->all(), ['quantity' => ['required', 'integer', 'min:0']]);

    if ($validator->fails()) {
      return $this->validationErrors($validator->errors()->toArray());
    }

    try {
      $this->carts->setQuantity($model, $productModel, (int) $request->input('quantity'));
    } catch (CartException $exception) {
      return $this->apiError('commerce_cart_rejected', $exception->getMessage(), 422);
    }

    return $this->ok(['cart' => $this->carts->summary($model)]);
  }

  public function removeItem(string $cart, string $product): JsonResponse
  {
    if ($unavailable = $this->unavailable()) {
      return $unavailable;
    }

    $model = $this->carts->findOpenByToken($cart);

    if ($model === null) {
      return $this->apiError('commerce_cart_not_found', 'The requested cart was not found or is no longer open.', 404);
    }

    $productModel = CommerceProduct::query()->whereKey($product)->first();

    if ($productModel === null) {
      return $this->apiError('commerce_product_not_found', 'The requested product was not found.', 404);
    }

    $this->carts->removeProduct($model, $productModel);

    return $this->ok(['cart' => $this->carts->summary($model)]);
  }

  public function clear(string $cart): JsonResponse
  {
    if ($unavailable = $this->unavailable()) {
      return $unavailable;
    }

    $model = $this->carts->findOpenByToken($cart);

    if ($model === null) {
      return $this->apiError('commerce_cart_not_found', 'The requested cart was not found or is no longer open.', 404);
    }

    $this->carts->clear($model);

    return $this->ok(['cart' => $this->carts->summary($model)]);
  }

  public function checkout(Request $request, string $cart): JsonResponse
  {
    if ($unavailable = $this->unavailable()) {
      return $unavailable;
    }

    $model = $this->carts->findOpenByToken($cart);

    if ($model === null) {
      return $this->apiError('commerce_cart_not_found', 'The requested cart was not found or is no longer open.', 404);
    }

    if ($request->filled('customer_email')) {
      $model->update(['customer_email' => (string) $request->input('customer_email')]);
    }

    try {
      $session = $this->checkout->forCart($model);
    } catch (CheckoutUnavailableException $exception) {
      return $this->apiError('commerce_checkout_unavailable', $exception->getMessage(), 409);
    }

    $order = $model->fresh('convertedOrder')->convertedOrder;

    return $this->ok([
      'redirect_url' => $session->redirectUrl,
      'checkout_mode' => $session->mode,
      'order' => [
        'id' => $order?->id,
        'order_number' => $order?->order_number,
        'status' => $order?->status,
        'total_amount' => (int) ($order?->total_amount ?? 0),
        'tax_amount' => (int) ($order?->tax_amount ?? 0),
        'currency' => $order?->currency,
      ],
    ], 201);
  }

  private function withCartAndProduct(Request $request, string $cart, callable $callback): JsonResponse
  {
    if ($unavailable = $this->unavailable()) {
      return $unavailable;
    }

    $model = $this->carts->findOpenByToken($cart);

    if ($model === null) {
      return $this->apiError('commerce_cart_not_found', 'The requested cart was not found or is no longer open.', 404);
    }

    $validator = Validator::make($request->all(), [
      'product_id' => ['required', 'integer'],
      'quantity' => ['nullable', 'integer', 'min:1'],
    ]);

    if ($validator->fails()) {
      return $this->validationErrors($validator->errors()->toArray());
    }

    $product = CommerceProduct::query()->whereKey($request->input('product_id'))->first();

    if ($product === null) {
      return $this->apiError('commerce_product_not_found', 'The requested product was not found.', 404);
    }

    try {
      return $callback($model, $product);
    } catch (CartException $exception) {
      return $this->apiError('commerce_cart_rejected', $exception->getMessage(), 422);
    }
  }

  private function unavailable(): ?JsonResponse
  {
    $tables = ['webblocks_commerce_carts', 'webblocks_commerce_cart_items', 'webblocks_commerce_products'];
    $missing = array_values(array_filter($tables, fn (string $table): bool => ! Schema::hasTable($table)));

    if ($missing !== []) {
      return response()->json([
        'ok' => false,
        'code' => 'commerce_setup_required',
        'message' => 'WebBlocks Commerce setup migrations have not been run yet.',
        'missing_tables' => $missing,
        'warnings' => [],
        'errors' => [['path' => 'commerce', 'message' => 'Run plugin setup before using the cart API.']],
      ], 409);
    }

    return null;
  }

  /**
   * @param  array<string, mixed>  $data
   */
  private function ok(array $data, int $status = 200): JsonResponse
  {
    return response()->json(['ok' => true, ...$data, 'warnings' => [], 'errors' => []], $status);
  }

  private function apiError(string $code, string $message, int $status = 422): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'code' => $code,
      'message' => $message,
      'warnings' => [],
      'errors' => [['path' => 'commerce', 'message' => $message]],
    ], $status);
  }

  /**
   * @param  array<string, array<int, string>>  $errors
   */
  private function validationErrors(array $errors): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'code' => 'invalid_commerce_cart',
      'message' => 'Cart request validation failed.',
      'warnings' => [],
      'errors' => collect($errors)
        ->map(fn (array $messages, string $field): array => ['path' => $field, 'message' => $messages[0] ?? 'Invalid value.'])
        ->values()
        ->all(),
    ], 422);
  }
}
