<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteSiteExportsRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->can('access-system') ?? false;
  }

  public function rules(): array
  {
    return [
      'site_export_ids' => ['required', 'array', 'min:1'],
      'site_export_ids.*' => ['required', 'integer', 'distinct', 'exists:wbcms_site_exports,id'],
    ];
  }

  public function messages(): array
  {
    return [
      'site_export_ids.required' => 'Select at least one site export to delete.',
      'site_export_ids.min' => 'Select at least one site export to delete.',
      'site_export_ids.*.exists' => 'One or more selected site exports no longer exists.',
    ];
  }
}
