<?php

namespace WebBlocks\Cms\Http\Requests\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Policies\TwoFactorPolicy;

final class ResetTwoFactorRequest extends FormRequest
{
  public function target(): ?Authenticatable
  {
    $subject = $this->route('user');
    $id = $subject instanceof Authenticatable ? $subject->getAuthIdentifier() : $subject;

    return ctype_digit((string) $id) ? Auth::guard('web')->getProvider()->retrieveById($id) : null;
  }

  public function authorize(): bool
  {
    return app(TwoFactorPolicy::class)->reset($this->user('web'), $this->target());
  }

  protected function prepareForValidation(): void
  {
    // The shared field accepts an email code or a recovery code. Keep legacy
    // recovery_code submissions compatible and reject submissions of both.
    $code = $this->input('code');
    if (is_string($code) && $code !== '' && ! preg_match('/^[0-9]{6}$/D', $code) && ! $this->filled('recovery_code')) {
      $this->merge(['code' => null, 'recovery_code' => $code]);
    }
  }

  public function rules(): array
  {
    return [
      'current_password' => ['required', 'string', 'current_password:web'],
      'code' => ['nullable', 'required_without:recovery_code', 'string', 'regex:/^\d{6}$/D'],
      'recovery_code' => ['nullable', 'required_without:code', 'prohibits:code', 'string', 'max:64'],
      'reason' => ['required', 'string', 'max:500'],
      'recovery_email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->target()?->getAuthIdentifier())],
    ];
  }

  public function messages(): array
  {
    $messages = [];
    foreach (array_keys($this->rules()) as $field) {
      foreach (['required', 'string', 'current_password', 'required_without', 'regex', 'prohibits', 'max', 'email', 'unique'] as $rule) {
        $messages[$field.'.'.$rule] = __('webblocks-cms::two-factor.reset_invalid');
      }
    }

    return $messages;
  }

  protected function failedValidation(Validator $validator): void
  {
    $response = $this->expectsJson() ? response()->json(['errors' => $validator->errors()], 422)
      : to_route('admin.users.edit', ['user' => $this->route('user')])->withErrors($validator);
    // Do not flash passwords, email codes, recovery codes or free-form audit reasons.
    throw new ValidationException($validator, $response);
  }
}
