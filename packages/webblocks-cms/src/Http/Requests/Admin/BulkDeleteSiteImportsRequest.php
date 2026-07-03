<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteSiteImportsRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->can('access-system') ?? false;
  }

  public function rules(): array
  {
    return [
      'site_import_ids' => ['required', 'array', 'min:1'],
      'site_import_ids.*' => ['required', 'integer', 'distinct', 'exists:wbcms_site_imports,id'],
    ];
  }

  public function messages(): array
  {
    return [
      'site_import_ids.required' => 'Select at least one site import to delete.',
      'site_import_ids.min' => 'Select at least one site import to delete.',
      'site_import_ids.*.exists' => 'One or more selected site imports no longer exists.',
    ];
  }
}
