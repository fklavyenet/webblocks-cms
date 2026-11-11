<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\Translations\PanelLanguages;

final class PanelLanguageCatalogRequest extends FormRequest
{
  public function rules(): array
  {
    return ['catalog' => ['required', 'file', 'max:'.PanelLanguages::MAX_KILOBYTES]];
  }

  public function catalogContents(): string
  {
    return $this->file('catalog')->getContent();
  }
}
