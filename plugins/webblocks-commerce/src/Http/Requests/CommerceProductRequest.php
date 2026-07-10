<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;
use WebBlocks\Cms\Support\Database\CmsTable;

class CommerceProductRequest extends FormRequest
{
  public function authorize(): bool
  {
    return (bool) $this->user()?->can('webblocks-commerce.manage-products');
  }

  /**
   * @return array<string, mixed>
   */
  public function rules(): array
  {
    $productId = $this->productRouteId();

    return [
      'site_id' => ['nullable', 'integer', 'exists:'.CmsTable::name('sites').',id'],
      'title' => ['required', 'string', 'max:255'],
      'slug' => [
        'required',
        'string',
        'max:255',
        'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
        Rule::unique('webblocks_commerce_products', 'slug')->ignore($productId),
      ],
      'description' => ['nullable', 'string'],
      'status' => ['required', Rule::in([
        CommerceProduct::STATUS_DRAFT,
        CommerceProduct::STATUS_ACTIVE,
        CommerceProduct::STATUS_ARCHIVED,
      ])],
      'price_amount' => ['required', 'integer', 'min:1'],
      'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
      'tax_class' => ['required', Rule::in([
        CommerceProduct::TAX_CLASS_STANDARD,
        CommerceProduct::TAX_CLASS_REDUCED,
        CommerceProduct::TAX_CLASS_ZERO,
      ])],
      'inventory_quantity' => ['nullable', 'integer', 'min:0'],
      'sku' => ['nullable', 'string', 'max:255'],
    ];
  }

  /**
   * @return array<string, mixed>
   */
  public function productPayload(): array
  {
    $validated = $this->validated();
    $validated['site_id'] = $validated['site_id'] ?? null;
    $validated['inventory_quantity'] = $validated['inventory_quantity'] ?? null;

    return $validated;
  }

  protected function prepareForValidation(): void
  {
    $title = trim((string) $this->input('title'));
    $slug = trim((string) $this->input('slug'));
    $currency = strtoupper(trim((string) $this->input('currency')));
    $inventory = $this->input('inventory_quantity');
    $taxClass = trim((string) $this->input('tax_class'));

    $this->merge([
      'title' => $title,
      'slug' => $slug !== '' ? Str::slug($slug) : Str::slug($title),
      'currency' => $currency,
      'tax_class' => $taxClass !== '' ? $taxClass : CommerceProduct::TAX_CLASS_STANDARD,
      'inventory_quantity' => $inventory === '' ? null : $inventory,
      'sku' => trim((string) $this->input('sku')) ?: null,
    ]);
  }

  private function productRouteId(): ?int
  {
    $product = $this->route('product');

    if (is_numeric($product)) {
      return (int) $product;
    }

    $pluginPath = $this->route('pluginPath');

    if (is_string($pluginPath) && preg_match('#^products/(\d+)(?:/edit|/archive)?$#', $pluginPath, $matches) === 1) {
      return (int) $matches[1];
    }

    return null;
  }
}
