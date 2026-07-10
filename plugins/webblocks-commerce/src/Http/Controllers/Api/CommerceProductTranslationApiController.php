<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProductTranslation;

/**
 * Plugin-owned, AI-first API for managing per-locale product content. Storefront
 * content shares the CMS Site+Locale system, so translations are keyed by the
 * CMS locale code; the base product row remains the default/fallback.
 */
class CommerceProductTranslationApiController extends Controller
{
  public function index(string $product): JsonResponse
  {
    if ($unavailable = $this->unavailable()) {
      return $unavailable;
    }

    $model = CommerceProduct::query()->with('translations.locale')->whereKey($product)->first();

    if ($model === null) {
      return $this->apiError('commerce_product_not_found', 'The requested product was not found.', 404);
    }

    return $this->ok([
      'product_id' => $model->getKey(),
      'base' => ['title' => $model->title, 'description' => $model->description],
      'translations' => $model->translations
        ->map(fn (CommerceProductTranslation $t): array => $this->payload($t))
        ->values()
        ->all(),
    ]);
  }

  public function upsert(Request $request, string $product, string $locale): JsonResponse
  {
    if ($unavailable = $this->unavailable()) {
      return $unavailable;
    }

    $model = CommerceProduct::query()->whereKey($product)->first();

    if ($model === null) {
      return $this->apiError('commerce_product_not_found', 'The requested product was not found.', 404);
    }

    $localeModel = $this->locale($locale);

    if ($localeModel === null) {
      return $this->apiError('commerce_locale_not_found', 'Unknown locale code. Create the locale in the CMS first.', 404);
    }

    $validator = Validator::make($request->all(), [
      'title' => ['nullable', 'string', 'max:255'],
      'description' => ['nullable', 'string'],
    ]);

    if ($validator->fails()) {
      return $this->validationErrors($validator->errors()->toArray());
    }

    $translation = CommerceProductTranslation::query()->updateOrCreate(
      ['product_id' => $model->getKey(), 'locale_id' => $localeModel->getKey()],
      [
        'title' => $this->nullableString($request->input('title')),
        'description' => $this->nullableString($request->input('description')),
      ],
    );

    return $this->ok(['translation' => $this->payload($translation->fresh('locale'))]);
  }

  public function destroy(string $product, string $locale): JsonResponse
  {
    if ($unavailable = $this->unavailable()) {
      return $unavailable;
    }

    $localeModel = $this->locale($locale);

    if ($localeModel === null) {
      return $this->apiError('commerce_locale_not_found', 'Unknown locale code.', 404);
    }

    CommerceProductTranslation::query()
      ->where('product_id', $product)
      ->where('locale_id', $localeModel->getKey())
      ->delete();

    return $this->ok(['deleted' => true]);
  }

  private function locale(string $code): ?Locale
  {
    $normalized = Locale::normalizeCode($code);

    if ($normalized === null) {
      return null;
    }

    return Locale::query()->where('code', $normalized)->first();
  }

  private function payload(CommerceProductTranslation $translation): array
  {
    return [
      'id' => $translation->id,
      'locale_id' => $translation->locale_id,
      'locale' => $translation->relationLoaded('locale') ? $translation->locale?->code : null,
      'title' => $translation->title,
      'description' => $translation->description,
    ];
  }

  private function nullableString(mixed $value): ?string
  {
    $value = is_string($value) ? trim($value) : null;

    return $value !== null && $value !== '' ? $value : null;
  }

  private function unavailable(): ?JsonResponse
  {
    $tables = ['webblocks_commerce_products', 'webblocks_commerce_product_translations'];
    $missing = array_values(array_filter($tables, fn (string $table): bool => ! Schema::hasTable($table)));

    if ($missing !== []) {
      return response()->json([
        'ok' => false,
        'code' => 'commerce_setup_required',
        'message' => 'WebBlocks Commerce setup migrations have not been run yet.',
        'missing_tables' => $missing,
        'warnings' => [],
        'errors' => [['path' => 'commerce', 'message' => 'Run plugin setup before using the translation API.']],
      ], 409);
    }

    return null;
  }

  /**
   * @param  array<string, mixed>  $data
   */
  private function ok(array $data): JsonResponse
  {
    return response()->json(['ok' => true, ...$data, 'warnings' => [], 'errors' => []]);
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
      'code' => 'invalid_commerce_translation',
      'message' => 'Translation validation failed.',
      'warnings' => [],
      'errors' => collect($errors)
        ->map(fn (array $messages, string $field): array => ['path' => $field, 'message' => $messages[0] ?? 'Invalid value.'])
        ->values()
        ->all(),
    ], 422);
  }
}
