<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Support\Access\CmsAuthorization;

class BulkUpdateContactMessageStatusRequest extends FormRequest
{
  public function authorize(): bool
  {
    return app(CmsAuthorization::class)->allows($this->user(), 'cms.messages.update-status');
  }

  public function rules(): array
  {
    return [
      'contact_message_ids' => ['required', 'array', 'list', 'min:1'],
      'contact_message_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
      'status' => ['required', Rule::in(['new', 'read'])],
    ];
  }
}
