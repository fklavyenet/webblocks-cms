<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Policies\CmsRolePolicy;
use WebBlocks\Cms\Support\Access\PermissionCatalog;

class CmsRoleRequest extends FormRequest
{
  public function authorize(): bool
  {
    $role = $this->route('cmsRole');

    return $role ? app(CmsRolePolicy::class)->update($this->user(), $role) : app(CmsRolePolicy::class)->manage($this->user());
  }

  public function rules(): array
  {
    return [
      'name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:2000'],
      'version' => [$this->route('cmsRole') ? 'required' : 'nullable', 'integer', 'min:1'],
      'permissions' => ['nullable', 'array'], 'permissions.*' => ['required', 'string', 'distinct', Rule::in(array_keys(app(PermissionCatalog::class)->editable()))],
    ];
  }

  public function after(): array
  {
    return [function ($validator): void {
      $permissions = $this->input('permissions', []);
      if (! is_array($permissions) || $permissions === []) {
      return;
      }
      if (! in_array('cms.panel.access', $permissions, true)) {
      $validator->errors()->add('permissions', __('webblocks-cms::acl.panel_required'));
      }
      foreach ($permissions as $permission) {
        if (! is_string($permission) || ! str_starts_with($permission, 'cms.')) {
        continue;
        }
        $group = explode('.', $permission)[1];
        $read = 'cms.'.$group.'.read';
        if (isset(app(PermissionCatalog::class)->editable()[$read]) && ! in_array($read, $permissions, true)) {
          $validator->errors()->add('permissions', __('webblocks-cms::acl.read_required', ['permission' => $read]));
        }
      }
    }];
  }
}
