<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageAsset;
use WebBlocks\Cms\Support\Pages\PageAssetPathValidator;

class PageAssetRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'path' => ['required', 'string', 'max:2048'],
      'sort_order' => ['nullable', 'integer', 'min:0'],
      'is_enabled' => ['nullable', 'boolean'],
      'is_defer' => ['nullable', 'boolean'],
      'is_async' => ['nullable', 'boolean'],
      'is_module' => ['nullable', 'boolean'],
      '_page_asset_close_url' => ['nullable', 'string', 'max:2048'],
      '_page_asset_modal' => ['nullable', 'string', 'max:255'],
      '_page_asset_id' => ['nullable', 'integer'],
      '_page_asset_type' => ['nullable', 'string', Rule::in(PageAsset::allowedTypes())],
      '_page_settings_tab' => ['nullable', 'string'],
    ];
  }

  public function after(): array
  {
    return [function (Validator $validator): void {
      $message = app(PageAssetPathValidator::class)->validate($this->assetType(), $this->input('path'));

      if ($message !== null) {
        $validator->errors()->add('path', $message);
      }
    }];
  }

  public function assetType(): string
  {
    $pageAsset = $this->route('page_asset');

    if ($pageAsset instanceof PageAsset) {
      return $pageAsset->type;
    }

    return app(PageAssetPathValidator::class)->normalizeType($this->route('type'));
  }

  public function assetData(): array
  {
    return app(PageAssetPathValidator::class)->assetData($this->assetType(), $this->input('path'), $this->input());
  }

  protected function getRedirectUrl(): string
  {
    $page = $this->route('page');

    if ($page instanceof Page) {
      return (string) ($this->input('_page_asset_close_url') ?: route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']));
    }

    return parent::getRedirectUrl();
  }
}
