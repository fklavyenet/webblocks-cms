<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Support\Sites\SiteAssetStore;

class SiteAssetRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  protected function prepareForValidation(): void
  {
    $this->merge([
      'contents' => (string) $this->input('contents', ''),
      'expected_checksum' => $this->filled('expected_checksum') ? (string) $this->input('expected_checksum') : null,
      '_site_asset_type' => $this->assetType(),
    ]);
  }

  public function rules(): array
  {
    return [
      'contents' => ['nullable', 'string', 'max:300000'],
      'expected_checksum' => ['nullable', 'string', 'size:64'],
      '_site_asset_type' => ['required', 'string', Rule::in(SiteAssetStore::TYPES)],
    ];
  }

  public function assetType(): string
  {
    return strtolower((string) $this->route('type'));
  }

  public function contents(): string
  {
    return (string) $this->input('contents', '');
  }

  public function expectedChecksum(): ?string
  {
    return $this->input('expected_checksum');
  }

  protected function getRedirectUrl(): string
  {
    $site = $this->route('site');

    if ($site) {
      return route('admin.site-assets.index', ['site' => $site]);
    }

    return parent::getRedirectUrl();
  }
}
