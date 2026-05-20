<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = (string) $this->input('name');
        $slug = (string) $this->input('slug');
        $normalizedSlug = Str::slug($slug !== '' ? $slug : $name);
        $authorization = app(AdminAuthorization::class);
        $user = $this->user();

        $this->merge([
            'slug' => $normalizedSlug,
            'path' => PageTranslation::pathFromSlug($normalizedSlug),
            'seo_title' => $this->nullableTrimmed('seo_title'),
            'seo_description' => $this->nullableTrimmed('seo_description'),
            'seo_keywords' => $this->nullableTrimmed('seo_keywords'),
            'og_title' => $this->nullableTrimmed('og_title'),
            'og_description' => $this->nullableTrimmed('og_description'),
            'og_image_media_id' => $user ? $authorization->normalizeAllowedMediaId($user, $this->integer('og_image_media_id') ?: $this->integer('og_image_asset_id') ?: null) : null,
        ]);
    }

    public function rules(): array
    {
        $page = $this->route('page');
        $page = $page instanceof Page ? $page : null;
        $translation = $this->route('translation');
        $translation = $translation instanceof PageTranslation ? $translation : null;
        $locale = $this->route('locale');
        $locale = $locale instanceof Locale ? $locale : null;
        $siteId = (int) ($page?->site_id ?? 0);
        $localeId = (int) ($translation?->locale_id ?? $locale?->id ?? 0);

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(PageTranslation::class, 'slug')
                    ->ignore($translation?->id)
                    ->where(fn ($query) => $query
                        ->where('site_id', $siteId)
                        ->where('locale_id', $localeId)
                    ),
            ],
            'path' => [
                'required',
                'string',
                'max:255',
                'regex:/^(?:\/|\/p\/[a-z0-9]+(?:-[a-z0-9]+)*)$/',
                Rule::unique(PageTranslation::class, 'path')
                    ->ignore($translation?->id)
                    ->where(fn ($query) => $query
                        ->where('site_id', $siteId)
                        ->where('locale_id', $localeId)
                    ),
            ],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'seo_keywords' => ['nullable', 'string', 'max:500'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:1000'],
            'og_image_media_id' => ['nullable', 'integer', Rule::exists(Media::class, 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Use only lowercase letters, numbers, and hyphens.',
            'slug.unique' => 'This slug is already used in this site for this locale.',
            'path.regex' => 'The generated path format is invalid.',
            'path.unique' => 'This path is already used in this site for this locale.',
        ];
    }

    public function validatedTranslation(): array
    {
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'path' => PageTranslation::pathFromSlug($data['slug']),
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'seo_keywords' => $data['seo_keywords'] ?? null,
            'og_title' => $data['og_title'] ?? null,
            'og_description' => $data['og_description'] ?? null,
            'og_image_media_id' => $data['og_image_media_id'] ?? null,
        ];
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value !== '' ? $value : null;
    }
}
