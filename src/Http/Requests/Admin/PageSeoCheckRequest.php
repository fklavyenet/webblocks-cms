<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Policies\CmsResourcePolicy;
use WebBlocks\Cms\Policies\PageSeoPolicy;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

final class PageSeoCheckRequest extends FormRequest
{
  public function authorize(): bool
  {
    app(CmsResourcePolicy::class)->authorizeRequest($this);

    return app(PageSeoPolicy::class)->check($this->user(), $this->route('page'), $this->attributes->get('cms_api_token'));
  }

  protected function prepareForValidation(): void
  {
    $page = $this->route('page');
    abort_unless($page instanceof Page, 404);
    abort_if($page->isSharedSlotSourcePage() || $page->isRevisionRestoreCandidate(), 404);
    $locale = $this->input('locale', $page->availableSiteLocales()->firstWhere('is_default', true)?->code);
    if (is_string($locale)) {
      $locale = Locale::normalizeCode($locale) ?? trim($locale);
    }
    $this->merge(['locale' => $locale]);
  }

  public function rules(): array
  {
    $page = $this->route('page');
    $locales = $page->availableSiteLocales()->whereIn('id', $page->translations()->pluck('locale_id'))->pluck('code')->all();

    return ['locale' => ['required', 'string', Rule::in($locales)]];
  }

  public function messages(): array
  {
    $message = app(CmsTranslator::class)->get('seo.locale_unavailable', app(AdminLocaleResolver::class)->locale());

    return ['locale.required' => $message, 'locale.string' => $message, 'locale.in' => $message];
  }

  protected function getRedirectUrl(): string
  {
    return route('admin.pages.edit', ['page' => $this->route('page'), 'tab' => 'seo']);
  }
}
