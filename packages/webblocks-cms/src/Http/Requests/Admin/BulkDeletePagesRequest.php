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
    return [
      'page_ids.required' => 'Select at least one page to delete.',
      'page_ids.min' => 'Select at least one page to delete.',
      'page_ids.*.exists' => 'One or more selected pages no longer exists.',
    ];
  }
}
