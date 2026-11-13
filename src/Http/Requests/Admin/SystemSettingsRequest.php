<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Policies\LogFormatPolicy;
use WebBlocks\Cms\Support\Mail\CmsMailSettingsResolver;
use WebBlocks\Cms\Support\System\SystemSettings;

class SystemSettingsRequest extends FormRequest
{
  public function authorize(): bool
  {
    if ($this->input('section') === 'logging') {
      return app(LogFormatPolicy::class)->update($this->user());
    }

    return $this->user() !== null;
  }

  protected function prepareForValidation(): void
  {
    $this->merge([
      'section' => trim((string) $this->input('section')),
      'project_name' => trim((string) $this->input('project_name')),
      'project_tagline' => trim((string) $this->input('project_tagline')),
      'default_locale' => Locale::normalizeCode($this->input('default_locale')),
      'timezone' => trim((string) $this->input('timezone')),
      'admin_listing_per_page' => trim((string) $this->input('admin_listing_per_page')),
      'visitor_consent_banner_enabled' => $this->boolean('visitor_consent_banner_enabled'),
      'cms_mail_mode' => trim((string) $this->input('cms_mail_mode', SystemSettings::CMS_MAIL_MODE_ENV)),
      'cms_mail_mailer' => trim((string) $this->input('cms_mail_mailer', 'smtp')),
      'cms_mail_host' => trim((string) $this->input('cms_mail_host')),
      'cms_mail_port' => trim((string) $this->input('cms_mail_port')),
      'cms_mail_encryption' => trim((string) $this->input('cms_mail_encryption')),
      'cms_mail_username' => trim((string) $this->input('cms_mail_username')),
      'cms_mail_password' => (string) $this->input('cms_mail_password'),
      'cms_mail_clear_password' => $this->boolean('cms_mail_clear_password'),
      'cms_mail_from_address' => trim((string) $this->input('cms_mail_from_address')),
      'cms_mail_from_name' => trim((string) $this->input('cms_mail_from_name')),
      'cms_mail_reply_to_address' => trim((string) $this->input('cms_mail_reply_to_address')),
      'cms_mail_timeout' => trim((string) $this->input('cms_mail_timeout')),
    ]);
  }

  public function rules(): array
  {
    $custom = $this->input('section') === 'mail_custom' || $this->input('cms_mail_mode') === SystemSettings::CMS_MAIL_MODE_CUSTOM;
    $customSmtpRequired = Rule::requiredIf($custom && $this->input('cms_mail_mailer') === 'smtp');

    $sectionRules = [
      'section' => ['required', Rule::in(['general', 'project', 'mail', 'mail_mode', 'mail_custom', 'privacy', 'logging'])],
    ];

    return match ($this->input('section')) {
      'logging' => $sectionRules + ['log_format' => ['required', 'string', Rule::in(['compact', 'full'])]],
      'general' => $sectionRules + [
        'default_locale' => [
          'required',
          'string',
          Rule::exists(Locale::class, 'code')->where(fn ($query) => $query->where('is_enabled', true)),
        ],
        'timezone' => ['required', 'string', Rule::in(array_keys(app(SystemSettings::class)->timezoneOptions()))],
        'admin_listing_per_page' => [
          'required',
          'integer',
          'min:'.SystemSettings::ADMIN_LISTING_PER_PAGE_MIN,
          'max:'.SystemSettings::ADMIN_LISTING_PER_PAGE_MAX,
        ],
      ],
      'project' => $sectionRules + [
        'project_name' => ['nullable', 'string', 'max:255'],
        'project_tagline' => ['nullable', 'string', 'max:255'],
      ],
      'mail_mode' => $sectionRules + [
        'cms_mail_mode' => [
          'bail', 'required', Rule::in([SystemSettings::CMS_MAIL_MODE_ENV, SystemSettings::CMS_MAIL_MODE_CUSTOM]),
          function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === SystemSettings::CMS_MAIL_MODE_CUSTOM
              && ! app(CmsMailSettingsResolver::class)->customSettingsAreComplete()) {
              $fail('webblocks-cms::admin.system_settings.mail_custom_incomplete')->translate();
            }
          },
        ],
      ],
      'mail', 'mail_custom' => $sectionRules + [
        ...($this->input('section') === 'mail' ? [
          'cms_mail_mode' => ['required', Rule::in([SystemSettings::CMS_MAIL_MODE_ENV, SystemSettings::CMS_MAIL_MODE_CUSTOM])],
        ] : []),
        'cms_mail_mailer' => [Rule::requiredIf($custom), Rule::in(CmsMailSettingsResolver::SUPPORTED_MAILERS)],
        'cms_mail_host' => ['nullable', $customSmtpRequired, 'string', 'max:255'],
        'cms_mail_port' => ['nullable', $customSmtpRequired, 'integer', 'min:1', 'max:65535'],
        'cms_mail_encryption' => ['nullable', Rule::in(['', 'tls', 'ssl'])],
        'cms_mail_username' => ['nullable', 'string', 'max:255'],
        'cms_mail_password' => ['nullable', 'string', 'max:1024'],
        'cms_mail_clear_password' => ['required', 'boolean'],
        'cms_mail_from_address' => ['nullable', Rule::requiredIf($custom), 'email', 'max:255'],
        'cms_mail_from_name' => ['nullable', 'string', 'max:255'],
        'cms_mail_reply_to_address' => ['nullable', 'email', 'max:255'],
        'cms_mail_timeout' => ['nullable', 'integer', 'min:1', 'max:300'],
        'cms_mail_pending_mode' => ['nullable', Rule::in([SystemSettings::CMS_MAIL_MODE_ENV, SystemSettings::CMS_MAIL_MODE_CUSTOM])],
      ],
      'privacy' => $sectionRules + [
        'visitor_consent_banner_enabled' => ['required', 'boolean'],
      ],
      default => $sectionRules,
    };
  }

  protected function failedValidation(Validator $validator): void
  {
    if ($this->expectsJson() || ! in_array($this->input('section'), ['mail', 'mail_mode', 'mail_custom'], true)) {
      parent::failedValidation($validator);
    }

    throw new ValidationException($validator, redirect()
      ->route('admin.system.settings.edit', ['tab' => 'mail', 'verification_locale' => $this->query('verification_locale')])
      ->withErrors($validator, $this->errorBag)
      ->withInput($this->only([
        'section', 'cms_mail_mode', 'cms_mail_pending_mode', 'cms_mail_mailer', 'cms_mail_host', 'cms_mail_port',
        'cms_mail_encryption', 'cms_mail_timeout', 'cms_mail_username', 'cms_mail_clear_password', 'cms_mail_from_address',
        'cms_mail_from_name', 'cms_mail_reply_to_address',
      ])));
  }

  public function settingsPayload(): array
  {
    if ($this->validated('section') === 'logging') {
      return [SystemSettings::LOG_FORMAT => $this->validated('log_format')];
    }

    if ($this->validated('section') === 'general') {
      return [
        SystemSettings::DEFAULT_LOCALE => $this->validated('default_locale'),
        SystemSettings::TIMEZONE => $this->validated('timezone'),
        SystemSettings::ADMIN_LISTING_PER_PAGE => $this->validated('admin_listing_per_page'),
      ];
    }

    if ($this->validated('section') === 'project') {
      return [
        SystemSettings::PROJECT_NAME => $this->validated('project_name'),
        SystemSettings::PROJECT_TAGLINE => $this->validated('project_tagline'),
      ];
    }

    if ($this->validated('section') === 'privacy') {
      return [
        SystemSettings::VISITOR_CONSENT_BANNER_ENABLED => $this->validated('visitor_consent_banner_enabled'),
      ];
    }

    if ($this->validated('section') === 'mail_mode' || $this->validated('cms_mail_mode') === SystemSettings::CMS_MAIL_MODE_ENV) {
      return [
        SystemSettings::CMS_MAIL_MODE => $this->validated('cms_mail_mode'),
      ];
    }

    $payload = [
      ...($this->validated('section') === 'mail' ? [SystemSettings::CMS_MAIL_MODE => $this->validated('cms_mail_mode')] : []),
      SystemSettings::CMS_MAIL_MAILER => $this->validated('cms_mail_mailer'),
      SystemSettings::CMS_MAIL_HOST => $this->validated('cms_mail_host'),
      SystemSettings::CMS_MAIL_PORT => $this->validated('cms_mail_port'),
      SystemSettings::CMS_MAIL_ENCRYPTION => $this->validated('cms_mail_encryption'),
      SystemSettings::CMS_MAIL_USERNAME => $this->validated('cms_mail_username'),
      SystemSettings::CMS_MAIL_FROM_ADDRESS => $this->validated('cms_mail_from_address'),
      SystemSettings::CMS_MAIL_FROM_NAME => $this->validated('cms_mail_from_name'),
      SystemSettings::CMS_MAIL_REPLY_TO_ADDRESS => $this->validated('cms_mail_reply_to_address'),
      SystemSettings::CMS_MAIL_TIMEOUT => $this->validated('cms_mail_timeout'),
    ];

    if ($this->validated('section') === 'mail_custom' && $this->validated('cms_mail_mailer') !== 'smtp') {
      return array_diff_key($payload, array_flip([
        SystemSettings::CMS_MAIL_HOST, SystemSettings::CMS_MAIL_PORT, SystemSettings::CMS_MAIL_ENCRYPTION,
        SystemSettings::CMS_MAIL_USERNAME, SystemSettings::CMS_MAIL_TIMEOUT,
      ]));
    }

    if ($this->validated('cms_mail_clear_password')) {
      $payload[SystemSettings::CMS_MAIL_PASSWORD] = null;
    } elseif (trim((string) $this->validated('cms_mail_password')) !== '') {
      $payload[SystemSettings::CMS_MAIL_PASSWORD] = $this->validated('cms_mail_password');
    }

    return $payload;
  }
}
