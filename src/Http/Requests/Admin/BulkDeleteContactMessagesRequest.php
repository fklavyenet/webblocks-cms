<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteContactMessagesRequest extends FormRequest
{
  public function authorize(): bool
  {
    return (bool) $this->user()?->can('access-admin');
  }

  public function rules(): array
  {
    return [
      'contact_message_ids' => ['required', 'array', 'min:1'],
      'contact_message_ids.*' => ['integer', 'distinct', 'exists:wbcms_contact_messages,id'],
    ];
  }

  public function messages(): array
  {
    return [
      'contact_message_ids.required' => 'Select at least one message to delete.',
      'contact_message_ids.array' => 'Select at least one message to delete.',
      'contact_message_ids.min' => 'Select at least one message to delete.',
      'contact_message_ids.*.exists' => 'One or more selected messages no longer exists.',
    ];
  }
}
