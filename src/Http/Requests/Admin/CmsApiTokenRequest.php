<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Policies\SystemUpdateApiPolicy;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;

class CmsApiTokenRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->can('access-system') === true;
  }

  protected function prepareForValidation(): void
  {
    $token = $this->route('token');
    $this->merge(['_editing_token' => $token instanceof CmsApiToken ? (string) $token->id : null]);
  }

  public function rules(): array
  {
    return [
      'name' => ['required', 'string', 'max:120'],
      'capabilities' => ['required', 'array', 'min:1'],
      // Core capabilities plus those contributed by enabled plugins.
      'capabilities.*' => ['required', 'string', Rule::in(app(CmsApiTokenCapabilities::class)->grantable())],
    ];
  }

  public function withValidator($validator): void
  {
    $validator->after(function ($validator): void {
      $capabilities = $this->input('capabilities', []);
      if (! is_array($capabilities) || array_intersect($capabilities, [CmsApiTokenCapabilities::SYSTEM_UPDATES_READ, CmsApiTokenCapabilities::SYSTEM_UPDATES_RUN]) === []) {
        return;
      }

      $token = $this->route('token');
      // Older system tokens predate the token_type column. Validate the explicit
      // save against their normalized type without changing site or owner scope.
      if ($token instanceof CmsApiToken) {
        $token = clone $token;
        $token->token_type ??= 'system';
      }
      if ($token instanceof CmsApiToken && ! app(SystemUpdateApiPolicy::class)->allows($token)) {
        $validator->errors()->add('capabilities', __('webblocks-cms::admin.api_tokens.capabilities.system_updates_scope_required'));
      }
    });
  }

  public function tokenName(): string
  {
    return trim((string) $this->validated('name'));
  }

  public function tokenCapabilities(): array
  {
    return array_values(array_unique($this->validated('capabilities')));
  }
}
