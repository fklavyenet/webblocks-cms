<?php

namespace App\Http\Requests\Admin;

use App\Models\Locale;
use App\Models\Site;
use App\Support\Sites\SiteDomainNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class SiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $site = $this->route('site');
        $site = $site instanceof Site ? $site : null;
        $domain = app(SiteDomainNormalizer::class)->normalize($this->input('domain'));
        $defaultLocaleId = (int) Locale::query()->where('is_default', true)->value('id');
        $submittedLocaleIds = collect($this->input('locale_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0);

        $this->merge([
            'is_primary' => $this->boolean('is_primary'),
            'handle' => str((string) $this->input('handle'))->slug()->toString(),
            'domain' => $domain,
            'locale_ids' => $this->normalizedLocaleIds($submittedLocaleIds, $site, $defaultLocaleId)->all(),
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
            'locale_ids' => ['required', 'array', 'min:1'],
            'locale_ids.*' => ['integer', 'exists:locales,id'],
        ];
    }

    private function normalizedLocaleIds(Collection $submittedLocaleIds, ?Site $site, int $defaultLocaleId): Collection
    {
        $fallbackLocaleIds = $site?->locales()->pluck('locales.id') ?? collect();

        $localeIds = $submittedLocaleIds->isNotEmpty()
            ? $submittedLocaleIds
            : $fallbackLocaleIds;

        if ($defaultLocaleId > 0) {
            $localeIds->push($defaultLocaleId);
        }

        return $localeIds
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
    }
}
