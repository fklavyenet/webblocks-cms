<?php

namespace App\Http\Requests\Admin;

use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Support\Users\AdminAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class DuplicatePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $page = $this->route('page');

        return $page instanceof Page
            && $this->user()?->canAccessAdmin();
    }

    protected function prepareForValidation(): void
    {
        $title = trim((string) $this->input('title'));
        $slug = trim((string) $this->input('slug'));

        $translations = collect($this->input('translations', []))
            ->map(function ($translation) {
                $translation = is_array($translation) ? $translation : [];
                $name = trim((string) ($translation['name'] ?? ''));
                $slug = trim((string) ($translation['slug'] ?? ''));

                return [
                    'locale_id' => (int) ($translation['locale_id'] ?? 0),
                    'name' => $name,
                    'slug' => Str::slug($slug !== '' ? $slug : $name),
                ];
            })
            ->values()
            ->all();

        $this->merge([
            'title' => $title,
            'slug' => Str::slug($slug !== '' ? $slug : $title),
            'translations' => $translations,
            'disable_incompatible_shared_slots' => $this->boolean('disable_incompatible_shared_slots'),
        ]);
    }

    public function rules(): array
    {
        return [
            'target_site_id' => ['required', 'integer', 'exists:sites,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale_id' => ['required', 'integer', 'exists:locales,id'],
            'translations.*.name' => ['required', 'string', 'max:255'],
            'translations.*.slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'disable_incompatible_shared_slots' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Use only lowercase letters, numbers, and hyphens.',
            'translations.*.slug.regex' => 'Use only lowercase letters, numbers, and hyphens.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $page = $this->route('page');
            $page = $page instanceof Page ? $page->loadMissing('translations.locale') : null;
            $targetSiteId = (int) $this->input('target_site_id');

            if (! $page || $targetSiteId < 1) {
                return;
            }

            /** @var AdminAuthorization $authorization */
            $authorization = app(AdminAuthorization::class);
            $targetSite = Site::query()->find($targetSiteId);

            if (! $targetSite) {
                return;
            }

            if (! $this->user()?->isSuperAdmin()) {
                try {
                    $authorization->abortUnlessSiteAccess($this->user(), $page);
                    $authorization->abortUnlessSiteAccess($this->user(), $targetSite);
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
                    $validator->errors()->add('target_site_id', 'You must have access to both the current site and the target site to duplicate this page.');

                    return;
                }
            }

            $sourceSecondaryLocaleIds = $page->translations
                ->reject(fn (PageTranslation $translation) => $translation->locale?->is_default)
                ->pluck('locale_id')
                ->sort()
                ->values()
                ->all();
            $submittedSecondaryLocaleIds = collect($this->input('translations', []))
                ->pluck('locale_id')
                ->map(fn ($localeId) => (int) $localeId)
                ->sort()
                ->values()
                ->all();

            if ($sourceSecondaryLocaleIds !== $submittedSecondaryLocaleIds) {
                $validator->errors()->add('translations', 'Provide title and slug values for each non-default source translation.');
            }
        }];
    }

    public function validatedTranslations(): Collection
    {
        $page = $this->route('page');
        $page = $page instanceof Page ? $page->loadMissing('translations.locale') : null;
        $validated = $this->validated();
        $defaultTranslation = $page?->defaultTranslation() ?? $page?->translations->sortBy('locale_id')->first();

        $translations = collect();

        if ($defaultTranslation) {
            $translations->push([
                'locale_id' => $defaultTranslation->locale_id,
                'name' => $validated['title'],
                'slug' => $validated['slug'],
                'path' => PageTranslation::pathFromSlug($validated['slug']),
                'name_field' => 'title',
                'slug_field' => 'slug',
            ]);
        }

        foreach ($validated['translations'] ?? [] as $index => $translation) {
            $translations->push([
                'locale_id' => (int) $translation['locale_id'],
                'name' => $translation['name'],
                'slug' => $translation['slug'],
                'path' => PageTranslation::pathFromSlug($translation['slug']),
                'name_field' => 'translations.'.$index.'.name',
                'slug_field' => 'translations.'.$index.'.slug',
            ]);
        }

        return $translations;
    }
}
