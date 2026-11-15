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
    $selectionRequired = __('webblocks-cms::admin-feedback.bulk.select_required', ['entity' => __('webblocks-cms::admin-feedback.bulk_entities.site_export')]);

    return [
      'site_export_ids.required' => $selectionRequired,
      'site_export_ids.min' => $selectionRequired,
      'site_export_ids.*.exists' => __('webblocks-cms::admin-feedback.bulk.selection_missing', ['entity' => __('webblocks-cms::admin-feedback.bulk_entities.site_exports')]),
    ];
  }
}
