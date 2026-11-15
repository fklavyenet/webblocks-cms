<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeletePagesRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->can('access-admin') ?? false;
  }

  public function rules(): array
  {
    return [
      'page_ids' => ['required', 'array', 'min:1'],
      'page_ids.*' => ['required', 'integer', 'distinct', 'exists:wbcms_pages,id'],
    ];
  }

  public function messages(): array
  {
    $selectionRequired = __('webblocks-cms::admin-feedback.bulk.select_required', ['entity' => __('webblocks-cms::admin-feedback.bulk_entities.page')]);

    return [
      'page_ids.required' => $selectionRequired,
      'page_ids.min' => $selectionRequired,
      'page_ids.*.exists' => __('webblocks-cms::admin-feedback.bulk.selection_missing', ['entity' => __('webblocks-cms::admin-feedback.bulk_entities.pages')]),
    ];
  }
}
