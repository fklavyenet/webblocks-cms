<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

class InternalCommerceController extends Controller
{
  private const PLUGIN_HANDLE = 'webblocks-commerce';

  private const PRODUCT_CLASS = 'WebBlocks\\Cms\\Plugins\\WebBlocksCommerce\\Models\\CommerceProduct';

  private const ORDER_CLASS = 'WebBlocks\\Cms\\Plugins\\WebBlocksCommerce\\Models\\CommerceOrder';

  public function __construct(
    private readonly PluginRegistry $plugins,
  ) {}

  public function products(Request $request): JsonResponse
  {
    if ($unavailable = $this->commerceUnavailable()) {
      return $unavailable;
    }

    $class = self::PRODUCT_CLASS;
    $query = $class::query()->with(['site', 'imageMedia']);

    if ($request->filled('status')) {
      $query->where('status', (string) $request->query('status'));
    }

    if ($request->filled('site_id')) {
      $query->where('site_id', (int) $request->query('site_id'));
    }

    if ($request->filled('search')) {
      $search = trim((string) $request->query('search'));

      $query->where(function ($query) use ($search) {
        $query
          ->where('title', 'like', '%'.$search.'%')
          ->orWhere('slug', 'like', '%'.$search.'%')
          ->orWhere('sku', 'like', '%'.$search.'%');
      });
    }

    $products = $query
      ->orderByDesc('created_at')
      ->orderByDesc('id')
      ->limit(min(max((int) $request->query('limit', 50), 1), 100))
      ->get()
      ->map(fn ($product): array => $this->productPayload($product))
      ->values()
      ->all();

    return $this->ok(['products' => $products]);
  }

  public function storeProduct(Request $request): JsonResponse
  {
    if ($unavailable = $this->commerceUnavailable()) {
      return $unavailable;
    }

    $validator = Validator::make($this->normalizedProductInput($request), $this->productRules());

    if ($validator->fails()) {
      return $this->validationErrors('invalid_commerce_product', 'Product validation failed.', $validator->errors()->toArray());
    }

    $class = self::PRODUCT_CLASS;
    $product = $class::query()->create($validator->validated());

    return response()->json([
      'ok' => true,
      'product' => $this->productPayload($product->fresh(['site', 'imageMedia'])),
      'warnings' => [],
      'errors' => [],
    ], 201);
  }

  public function updateProduct(Request $request, string $product): JsonResponse
  {
    if ($unavailable = $this->commerceUnavailable()) {
      return $unavailable;
    }

    $class = self::PRODUCT_CLASS;
    $record = $class::query()->whereKey($product)->first();

    if (! $record) {
      return $this->apiError('commerce_product_not_found', 'The requested commerce product was not found.', 404);
    }

    $validator = Validator::make(
      $this->normalizedProductInput($request, partial: true),
      $this->productRules($record->getKey(), partial: true)
    );

    if ($validator->fails()) {
      return $this->validationErrors('invalid_commerce_product', 'Product validation failed.', $validator->errors()->toArray());
    }

    $payload = array_intersect_key($validator->validated(), array_flip(array_keys($request->all())));

    if ($payload === []) {
      return $this->apiError('empty_commerce_product_update', 'Provide at least one product field to update.');
    }

    $record->fill($payload);
    $record->save();

    return $this->ok([
      'product' => $this->productPayload($record->fresh(['site', 'imageMedia'])),
    ]);
  }

  public function orders(Request $request): JsonResponse
  {
    if ($unavailable = $this->commerceUnavailable(requireOrders: true)) {
      return $unavailable;
    }

    $class = self::ORDER_CLASS;
    $query = $class::query()->with(['site', 'items', 'payments']);

    foreach (['status', 'gateway', 'site_id'] as $field) {
      if ($request->filled($field)) {
        $query->where($field, $field === 'site_id' ? (int) $request->query($field) : (string) $request->query($field));
      }
    }

    if ($request->filled('search')) {
      $search = trim((string) $request->query('search'));

      $query->where(function ($query) use ($search) {
        $query
          ->where('order_number', 'like', '%'.$search.'%')
          ->orWhere('customer_email', 'like', '%'.$search.'%')
          ->orWhere('gateway_checkout_id', 'like', '%'.$search.'%')
          ->orWhere('gateway_payment_id', 'like', '%'.$search.'%');
      });
    }

    $orders = $query
      ->orderByDesc('created_at')
      ->orderByDesc('id')
      ->limit(min(max((int) $request->query('limit', 50), 1), 100))
      ->get()
      ->map(fn ($order): array => $this->orderPayload($order))
      ->values()
      ->all();

    return $this->ok(['orders' => $orders]);
  }

  public function order(string $order): JsonResponse
  {
    if ($unavailable = $this->commerceUnavailable(requireOrders: true)) {
      return $unavailable;
    }

    $class = self::ORDER_CLASS;
    $record = $class::query()->with(['site', 'items', 'payments'])->whereKey($order)->first();

    if (! $record) {
      return $this->apiError('commerce_order_not_found', 'The requested commerce order was not found.', 404);
    }

    return $this->ok(['order' => $this->orderPayload($record)]);
  }

  private function commerceUnavailable(bool $requireOrders = false): ?JsonResponse
  {
    if (! $this->plugins->isEnabled(self::PLUGIN_HANDLE)) {
      return $this->apiError('commerce_plugin_disabled', 'WebBlocks Commerce is not enabled.', 409);
    }

    if (! class_exists(self::PRODUCT_CLASS) || ($requireOrders && ! class_exists(self::ORDER_CLASS))) {
      return $this->apiError('commerce_plugin_unavailable', 'WebBlocks Commerce classes are not available.', 409);
    }

    $tables = ['webblocks_commerce_products'];

    if ($requireOrders) {
      $tables = [
        'webblocks_commerce_orders',
        'webblocks_commerce_order_items',
        'webblocks_commerce_payments',
      ];
    }

    $missing = array_values(array_filter($tables, fn (string $table): bool => ! Schema::hasTable($table)));

    if ($missing !== []) {
      return response()->json([
        'ok' => false,
        'code' => 'commerce_setup_required',
        'message' => 'WebBlocks Commerce setup migrations have not been run yet.',
        'missing_tables' => $missing,
        'setup_url' => '/webadmin/api/plugins/webblocks-commerce/setup',
        'warnings' => [],
        'errors' => [
          [
            'path' => 'commerce',
            'message' => 'Run plugin setup before using Commerce API resources.',
          ],
        ],
      ], 409);
    }

    return null;
  }

  /**
   * @return array<string, mixed>
   */
  private function normalizedProductInput(Request $request, bool $partial = false): array
  {
    $payload = $request->all();

    if (! $partial || array_key_exists('title', $payload)) {
      $payload['title'] = trim((string) ($payload['title'] ?? ''));
    }

    if (! $partial || array_key_exists('slug', $payload)) {
      $slug = trim((string) ($payload['slug'] ?? ''));
      $payload['slug'] = $slug !== '' ? Str::slug($slug) : Str::slug((string) ($payload['title'] ?? ''));
    }

    if (! $partial || array_key_exists('currency', $payload)) {
      $payload['currency'] = strtoupper(trim((string) ($payload['currency'] ?? 'USD')));
    }

    foreach (['description', 'sku'] as $field) {
      if (array_key_exists($field, $payload) && trim((string) $payload[$field]) === '') {
        $payload[$field] = null;
      }
    }

    foreach (['site_id', 'image_media_id', 'inventory_quantity'] as $field) {
      if (array_key_exists($field, $payload) && $payload[$field] === '') {
        $payload[$field] = null;
      }
    }

    return $payload;
  }

  /**
   * @return array<string, array<int, mixed>>
   */
  private function productRules(?int $ignoreId = null, bool $partial = false): array
  {
    $required = $partial ? 'sometimes' : 'required';
    $nullable = $partial ? 'sometimes' : 'nullable';

    return [
      'site_id' => [$nullable, 'integer', 'exists:wbcms_sites,id'],
      'image_media_id' => [$nullable, 'integer', 'exists:wbcms_media,id'],
      'title' => [$required, 'string', 'max:255'],
      'slug' => [
        $required,
        'string',
        'max:255',
        'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
        Rule::unique('webblocks_commerce_products', 'slug')->ignore($ignoreId),
      ],
      'description' => [$nullable, 'string'],
      'status' => [$partial ? 'sometimes' : 'required', Rule::in(['draft', 'active', 'archived'])],
      'price_amount' => [$required, 'integer', 'min:1'],
      'currency' => [$required, 'string', 'size:3'],
      'inventory_quantity' => [$nullable, 'integer', 'min:0'],
      'sku' => [$nullable, 'string', 'max:255'],
      'metadata' => [$nullable, 'array'],
    ];
  }

  private function productPayload($product): array
  {
    return [
      'id' => $product->id,
      'site_id' => $product->site_id,
      'site_handle' => $product->relationLoaded('site') ? $product->site?->handle : null,
      'image_media_id' => $product->image_media_id,
      'title' => $product->title,
      'slug' => $product->slug,
      'description' => $product->description,
      'status' => $product->status,
      'price_amount' => (int) $product->price_amount,
      'currency' => $product->currency,
      'inventory_quantity' => $product->inventory_quantity,
      'sku' => $product->sku,
      'metadata' => $product->metadata ?? [],
      'available_for_checkout' => method_exists($product, 'isAvailableForCheckout') ? $product->isAvailableForCheckout() : $product->status === 'active',
      'buy_url' => $this->productBuyUrl((string) $product->slug),
      'created_at' => $product->created_at?->toIso8601String(),
      'updated_at' => $product->updated_at?->toIso8601String(),
    ];
  }

  private function productBuyUrl(string $slug): ?string
  {
    if (! Route::has('webblocks.commerce.products.buy')) {
      return null;
    }

    return route('webblocks.commerce.products.buy', $slug, absolute: false);
  }

  private function orderPayload($order): array
  {
    return [
      'id' => $order->id,
      'site_id' => $order->site_id,
      'site_handle' => $order->relationLoaded('site') ? $order->site?->handle : null,
      'order_number' => $order->order_number,
      'customer_email' => $order->customer_email,
      'status' => $order->status,
      'subtotal_amount' => (int) $order->subtotal_amount,
      'total_amount' => (int) $order->total_amount,
      'currency' => $order->currency,
      'gateway' => $order->gateway,
      'gateway_checkout_id' => $order->gateway_checkout_id,
      'gateway_payment_id' => $order->gateway_payment_id,
      'placed_at' => $order->placed_at?->toIso8601String(),
      'paid_at' => $order->paid_at?->toIso8601String(),
      'cancelled_at' => $order->cancelled_at?->toIso8601String(),
      'metadata' => $order->metadata ?? [],
      'items' => $order->relationLoaded('items')
        ? $order->items->map(fn ($item): array => [
          'id' => $item->id,
          'product_id' => $item->product_id,
          'title' => $item->title,
          'sku' => $item->sku,
          'quantity' => (int) $item->quantity,
          'unit_amount' => (int) $item->unit_amount,
          'total_amount' => (int) $item->total_amount,
          'currency' => $item->currency,
        ])->values()->all()
        : [],
      'payments' => $order->relationLoaded('payments')
        ? $order->payments->map(fn ($payment): array => [
          'id' => $payment->id,
          'gateway' => $payment->gateway,
          'gateway_payment_id' => $payment->gateway_payment_id,
          'gateway_checkout_id' => $payment->gateway_checkout_id,
          'status' => $payment->status,
          'amount' => (int) $payment->amount,
          'currency' => $payment->currency,
        ])->values()->all()
        : [],
      'created_at' => $order->created_at?->toIso8601String(),
      'updated_at' => $order->updated_at?->toIso8601String(),
    ];
  }

  private function ok(array $data): JsonResponse
  {
    return response()->json([
      'ok' => true,
      ...$data,
      'warnings' => [],
      'errors' => [],
    ]);
  }

  private function apiError(string $code, string $message, int $status = 422): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'code' => $code,
      'message' => $message,
      'warnings' => [],
      'errors' => [
        [
          'path' => 'commerce',
          'message' => $message,
        ],
      ],
    ], $status);
  }

  /**
   * @param  array<string, array<int, string>>  $errors
   */
  private function validationErrors(string $code, string $message, array $errors): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'code' => $code,
      'message' => $message,
      'warnings' => [],
      'errors' => collect($errors)
        ->map(fn (array $messages, string $field): array => [
          'path' => $field,
          'message' => $messages[0] ?? 'Invalid value.',
        ])
        ->values()
        ->all(),
    ], 422);
  }
}
