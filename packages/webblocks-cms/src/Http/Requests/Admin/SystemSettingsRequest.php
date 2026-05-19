<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use App\Models\Locale;
use App\Support\System\SystemSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'project_name' => trim((string) $this->input('project_name')),
            'project_tagline' => trim((string) $this->input('project_tagline')),
            'default_locale' => Locale::normalizeCode($this->input('default_locale')),
            'timezone' => trim((string) $this->input('timezone')),
            'admin_listing_per_page' => trim((string) $this->input('admin_listing_per_page')),
            'visitor_consent_banner_enabled' => $this->boolean('visitor_consent_banner_enabled'),
        ]);
    }

    public function rules(): array
    {
        return [
            'project_name' => ['nullable', 'string', 'max:255'],
            'project_tagline' => ['nullable', 'string', 'max:255'],
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
            'visitor_consent_banner_enabled' => ['required', 'boolean'],
        ];
    }

    public function settingsPayload(): array
    {
        return [
            SystemSettings::PROJECT_NAME => $this->validated('project_name'),
            SystemSettings::PROJECT_TAGLINE => $this->validated('project_tagline'),
            SystemSettings::DEFAULT_LOCALE => $this->validated('default_locale'),
            SystemSettings::TIMEZONE => $this->validated('timezone'),
            SystemSettings::ADMIN_LISTING_PER_PAGE => $this->validated('admin_listing_per_page'),
            SystemSettings::VISITOR_CONSENT_BANNER_ENABLED => $this->validated('visitor_consent_banner_enabled'),
        ];
    }
}
