<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Policies\EditorRecoveryPolicy;

class EditorRecoveryRequest extends FormRequest
{
  public function authorize(): bool
  {
    return app(EditorRecoveryPolicy::class)->edit($this->user(), $this->route('page'));
  }

  public function rules(): array
  {
    return [
      'locale' => ['required', 'string', 'max:20'],
      'block_id' => ['nullable', 'integer', 'min:1'],
      'base_fingerprint' => ['required_if:_checkpoint,1', 'nullable', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
      'sequence' => ['required_if:_checkpoint,1', 'nullable', 'integer', 'min:1', 'max:2147483646'],
      '_checkpoint' => ['nullable', Rule::in(['1'])],
      'entries' => ['required_if:_checkpoint,1', 'array', 'max:512'],
      'entries.*.name' => ['required', 'string', 'max:190'],
      'entries.*.present' => ['required', 'boolean'],
      'entries.*.value' => ['nullable', 'string', 'max:349528'],
      'reviewed_conflict' => ['nullable', 'boolean'],
    ];
  }
}
