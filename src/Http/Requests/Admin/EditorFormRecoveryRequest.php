<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Support\EditorRecovery\RecoveryFormContext;

class EditorFormRecoveryRequest extends FormRequest
{
  public function authorize(): bool
  {
    return (bool) $this->user()?->is_active;
  }

  public function rules(): array
  {
    return [...(new EditorRecoveryRequest)->rules(),
      'form_kind' => ['required', Rule::in(RecoveryFormContext::KINDS)],
      'site_id' => ['nullable', 'integer', 'min:1'],
      'page_id' => ['nullable', 'integer', 'min:1'],
      'block_type_id' => ['nullable', 'integer', 'min:1'],
      'slot_type_id' => ['nullable', 'integer', 'min:1'],
      'parent_id' => ['nullable', 'integer', 'min:1'],
      'translation_id' => ['nullable', 'integer', 'min:1'],
    ];
  }
}
