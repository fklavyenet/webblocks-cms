<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Policies\VerificationEmailPolicy;
use WebBlocks\Cms\Support\Mail\VerificationEmailTemplates;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;

final class VerificationEmailSettingsRequest extends FormRequest
{
  public function authorize(): bool
  {
    return app(VerificationEmailPolicy::class)->update($this->user());
  }

  public function rules(): array
  {
    $rules = [
      'locale' => ['required', 'string', Rule::in(AdminLocaleResolver::SUPPORTED_LOCALES)],
      'intent' => ['required', Rule::in(['save', 'preview', 'reset'])],
    ];

    if ($this->input('intent') === 'reset') {
      return $rules;
    }

    foreach (VerificationEmailTemplates::FIELDS as $field) {
      $singleLine = in_array($field, ['subject', 'greeting', 'salutation'], true);
      $rules[$field] = ['bail', 'required', 'string', 'max:'.($singleLine ? 255 : 2000),
        function (string $attribute, string $value, Closure $fail) use ($singleLine): void {
          $remaining = str_replace(VerificationEmailTemplates::TOKENS, '', $value);
          if (str_contains($remaining, '{') || str_contains($remaining, '}')) {
            $fail(__('webblocks-cms::two-factor.template_invalid_tokens'));
          }
          if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)
            || ($singleLine && preg_match('/[\r\n]/', $value))) {
            $fail(__('webblocks-cms::two-factor.template_invalid_text'));
          }
        },
      ];
    }

    return $rules;
  }

  protected function getRedirectUrl(): string
  {
    $locale = $this->input('locale');

    return route('admin.system.settings.edit', [
      'tab' => 'mail',
      'verification_locale' => is_string($locale) && in_array($locale, AdminLocaleResolver::SUPPORTED_LOCALES, true) ? $locale : 'en',
    ]);
  }
}
