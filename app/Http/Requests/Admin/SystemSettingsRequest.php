<?php

namespace App\Http\Requests\Admin;

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
            'app_name' => trim((string) $this->input('app_name')),
            'app_slogan' => trim((string) $this->input('app_slogan')),
            'default_locale' => Locale::normalizeCode($this->input('default_locale')),
            'timezone' => trim((string) $this->input('timezone')),
            'visitor_consent_banner_enabled' => $this->boolean('visitor_consent_banner_enabled'),
        ]);
    }

    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:255'],
            'app_slogan' => ['nullable', 'string', 'max:255'],
            'default_locale' => [
                'required',
                'string',
                Rule::exists(Locale::class, 'code')->where(fn ($query) => $query->where('is_enabled', true)),
            ],
            'timezone' => ['required', 'string', Rule::in(array_keys(app(SystemSettings::class)->timezoneOptions()))],
            'visitor_consent_banner_enabled' => ['required', 'boolean'],
        ];
    }

    public function settingsPayload(): array
    {
        return [
            SystemSettings::APP_NAME => $this->validated('app_name'),
            SystemSettings::APP_SLOGAN => $this->validated('app_slogan'),
            SystemSettings::DEFAULT_LOCALE => $this->validated('default_locale'),
            SystemSettings::TIMEZONE => $this->validated('timezone'),
            SystemSettings::VISITOR_CONSENT_BANNER_ENABLED => $this->validated('visitor_consent_banner_enabled'),
        ];
    }
}
