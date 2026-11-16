<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\Translations\PanelLanguages;

final class PanelLanguageCatalogRequest extends FormRequest
{
  public function messages(): array
  {
    return [
      'catalog.required' => __('webblocks-cms::admin-feedback.panel_validation.panel_catalog_required'),
    ];
  }

  public function rules(): array
  {
    return ['catalog' => ['required', 'file', 'max:'.PanelLanguages::MAX_KILOBYTES]];
  }

  public function catalogContents(): string
  {
    return $this->file('catalog')->getContent();
  }
}
