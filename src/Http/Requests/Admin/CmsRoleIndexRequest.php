<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Policies\CmsRolePolicy;

class CmsRoleIndexRequest extends FormRequest
{
  public function authorize(): bool
  {
    return app(CmsRolePolicy::class)->manage($this->user());
  }

  public function rules(): array
  {
    return [
      'q' => ['nullable', 'string', 'max:160'],
      'type' => ['nullable', Rule::in(['protected', 'custom'])],
      'roles_page' => ['nullable', 'integer', 'min:1'],
      'history_page' => ['nullable', 'integer', 'min:1'],
    ];
  }
}
