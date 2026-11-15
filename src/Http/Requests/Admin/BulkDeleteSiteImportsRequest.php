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
    $selectionRequired = __('webblocks-cms::admin-feedback.bulk.select_required', ['entity' => __('webblocks-cms::admin-feedback.bulk_entities.site_import')]);

    return [
      'site_import_ids.required' => $selectionRequired,
      'site_import_ids.min' => $selectionRequired,
      'site_import_ids.*.exists' => __('webblocks-cms::admin-feedback.bulk.selection_missing', ['entity' => __('webblocks-cms::admin-feedback.bulk_entities.site_imports')]),
    ];
  }
}
