<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SiteImportUploadRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'archive' => ['required', 'file', 'mimes:zip', 'max:51200'],
    ];
  }

  public function messages(): array
  {
    return ['archive.required' => __('webblocks-cms::admin-feedback.panel_copy.site_import_archive_required')];
  }
}
