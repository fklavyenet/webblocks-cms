<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Support\Locales\LocaleOptionCatalog;

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
    $catalog = app(LocaleOptionCatalog::class);
    $currentOption = $locale ? $catalog->find($locale->code) : null;
    $defaultMode = $locale && ! $currentOption ? 'custom' : 'standard';
    $mode = (string) $this->input('locale_mode', $defaultMode);
    $mode = in_array($mode, ['standard', 'custom'], true) ? $mode : 'standard';
    $localeOption = Locale::normalizeCode($this->input('locale_option'));
    $option = $mode === 'standard'
      ? $catalog->find($localeOption)
      : null;

    $this->merge([
      'locale_mode' => $mode,
      'locale_option' => $localeOption,
      'code' => $option['code'] ?? Locale::normalizeCode($this->input('code')),
      'name' => $option['name'] ?? $this->input('name'),
      'is_default' => $this->boolean('is_default'),
      ...($locale ? [] : ['is_enabled' => true]),
    ]);
  }

  public function rules(): array
  {
    $locale = $this->route('locale');
    $locale = $locale instanceof Locale ? $locale : null;

    $rules = [
      'code' => ['required', 'string', 'max:35', 'regex:'.Locale::CODE_VALIDATION_PATTERN, Rule::unique(Locale::class, 'code')->ignore($locale?->id)],
      'name' => ['required', 'string', 'max:255'],
      'locale_mode' => ['nullable', 'string', Rule::in(['standard', 'custom'])],
      'locale_option' => ['nullable', 'string', 'max:35'],
      'is_default' => ['nullable', 'boolean'],
    ];

    if (! $locale) {
      $rules['is_enabled'] = ['required', 'boolean'];
    }

    return $rules;
  }

  public function withValidator(Validator $validator): void
  {
    $locale = $this->route('locale');

    if ($this->input('locale_mode') === 'custom') {
      return;
    }

    $validator->after(function (Validator $validator): void {
      if (! app(LocaleOptionCatalog::class)->find($this->input('locale_option'))) {
        $validator->errors()->add('locale_option', 'Select a locale from the standard locale list or use custom locale details.');
      }
    });
  }
}
