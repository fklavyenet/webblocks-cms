<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;

final class PageTranslationPayload
{
  private const MESSAGE_PREFIX = 'webblocks-cms::admin.page_translations.translation_validation.';

  public const TEXT_FIELDS = [
    'seo_title' => 255,
    'seo_description' => 1000,
    'seo_keywords' => 500,
    'list_excerpt' => 300,
    'og_title' => 255,
    'og_description' => 1000,
  ];

  public const IDENTITY_FIELDS = ['name', 'slug', 'path'];

  public const SEO_FIELDS = ['seo_title', 'seo_description', 'seo_keywords', 'list_excerpt', 'og_title', 'og_description', 'og_image_media_id'];

  public function validate(array $payload, Page $page, Locale $locale, ?PageTranslation $translation): array
  {
    $creating = $translation === null;
    $candidate = $this->resolveIdentity($payload, $creating);

    foreach (array_keys(self::TEXT_FIELDS) as $field) {
      if (array_key_exists($field, $payload)) {
        $candidate[$field] = $this->nullableTrimmed($payload[$field]);
      }
    }

    if (array_key_exists('og_image_media_id', $payload)) {
      $candidate['og_image_media_id'] = $payload['og_image_media_id'] === null || $payload['og_image_media_id'] === ''
        ? null
        : (int) $payload['og_image_media_id'];
    }

    $validator = Validator::make(
      $candidate,
      $this->rules($page->site_id, $locale->id, $translation?->id, $creating),
      $this->messages(),
    );

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }

    if ($error = $this->openGraphImageError($candidate['og_image_media_id'] ?? null)) {
      throw ValidationException::withMessages(['og_image_media_id' => $error])->errorBag('translation_field');
    }

    return $candidate;
  }

  public function identityRules(?int $siteId, ?int $localeId, ?int $translationId, bool $requireIdentity = true): array
  {
    $presence = $requireIdentity ? 'required' : 'sometimes';

    $rules = [
      'name' => [$presence, 'string', 'max:255'],
      'slug' => [
        $presence,
        'string',
        'max:255',
        'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
      ],
      'path' => $this->pathRules($requireIdentity),
    ];

    if ($siteId !== null && $localeId !== null) {
      $rules['slug'][] = $this->uniquePerSiteLocale('slug', $siteId, $localeId, $translationId);
      $rules['path'][] = $this->uniquePerSiteLocale('path', $siteId, $localeId, $translationId);
    }

    return $rules;
  }

  public function pathRules(bool $required = true): array
  {
    return [
      'bail',
      $required ? 'required' : 'sometimes',
      'string',
      'max:255',
      'regex:/^(?:\/|\/[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*)$/',
      function (string $attribute, mixed $value, \Closure $fail): void {
        if (is_string($value) && PagePath::isReserved($value)) {
          $fail(__(self::MESSAGE_PREFIX.'reserved_path'));
        }
      },
    ];
  }

  public function rules(int $siteId, int $localeId, ?int $translationId, bool $requireIdentity = true): array
  {
    $rules = $this->identityRules($siteId, $localeId, $translationId, $requireIdentity) + [
      'og_image_media_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Media::class, 'id')],
    ];

    foreach (self::TEXT_FIELDS as $field => $max) {
      $rules[$field] = ['sometimes', 'nullable', 'string', 'max:'.$max];
    }

    return $rules;
  }

  public function messages(): array
  {
    return [
      'slug.regex' => __(self::MESSAGE_PREFIX.'slug_format'),
      'slug.unique' => __(self::MESSAGE_PREFIX.'slug_unique'),
      'path.regex' => __(self::MESSAGE_PREFIX.'path_format'),
      'path.unique' => __(self::MESSAGE_PREFIX.'path_unique'),
    ];
  }

  public function openGraphImageError(?int $mediaId): ?string
  {
    return $mediaId !== null && ! Media::query()->find($mediaId)?->isImage()
      ? __(self::MESSAGE_PREFIX.'open_graph_image')
      : null;
  }

  /**
   * Name, slug and path move together. A caller may send any one of them; the
   * other two are derived the same way the content plan derives them so a
   * translation written here and one written by content apply agree.
   *
   * @param  array<string, mixed>  $payload
   * @return array<string, mixed>
   */
  private function resolveIdentity(array $payload, bool $creating): array
  {
    $identity = [];

    if (array_key_exists('name', $payload)) {
      $identity['name'] = trim((string) $payload['name']);
    }

    $rawPath = array_key_exists('path', $payload) ? trim((string) $payload['path']) : null;
    $rawSlug = array_key_exists('slug', $payload) ? trim((string) $payload['slug']) : null;

    if ($rawPath !== null && $rawPath !== '') {
      try {
        $identity['path'] = PagePath::canonicalize($rawPath);
      } catch (\InvalidArgumentException $exception) {
        throw ValidationException::withMessages(['path' => $exception->getMessage()])->errorBag('translation_field');
      }

      $identity['slug'] = $rawSlug !== null && $rawSlug !== ''
        ? Str::slug($rawSlug)
        : PagePath::slugFromPath($identity['path']);

      return $identity;
    }

    if ($rawSlug !== null && $rawSlug !== '') {
      $identity['slug'] = Str::slug($rawSlug);
      $identity['path'] = PageTranslation::pathFromSlug($identity['slug']);

      return $identity;
    }

    // Creating with only a name is the common case: derive the rest from it,
    // exactly as the admin form does.
    if ($creating) {
      $slug = Str::slug($identity['name'] ?? '');
      $identity['slug'] = $slug;
      $identity['path'] = PageTranslation::pathFromSlug($slug);
    }

    return $identity;
  }

  private function uniquePerSiteLocale(string $column, int $siteId, int $localeId, ?int $translationId): Unique
  {
    return Rule::unique(PageTranslation::class, $column)
      ->ignore($translationId)
      ->where(fn ($query) => $query
        ->where('site_id', $siteId)
        ->where('locale_id', $localeId)
      );
  }

  private function nullableTrimmed(mixed $value): ?string
  {
    $value = trim((string) $value);

    return $value !== '' ? $value : null;
  }
}
