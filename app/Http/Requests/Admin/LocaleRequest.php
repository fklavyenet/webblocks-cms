<?php

namespace App\Http\Requests\Admin;

use App\Models\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Locale::normalizeCode($this->input('code')),
            'is_default' => $this->boolean('is_default'),
            'is_enabled' => $this->boolean('is_enabled', true),
        ]);
    }

    public function rules(): array
    {
        $locale = $this->route('locale');
        $locale = $locale instanceof Locale ? $locale : null;

        return [
            'code' => ['required', 'string', 'max:10', 'regex:'.Locale::CODE_VALIDATION_PATTERN, Rule::unique(Locale::class, 'code')->ignore($locale?->id)],
            'name' => ['required', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
            'is_enabled' => ['nullable', 'boolean'],
        ];
    }
}
