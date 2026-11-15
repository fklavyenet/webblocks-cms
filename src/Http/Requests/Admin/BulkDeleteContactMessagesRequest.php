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
    $selectionRequired = __('webblocks-cms::admin-feedback.bulk.select_required', ['entity' => __('webblocks-cms::admin-feedback.bulk_entities.message')]);

    return [
      'contact_message_ids.required' => $selectionRequired,
      'contact_message_ids.array' => $selectionRequired,
      'contact_message_ids.min' => $selectionRequired,
      'contact_message_ids.*.exists' => __('webblocks-cms::admin-feedback.bulk.selection_missing', ['entity' => __('webblocks-cms::admin-feedback.bulk_entities.messages')]),
    ];
  }
}
