<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

class PagePreviewRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  protected function prepareForValidation(): void
  {
    $locale = $this->input('locale');

    if (is_string($locale)) {
      $this->merge(['locale' => Locale::normalizeCode($locale) ?? trim($locale)]);
    }
  }

  public function rules(): array
  {
    $page = $this->route('page');
    $locales = $page instanceof Page
      ? $page->availableSiteLocales()->whereIn('id', $page->translations->pluck('locale_id'))->pluck('code')->all()
      : [];

    return ['locale' => ['nullable', 'string', Rule::in($locales)]];
  }

  public function messages(): array
  {
    $message = app(CmsTranslator::class)->admin('page_edit.preview_locale_unavailable', app(AdminLocaleResolver::class)->locale());

    return ['locale.in' => $message, 'locale.string' => $message];
  }
}
