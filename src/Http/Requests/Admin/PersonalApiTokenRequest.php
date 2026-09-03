<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Support\InternalApiTokens\PersonalApiTokenNetworkPolicy;
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
      'allowed_ip_ranges' => ['nullable', 'array', 'max:20'],
      'allowed_ip_ranges.*' => ['required', 'string', 'max:64', function (string $attribute, mixed $value, \Closure $fail): void {
        if (! app(PersonalApiTokenNetworkPolicy::class)->valid((string) $value)) {
          $fail(__('validation.ip_or_cidr'));
        }
      }],
      'requests_per_minute' => ['required', 'integer', Rule::in([30, 60, 120, 300])],
    ];
  }

  protected function prepareForValidation(): void
  {
    $ranges = $this->input('allowed_ip_ranges');

    if (is_string($ranges)) {
      $this->merge([
        'allowed_ip_ranges' => collect(preg_split('/[\s,]+/', $ranges) ?: [])
          ->map(fn ($range) => trim((string) $range))->filter()->unique()->values()->all(),
      ]);
    }
  }
}
