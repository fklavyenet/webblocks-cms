<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Policies\RequestSecuritySettingsPolicy;
use WebBlocks\Cms\Support\System\RequestSecuritySettings;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

class RequestSecuritySettingsRequest extends FormRequest
{
  public function authorize(): bool
  {
    return app(RequestSecuritySettingsPolicy::class)->update($this->user());
  }

  public function rules(): array
  {
    $rules = ['mode' => ['required', 'string', Rule::in(['custom', 'defaults'])]];

    foreach (RequestSecuritySettings::DEFINITIONS as $field => $definition) {
      $rules[$field] = ['exclude_unless:mode,custom', 'required', 'integer', 'min:1', 'max:'.$definition['max']];
    }

    return $rules;
  }

  protected function prepareForValidation(): void
  {
    $this->merge(['section' => 'security', 'mode' => $this->input('mode', 'custom')]);
  }

  public function attributes(): array
  {
    $translator = app(CmsTranslator::class);
    $locale = app(AdminLocaleResolver::class)->locale();
    $attributes = [];

    foreach (array_keys(RequestSecuritySettings::DEFINITIONS) as $field) {
      $attributes[$field] = $translator->admin('system_settings.security_'.$field, $locale);
    }

    return $attributes;
  }

  protected function failedValidation(Validator $validator): void
  {
    if ($this->expectsJson()) {
      parent::failedValidation($validator);
    }

    $input = ['section' => 'security'];
    foreach (array_keys(RequestSecuritySettings::DEFINITIONS) as $field) {
      $value = $this->input($field);
      if (is_scalar($value) || $value === null) {
        $input[$field] = $value;
      }
    }

    throw new ValidationException($validator, redirect()
      ->route('admin.system.settings.edit', ['tab' => 'security'])
      ->withErrors($validator, $this->errorBag)
      ->withInput($input));
  }
}
