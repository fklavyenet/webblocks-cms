<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;

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
      'admin_locale' => Locale::normalizeCode($this->input('admin_locale')),
    ]);
  }

  public function rules(): array
  {
    return [
      'name' => ['required', 'string', 'max:255'],
      'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($this->user()?->id)],
      'admin_locale' => ['nullable', 'string', Rule::in(AdminLocaleResolver::SUPPORTED_LOCALES)],
    ];
  }
}
