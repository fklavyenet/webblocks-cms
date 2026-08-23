<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Support\Mail\CmsMailSettingsResolver;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;

class SystemSettingsRequest extends FormRequest
{
  public function authorize(): bool
  {
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
      'admin_locale' => Locale::normalizeCode($this->input('admin_locale')),
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
      'backup_cleanup_enabled' => $this->boolean('backup_cleanup_enabled'),
      'backup_cleanup_pre_update_days' => trim((string) $this->input('backup_cleanup_pre_update_days')),
      'backup_cleanup_keep_latest_pre_update' => trim((string) $this->input('backup_cleanup_keep_latest_pre_update')),
      'backup_cleanup_restore_safety_days' => trim((string) $this->input('backup_cleanup_restore_safety_days')),
      'backup_cleanup_content_apply_days' => trim((string) $this->input('backup_cleanup_content_apply_days')),
    ]);
  }

  public function rules(): array
  {
    $customSmtpRequired = Rule::requiredIf(fn (): bool => $this->input('cms_mail_mode') === SystemSettings::CMS_MAIL_MODE_CUSTOM && $this->input('cms_mail_mailer') === 'smtp');

    $sectionRules = [
      'section' => ['required', Rule::in(['general', 'project', 'mail', 'privacy', 'backup-cleanup'])],
    ];

    return match ($this->input('section')) {
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
        'admin_locale' => ['required', 'string', Rule::in(AdminLocaleResolver::SUPPORTED_LOCALES)],
      ],
      'project' => $sectionRules + [
        'project_name' => ['nullable', 'string', 'max:255'],
        'project_tagline' => ['nullable', 'string', 'max:255'],
      ],
      'mail' => $sectionRules + [
        'cms_mail_mode' => ['required', Rule::in([SystemSettings::CMS_MAIL_MODE_ENV, SystemSettings::CMS_MAIL_MODE_CUSTOM])],
        'cms_mail_mailer' => ['required_if:cms_mail_mode,'.SystemSettings::CMS_MAIL_MODE_CUSTOM, Rule::in(CmsMailSettingsResolver::SUPPORTED_MAILERS)],
        'cms_mail_host' => ['nullable', $customSmtpRequired, 'string', 'max:255'],
        'cms_mail_port' => ['nullable', $customSmtpRequired, 'integer', 'min:1', 'max:65535'],
        'cms_mail_encryption' => ['nullable', Rule::in(['', 'tls', 'ssl'])],
        'cms_mail_username' => ['nullable', 'string', 'max:255'],
        'cms_mail_password' => ['nullable', 'string', 'max:1024'],
        'cms_mail_clear_password' => ['required', 'boolean'],
        'cms_mail_from_address' => ['nullable', 'required_if:cms_mail_mode,'.SystemSettings::CMS_MAIL_MODE_CUSTOM, 'email', 'max:255'],
        'cms_mail_from_name' => ['nullable', 'string', 'max:255'],
        'cms_mail_reply_to_address' => ['nullable', 'email', 'max:255'],
        'cms_mail_timeout' => ['nullable', 'integer', 'min:1', 'max:300'],
      ],
      'privacy' => $sectionRules + [
        'visitor_consent_banner_enabled' => ['required', 'boolean'],
      ],
      'backup-cleanup' => $sectionRules + [
        'backup_cleanup_enabled' => ['required', 'boolean'],
        'backup_cleanup_pre_update_days' => ['required', 'integer', 'min:1', 'max:3650'],
        'backup_cleanup_keep_latest_pre_update' => ['required', 'integer', 'min:1', 'max:100'],
        'backup_cleanup_restore_safety_days' => ['required', 'integer', 'min:1', 'max:3650'],
        'backup_cleanup_content_apply_days' => ['required', 'integer', 'min:1', 'max:3650'],
      ],
      default => $sectionRules,
    };
  }

  public function settingsPayload(): array
  {
    if ($this->validated('section') === 'general') {
      return [
        SystemSettings::DEFAULT_LOCALE => $this->validated('default_locale'),
        SystemSettings::TIMEZONE => $this->validated('timezone'),
        SystemSettings::ADMIN_LISTING_PER_PAGE => $this->validated('admin_listing_per_page'),
        SystemSettings::ADMIN_LOCALE => $this->validated('admin_locale'),
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

    if ($this->validated('section') === 'backup-cleanup') {
      return [
        SystemSettings::BACKUP_CLEANUP_ENABLED => $this->validated('backup_cleanup_enabled'),
        SystemSettings::BACKUP_CLEANUP_PRE_UPDATE_DAYS => $this->validated('backup_cleanup_pre_update_days'),
        SystemSettings::BACKUP_CLEANUP_KEEP_LATEST_PRE_UPDATE => $this->validated('backup_cleanup_keep_latest_pre_update'),
        SystemSettings::BACKUP_CLEANUP_RESTORE_SAFETY_DAYS => $this->validated('backup_cleanup_restore_safety_days'),
        SystemSettings::BACKUP_CLEANUP_CONTENT_APPLY_DAYS => $this->validated('backup_cleanup_content_apply_days'),
      ];
    }

    if ($this->validated('cms_mail_mode') === SystemSettings::CMS_MAIL_MODE_ENV) {
      return [
        SystemSettings::CMS_MAIL_MODE => SystemSettings::CMS_MAIL_MODE_ENV,
      ];
    }

    $payload = [
      SystemSettings::CMS_MAIL_MODE => $this->validated('cms_mail_mode'),
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

    if ($this->validated('cms_mail_clear_password')) {
      $payload[SystemSettings::CMS_MAIL_PASSWORD] = null;
    } elseif (trim((string) $this->validated('cms_mail_password')) !== '') {
      $payload[SystemSettings::CMS_MAIL_PASSWORD] = $this->validated('cms_mail_password');
    }

    return $payload;
  }
}
