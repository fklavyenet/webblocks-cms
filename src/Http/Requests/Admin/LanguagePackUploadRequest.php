<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\Translations\NativeLanguageArchive;

final class LanguagePackUploadRequest extends FormRequest
{
  public function rules(): array
  {
    return ['package' => ['required', 'file', 'max:'.(NativeLanguageArchive::MAX_BYTES / 1024)]];
  }

  public function attributes(): array
  {
    return ['package' => __('webblocks-cms::panel-languages.package')];
  }
}
