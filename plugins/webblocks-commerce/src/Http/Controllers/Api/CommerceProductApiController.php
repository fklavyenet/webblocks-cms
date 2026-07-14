<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers\Api\Concerns\RespondsWithCommerceApiEnvelope;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Currency\CurrencyCatalog;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways\CommerceGatewayManager;
use WebBlocks\Cms\Support\Database\CmsTable;

/**
 * Plugin-owned product API (migrated from the CMS core InternalCommerceController
 * so the plugin owns its whole AI surface). Exposes the same tax_class and
 * catalog fields the admin panel does.
 */
class CommerceProductApiController extends Controller
{
  use RespondsWithCommerceApiEnvelope;

  public function index(Request $request): JsonResponse
  {
    if ($unavailable = $this->requireTables(['webblocks_commerce_products'])) {
      return $unavailable;
    }

    $query = CommerceProduct::query()->with(['site', 'imageMedia']);

    if ($request->filled('status')) {
      $query->where('status', (string) $request->query('status'));
    }

    if ($request->filled('site_id')) {
      $query->where('site_id', (int) $request->query('site_id'));
    }

    if ($request->filled('search')) {
      $search = trim((string) $request->query('search'));
      $query->where(function ($query) use ($search): void {
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
      ->map(fn (CommerceProduct $product): array => $this->payload($product))
      ->values()
      ->all();

    return $this->ok(['products' => $products]);
  }

  public function store(Request $request): JsonResponse
  {
    if ($unavailable = $this->requireTables(['webblocks_commerce_products'])) {
      return $unavailable;
    }

    $validator = Validator::make($this->normalizedInput($request), $this->rules());

    if ($validator->fails()) {
      return $this->validationErrors($validator->errors()->toArray(), 'invalid_commerce_product');
    }

    $product = CommerceProduct::query()->create($validator->validated());

    return $this->ok(['product' => $this->payload($product->fresh(['site', 'imageMedia']))], 201);
  }

  public function update(Request $request, string $product): JsonResponse
  {
    if ($unavailable = $this->requireTables(['webblocks_commerce_products'])) {
      return $unavailable;
    }

    $record = CommerceProduct::query()->whereKey($product)->first();

    if ($record === null) {
      return $this->apiError('commerce_product_not_found', 'The requested commerce product was not found.', 404);
    }

    $validator = Validator::make(
      $this->normalizedInput($request, partial: true),
      $this->rules($record->getKey(), partial: true)
    );

    if ($validator->fails()) {
      return $this->validationErrors($validator->errors()->toArray(), 'invalid_commerce_product');
    }

    $payload = array_intersect_key($validator->validated(), array_flip(array_keys($request->all())));

    if ($payload === []) {
      return $this->apiError('empty_commerce_product_update', 'Provide at least one product field to update.');
    }

    $record->fill($payload)->save();

    return $this->ok(['product' => $this->payload($record->fresh(['site', 'imageMedia']))]);
  }

  /**
   * @return array<string, mixed>
   */
  private function normalizedInput(Request $request, bool $partial = false): array
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
  private function rules(?int $ignoreId = null, bool $partial = false): array
  {
    $required = $partial ? 'sometimes' : 'required';
    $nullable = $partial ? 'sometimes' : 'nullable';

    return [
      'site_id' => [$nullable, 'integer', 'exists:'.CmsTable::name('sites').',id'],
      'image_media_id' => [$nullable, 'integer', 'exists:'.CmsTable::name('media').',id'],
      'title' => [$required, 'string', 'max:255'],
      'slug' => [
        $required,
        'string',
        'max:255',
        'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
        Rule::unique('webblocks_commerce_products', 'slug')->ignore($ignoreId),
      ],
      'description' => [$nullable, 'string'],
      'status' => [$partial ? 'sometimes' : 'required', Rule::in([
        CommerceProduct::STATUS_DRAFT,
        CommerceProduct::STATUS_ACTIVE,
        CommerceProduct::STATUS_ARCHIVED,
      ])],
      'price_amount' => [$required, 'integer', 'min:1'],
      'currency' => [
        $required,
        'string',
        Rule::in(app(CurrencyCatalog::class)->codesForGateway(app(CommerceGatewayManager::class)->gatewayKey())),
      ],
      'tax_class' => [$partial ? 'sometimes' : 'nullable', Rule::in([
        CommerceProduct::TAX_CLASS_STANDARD,
        CommerceProduct::TAX_CLASS_REDUCED,
        CommerceProduct::TAX_CLASS_ZERO,
      ])],
      'inventory_quantity' => [$nullable, 'integer', 'min:0'],
      'sku' => [$nullable, 'string', 'max:255'],
      'metadata' => [$nullable, 'array'],
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function payload(CommerceProduct $product): array
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
      'tax_class' => $product->taxClass(),
      'inventory_quantity' => $product->inventory_quantity,
      'sku' => $product->sku,
      'metadata' => $product->metadata ?? [],
      'available_for_checkout' => $product->isAvailableForCheckout(),
      'buy_url' => $this->buyUrl((string) $product->slug),
      'created_at' => $product->created_at?->toIso8601String(),
      'updated_at' => $product->updated_at?->toIso8601String(),
    ];
  }

  private function buyUrl(string $slug): ?string
  {
    if (! Route::has('webblocks.commerce.products.buy')) {
      return null;
    }

    return route('webblocks.commerce.products.buy', $slug, absolute: false);
  }
}
