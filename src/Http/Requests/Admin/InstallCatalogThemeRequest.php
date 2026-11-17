<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\Theme\ThemeCatalog;

final class InstallCatalogThemeRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'handle' => ['required', 'string', 'max:63', 'regex:/^'.ThemeCatalog::HANDLE_PATTERN.'$/D'],
      'version' => ['required', 'string', 'max:64', 'regex:/^'.ThemeCatalog::VERSION_PATTERN.'$/D'],
    ];
  }
}
