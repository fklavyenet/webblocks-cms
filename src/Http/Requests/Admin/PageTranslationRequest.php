<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Pages\PagePath;
use WebBlocks\Cms\Support\Pages\PageTranslationPayload;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

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
    $path = trim((string) $this->input('path'));
    $authorization = app(AdminAuthorization::class);
    $user = $this->user();

    $input = [
      'slug' => $normalizedSlug,
      'path' => $path !== '' ? $this->normalizePathInput($path) : PageTranslation::pathFromSlug($normalizedSlug),
      'og_image_media_id' => $user ? $authorization->normalizeAllowedMediaId($user, $this->integer('og_image_media_id') ?: $this->integer('og_image_asset_id') ?: null) : null,
    ];
    foreach (array_keys(PageTranslationPayload::TEXT_FIELDS) as $field) {
      $input[$field] = $this->nullableTrimmed($field);
    }

    $this->merge($input);
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

    return app(PageTranslationPayload::class)->rules($siteId, $localeId, $translation?->id);
  }

  public function messages(): array
  {
    return app(PageTranslationPayload::class)->messages();
  }

  public function after(): array
  {
    return [
      function (Validator $validator): void {
        $field = 'og_image_media_id';
        if (! $validator->errors()->has($field)
          && ($error = app(PageTranslationPayload::class)->openGraphImageError($this->input($field)))) {
          $validator->errors()->add($field, $error);
        }
      },
    ];
  }

  public function validatedTranslation(): array
  {
    $data = $this->validated();

    $translation = $data + array_fill_keys(PageTranslationPayload::SEO_FIELDS, null);
    $translation['path'] = PagePath::canonicalize($translation['path']);
    $acl = app(CmsAuthorization::class);
    if ($acl->ready() && ! $acl->allows($this->user(), 'cms.seo.update', $this->route('page')?->site_id)) {
      $translation = array_intersect_key($translation, array_flip(['name', 'slug', 'path', 'list_excerpt']));
    }

    return $translation;
  }

  private function normalizePathInput(string $path): string
  {
    if ($path === '/') {
      return '/';
    }

    return '/'.trim(str_replace('\\', '/', $path), '/');
  }

  private function nullableTrimmed(string $key): ?string
  {
    $value = trim((string) $this->input($key));

    return $value !== '' ? $value : null;
  }
}
