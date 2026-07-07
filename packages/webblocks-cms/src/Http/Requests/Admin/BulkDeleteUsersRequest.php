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
    return [
      'user_ids.required' => 'Select at least one user to delete.',
      'user_ids.min' => 'Select at least one user to delete.',
      'user_ids.*.exists' => 'One or more selected users no longer exists.',
    ];
  }
}
