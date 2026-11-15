<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteMediaRequest extends FormRequest
{
  public function authorize(): bool
  {
    return (bool) $this->user()?->can('access-admin');
  }

  public function rules(): array
  {
    return [
      'media_ids' => ['required', 'array', 'min:1'],
      'media_ids.*' => ['integer', 'distinct', 'exists:wbcms_media,id'],
    ];
  }

  public function messages(): array
  {
    $selectionRequired = __('webblocks-cms::admin-feedback.bulk.select_required', ['entity' => __('webblocks-cms::admin-feedback.bulk_entities.media_item')]);

    return [
      'media_ids.required' => $selectionRequired,
      'media_ids.array' => $selectionRequired,
      'media_ids.min' => $selectionRequired,
      'media_ids.*.exists' => __('webblocks-cms::admin-feedback.bulk.selection_missing', ['entity' => __('webblocks-cms::admin-feedback.bulk_entities.media_items')]),
    ];
  }
}
