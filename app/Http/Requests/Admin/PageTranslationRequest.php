<?php

namespace App\Http\Requests\Admin;

use App\Models\Locale;
use App\Models\Page;
use App\Models\PageTranslation;
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

        $this->merge([
            'slug' => Str::slug($slug !== '' ? $slug : $name),
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
                Rule::unique(PageTranslation::class, 'slug')
                    ->ignore($translation?->id)
                    ->where(fn ($query) => $query
                        ->where('site_id', $siteId)
                        ->where('locale_id', $localeId)
                    ),
            ],
        ];
    }

    public function validatedTranslation(): array
    {
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'path' => PageTranslation::pathFromSlug($data['slug']),
        ];
    }
}
