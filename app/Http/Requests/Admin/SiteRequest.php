<?php

namespace App\Http\Requests\Admin;

use App\Models\Site;
use App\Support\Sites\SiteDomainNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $domain = app(SiteDomainNormalizer::class)->normalize($this->input('domain'));

        $this->merge([
            'is_primary' => $this->boolean('is_primary'),
            'handle' => str((string) $this->input('handle'))->slug()->toString(),
            'domain' => $domain,
            'locale_ids' => collect($this->input('locale_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        $site = $this->route('site');
        $site = $site instanceof Site ? $site : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'handle' => ['required', 'string', 'max:255', Rule::unique(Site::class, 'handle')->ignore($site?->id)],
            'domain' => ['nullable', 'string', 'max:255', Rule::unique(Site::class, 'domain')->ignore($site?->id)],
            'is_primary' => ['nullable', 'boolean'],
            'locale_ids' => ['required', 'array'],
            'locale_ids.*' => ['integer', 'exists:locales,id'],
        ];
    }
}
