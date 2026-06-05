<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user() !== null;
  }

  protected function prepareForValidation(): void
  {
    $this->merge([
      'email' => str((string) $this->input('email'))->lower()->toString(),
    ]);
  }

  public function rules(): array
  {
    return [
      'name' => ['required', 'string', 'max:255'],
      'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($this->user()?->id)],
    ];
  }
}
