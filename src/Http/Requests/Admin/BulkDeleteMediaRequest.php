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
    return [
      'media_ids.required' => 'Select at least one media item to delete.',
      'media_ids.array' => 'Select at least one media item to delete.',
      'media_ids.min' => 'Select at least one media item to delete.',
      'media_ids.*.exists' => 'One or more selected media items no longer exists.',
    ];
  }
}
