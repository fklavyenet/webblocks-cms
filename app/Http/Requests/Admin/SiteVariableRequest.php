<?php

namespace App\Http\Requests\Admin;

use App\Models\Site;
use App\Models\SiteVariable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiteVariableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'key' => str((string) $this->input('key'))
                ->trim()
                ->snake()
                ->replace('-', '_')
                ->lower()
                ->toString(),
            'label' => $this->normalizeNullableString($this->input('label')),
            'value' => $this->normalizeNullableString($this->input('value'), preserveLineBreaks: true),
            'sort_order' => max(0, (int) $this->input('sort_order', 0)),
            'is_enabled' => $this->boolean('is_enabled', true),
        ]);
    }

    public function rules(): array
    {
        $site = $this->route('site');
        $site = $site instanceof Site ? $site : null;
        $siteVariable = $this->route('site_variable');
        $siteVariable = $siteVariable instanceof SiteVariable ? $siteVariable : null;

        return [
            'key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique(SiteVariable::class, 'key')
                    ->where(fn ($query) => $query->where('site_id', $site?->id))
                    ->ignore($siteVariable?->id),
            ],
            'label' => ['nullable', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_enabled' => ['nullable', 'boolean'],
            '_site_tab' => ['nullable', 'string', 'max:255'],
            '_site_variable_modal' => ['nullable', 'string', 'max:255'],
            '_site_variable_id' => ['nullable', 'integer'],
            '_site_variable_close_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function siteVariableData(): array
    {
        return [
            'key' => (string) $this->string('key'),
            'label' => $this->input('label'),
            'value' => $this->input('value'),
            'sort_order' => max(0, (int) $this->input('sort_order', 0)),
            'is_enabled' => $this->boolean('is_enabled', true),
        ];
    }

    protected function getRedirectUrl(): string
    {
        $site = $this->route('site');

        if ($site instanceof Site) {
            return (string) ($this->input('_site_variable_close_url') ?: route('admin.sites.edit', ['site' => $site, 'tab' => 'variables']));
        }

        return parent::getRedirectUrl();
    }

    private function normalizeNullableString(mixed $value, bool $preserveLineBreaks = false): ?string
    {
        $value = (string) ($value ?? '');
        $value = $preserveLineBreaks
            ? preg_replace("/\r\n?|\n/u", "\n", $value)
            : trim($value);

        if (! is_string($value)) {
            return null;
        }

        $value = $preserveLineBreaks ? trim($value) : $value;

        return $value !== '' ? $value : null;
    }
}
