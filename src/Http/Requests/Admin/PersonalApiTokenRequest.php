<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Support\InternalApiTokens\PersonalApiTokenPolicy;

class PersonalApiTokenRequest extends FormRequest
{
  public function authorize(): bool
  {
    if ($this->user()?->canAccessAdmin() !== true) {
      return false;
    }

    $token = $this->route('token');

    return ! $token instanceof CmsApiToken
      || ($token->isPersonal() && (int) $token->created_by_user_id === (int) $this->user()->id);
  }

  public function rules(): array
  {
    $siteIds = $this->user()->accessibleSiteIds()->map(fn ($id) => (int) $id)->all();

    return [
      'name' => ['required', 'string', 'max:120'],
      'site_ids' => ['required', 'array', 'min:1'],
      'site_ids.*' => ['required', 'integer', Rule::in($siteIds)],
      'capabilities' => ['required', 'array', 'min:1'],
      'capabilities.*' => ['required', 'string', Rule::in(app(PersonalApiTokenPolicy::class)->grantable($this->user()))],
      'expires_in_days' => ['required', 'integer', Rule::in([30, 90, 365])],
    ];
  }
}
