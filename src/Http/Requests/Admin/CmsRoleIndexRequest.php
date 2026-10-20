<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Policies\CmsRolePolicy;
use WebBlocks\Cms\Queries\Access\CmsRoleQuery;

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
      'history_user' => ['nullable', 'integer', 'min:1'],
      'history_site' => ['nullable', 'integer', 'min:1'],
      'history_role' => ['nullable', 'integer', 'min:1'],
      'history_action' => ['nullable', Rule::in(CmsRoleQuery::HISTORY_ACTIONS)],
    ];
  }
}
