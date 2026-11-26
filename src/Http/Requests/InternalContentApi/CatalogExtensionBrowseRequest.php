<?php

namespace WebBlocks\Cms\Http\Requests\InternalContentApi;

use Illuminate\Foundation\Http\FormRequest;

final class CatalogExtensionBrowseRequest extends FormRequest
{
  public function rules(): array
  {
    return ['page' => ['sometimes', 'integer', 'min:1', 'max:10000']];
  }
}
