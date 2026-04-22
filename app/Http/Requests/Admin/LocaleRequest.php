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
        $locale = $this->route('locale');
        $locale = $locale instanceof Locale ? $locale : null;

        $this->merge([
            'code' => Locale::normalizeCode($this->input('code')),
            'is_default' => $this->boolean('is_default'),
            ...($locale ? [] : ['is_enabled' => true]),
        ]);
    }

    public function rules(): array
    {
        $locale = $this->route('locale');
        $locale = $locale instanceof Locale ? $locale : null;

        $rules = [
            'code' => ['required', 'string', 'max:10', 'regex:'.Locale::CODE_VALIDATION_PATTERN, Rule::unique(Locale::class, 'code')->ignore($locale?->id)],
            'name' => ['required', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ];

        if (! $locale) {
            $rules['is_enabled'] = ['required', 'boolean'];
        }

        return $rules;
    }
}
