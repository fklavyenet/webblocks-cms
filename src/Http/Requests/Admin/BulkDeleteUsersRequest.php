<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteUsersRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->can('manage-users') ?? false;
  }

  public function rules(): array
  {
    return [
      'user_ids' => ['required', 'array', 'min:1'],
      'user_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
    ];
  }

  public function messages(): array
  {
    $selectionRequired = __('webblocks-cms::admin-feedback.bulk.select_required', ['entity' => __('webblocks-cms::admin-feedback.bulk_entities.user')]);

    return [
      'user_ids.required' => $selectionRequired,
      'user_ids.min' => $selectionRequired,
      'user_ids.*.exists' => __('webblocks-cms::admin-feedback.bulk.selection_missing', ['entity' => __('webblocks-cms::admin-feedback.bulk_entities.users')]),
    ];
  }
}
