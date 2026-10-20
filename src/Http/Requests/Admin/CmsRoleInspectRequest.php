<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Policies\CmsRolePolicy;

class CmsRoleInspectRequest extends FormRequest
{
  public function authorize(): bool
  {
    return app(CmsRolePolicy::class)->manage($this->user());
  }

  public function rules(): array
  {
    return [
      'user_id' => ['nullable', 'integer', 'min:1'],
      'site_id' => ['nullable', 'integer', 'min:1'],
      'q' => ['nullable', 'string', 'max:160'],
      'status' => ['nullable', Rule::in(['allowed', 'denied'])],
    ];
  }
}
