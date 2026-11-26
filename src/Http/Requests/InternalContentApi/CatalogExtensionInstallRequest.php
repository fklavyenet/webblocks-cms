<?php

namespace WebBlocks\Cms\Http\Requests\InternalContentApi;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\Theme\ThemeCatalog;

final class CatalogExtensionInstallRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'version' => ['required', 'string', 'max:64', 'regex:/\A'.ThemeCatalog::VERSION_PATTERN.'\z/D'],
      'checksum' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
    ];
  }
}
