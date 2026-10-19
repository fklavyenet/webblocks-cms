<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Policies\CmsRolePolicy;

class DeleteCmsRoleRequest extends FormRequest
{
  public function authorize(): bool
  {
    $role = $this->route('cmsRole');

    return $role instanceof CmsRole && app(CmsRolePolicy::class)->update($this->user(), $role);
  }

  public function rules(): array
  {
    return ['version' => ['required', 'integer', 'min:1']];
  }
}
