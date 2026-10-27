<?php

namespace WebBlocks\Cms\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Policies\TwoFactorPolicy;
use WebBlocks\Cms\Support\Auth\TwoFactorAuthentication;

final class TwoFactorRequest extends FormRequest
{
  public function authorize(): bool
  {
    if ($this->routeIs('webblocks.two-factor.verify', 'webblocks.two-factor.sendChallenge')) {
      return true; // The challenge controller validates the expiring password-authenticated identity.
    }

    return $this->routeIs('webblocks.two-factor.policy')
      ? app(TwoFactorPolicy::class)->configure($this->user('web'))
      : app(TwoFactorPolicy::class)->manage($this->user('web'));
  }

  public function rules(): array
  {
    if ($this->routeIs('webblocks.two-factor.sendChallenge')) {
      return [];
    }
    if ($this->routeIs('webblocks.two-factor.send')) {
      return ['purpose' => ['required', Rule::in(['setup', 'action'])], 'tab' => ['nullable', Rule::in(['recovery', 'replace', 'disable', 'policy'])]];
    }
    $password = ['current_password' => ['required', 'string', 'current_password:web']];
    $factor = [
      'code' => ['nullable', 'required_without:recovery_code', 'string', 'regex:/^\d{6}$/D'],
      'recovery_code' => ['nullable', 'required_without:code', 'prohibits:code', 'string', 'max:64'],
    ];
    if ($this->routeIs('webblocks.two-factor.start')) {
      return $password + (app(TwoFactorAuthentication::class)->credential($this->user('web')) !== null ? $factor : []);
    }
    if ($this->routeIs('webblocks.two-factor.confirm')) {
      return ['code' => ['required', 'string', 'regex:/^\d{6}$/D']];
    }
    if ($this->routeIs('webblocks.two-factor.policy')) {
      return $password + [
        'require_super_admin' => ['required', 'boolean'],
        'role_ids' => ['nullable', 'array'],
        'role_ids.*' => ['required', 'integer', 'distinct', Rule::exists('wbcms_acl_roles', 'id')],
      ] + (app(TwoFactorAuthentication::class)->credential($this->user('web'))?->confirmed_at !== null ? $factor : []);
    }

    return $factor + ($this->routeIs('webblocks.two-factor.verify', 'webblocks.two-factor.sendChallenge') ? [] : $password);
  }

  public function messages(): array
  {
    $messages = [];
    foreach (['code', 'recovery_code', 'current_password', 'require_super_admin', 'role_ids', 'role_ids.*', 'purpose', 'tab'] as $field) {
      foreach (['required', 'required_without', 'string', 'regex', 'max', 'prohibits', 'current_password', 'boolean', 'array', 'integer', 'distinct', 'exists', 'in'] as $rule) {
        $messages[$field.'.'.$rule] = __('webblocks-cms::two-factor.invalid');
      }
    }

    return $messages;
  }

  protected function failedValidation(Validator $validator): void
  {
    if ($this->expectsJson()) {
      throw new ValidationException($validator, response()->json(['errors' => $validator->errors()], 422));
    }
    $route = $this->routeIs('webblocks.two-factor.verify', 'webblocks.two-factor.sendChallenge') ? 'webblocks.two-factor.challenge'
      : ($this->routeIs('webblocks.two-factor.policy') ? 'admin.system.settings.edit' : 'webblocks.two-factor.show');
    $parameters = $route === 'admin.system.settings.edit' ? ['tab' => 'security'] : [];
    if ($route === 'webblocks.two-factor.show') {
      $parameters = ['tab' => $this->routeIs('webblocks.two-factor.disable') ? 'disable' : ($this->routeIs('webblocks.two-factor.start') ? 'replace' : 'recovery')];
    }
    $response = redirect()->route($route, $parameters)->withErrors($validator);
    if ($route === 'admin.system.settings.edit') {
      $roles = $this->input('role_ids', []);
      $response->withInput(['section' => 'security', 'require_super_admin' => $this->boolean('require_super_admin'),
        'role_ids' => is_array($roles) ? array_values(array_filter($roles, fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)))) : []]);
    }
    throw new ValidationException($validator, $response);
  }
}
