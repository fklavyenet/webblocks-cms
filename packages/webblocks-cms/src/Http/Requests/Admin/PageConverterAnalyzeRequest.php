<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\PageConverter\PageConverterAnalyzeInput;
use WebBlocks\Cms\Support\PageConverter\PageConverterProfile;
use WebBlocks\Cms\Support\Pages\PageLayoutManager;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class PageConverterAnalyzeRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->canAccessAdmin() ?? false;
  }

  protected function prepareForValidation(): void
  {
    $this->merge([
      'site_id' => (int) $this->input('site_id'),
      'locale_id' => (int) $this->input('locale_id'),
      'page_layout' => Page::normalizePublicShellHandle((string) $this->input('page_layout', 'default')),
      'page_title' => trim((string) $this->input('page_title')),
      'page_path' => trim((string) $this->input('page_path')),
      'source_html' => trim((string) $this->input('source_html')),
      'conversion_profile' => (string) $this->input('conversion_profile', PageConverterProfile::CONSERVATIVE),
    ]);
  }

  public function rules(): array
  {
    return [
      'site_id' => ['required', 'integer', 'exists:sites,id'],
      'locale_id' => ['required', 'integer', 'exists:locales,id'],
      'page_layout' => ['required', Rule::in(app(PageLayoutManager::class)->activeHandles())],
      'page_title' => ['required', 'string', 'max:255'],
      'page_path' => ['required', 'string', 'max:255', 'regex:/^\/?[A-Za-z0-9][A-Za-z0-9\/_-]*$/'],
      'source_html' => ['nullable', 'string'],
      'source_file' => ['nullable', 'file', 'extensions:html,htm', 'max:2048'],
      'conversion_profile' => ['required', Rule::in(PageConverterProfile::values())],
    ];
  }

  public function after(): array
  {
    return [function (Validator $validator): void {
      $siteId = (int) $this->input('site_id');
      $localeId = (int) $this->input('locale_id');
      $path = (string) $this->input('page_path');
      $sourceHtml = trim((string) $this->input('source_html'));

      if ($sourceHtml === '' && ! $this->hasFile('source_file')) {
        $validator->errors()->add('source_html', 'Paste source HTML or upload an .html/.htm file.');
      }

      if (preg_match('/^https?:\/\//i', $sourceHtml) === 1) {
        $validator->errors()->add('source_html', 'Remote URL fetching is not supported in this Page Converter phase. Paste HTML or upload an HTML file instead.');
      }

      if (str_contains($path, '..') || str_contains($path, '//') || str_contains($path, '\\') || str_contains($path, ':')) {
        $validator->errors()->add('page_path', 'Enter a safe page path or slug without traversal, duplicate slashes, protocols, or backslashes.');
      }

      $site = $siteId > 0 ? Site::query()->find($siteId) : null;

      if (! $site) {
        return;
      }

      /** @var AdminAuthorization $authorization */
      $authorization = app(AdminAuthorization::class);

      try {
        $authorization->abortUnlessSiteAccess($this->user(), $site);
      } catch (HttpException) {
        $validator->errors()->add('site_id', 'You do not have permission to convert pages for the selected site.');

        return;
      }

      if ($localeId > 0 && ! $site->hasEnabledLocale($localeId)) {
        $validator->errors()->add('locale_id', 'Choose an enabled locale for the selected site.');
      }
    }];
  }

  public function toInput(): PageConverterAnalyzeInput
  {
    $validated = $this->validated();
    $uploadedFile = $this->file('source_file');
    $sourceHtml = trim((string) ($validated['source_html'] ?? ''));
    $sourceType = 'textarea';
    $sourceName = 'Pasted HTML';

    if ($sourceHtml === '' && $uploadedFile) {
      $sourceHtml = (string) file_get_contents($uploadedFile->getRealPath());
      $sourceType = 'file';
      $sourceName = $uploadedFile->getClientOriginalName();
    }

    return new PageConverterAnalyzeInput(
      siteId: (int) $validated['site_id'],
      localeId: (int) $validated['locale_id'],
      pageLayout: (string) $validated['page_layout'],
      pageTitle: (string) $validated['page_title'],
      pagePath: (string) $validated['page_path'],
      conversionProfile: (string) $validated['conversion_profile'],
      sourceHtml: $sourceHtml,
      sourceType: $sourceType,
      sourceName: $sourceName,
    );
  }
}
