<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\Theme\ThemePackage;

final class ThemePackageRequest extends FormRequest
{
  public function rules(): array
  {
    return ['package' => ['required', 'file', 'max:'.ThemePackage::MAX_KILOBYTES]];
  }

  public function attributes(): array
  {
    return ['package' => __('webblocks-cms::themes.package')];
  }
}
