<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;

final class PageTranslationPayload
{
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

    $rules = [
      'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
      'slug' => [
        $creating ? 'required' : 'sometimes',
        'string',
        'max:255',
        'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
        $this->uniquePerSiteLocale('slug', $page, $locale, $translation),
      ],
      'path' => [
        $creating ? 'required' : 'sometimes',
        'string',
        'max:255',
        'regex:/^(?:\/|\/[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*)$/',
        function (string $attribute, mixed $value, \Closure $fail): void {
          if (is_string($value) && PagePath::isReserved($value)) {
            $fail('This path uses a reserved CMS or host route segment.');
          }
        },
        $this->uniquePerSiteLocale('path', $page, $locale, $translation),
      ],
      'og_image_media_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Media::class, 'id')],
    ];

    foreach (self::TEXT_FIELDS as $field => $max) {
      $rules[$field] = ['sometimes', 'nullable', 'string', 'max:'.$max];
    }

    $validator = Validator::make($candidate, $rules, [
      'slug.regex' => 'Use only lowercase letters, numbers, and hyphens.',
      'slug.unique' => 'This slug is already used in this site for this locale.',
      'path.regex' => 'Use a slash path with lowercase letters, numbers, and hyphens only.',
      'path.unique' => 'This path is already used in this site for this locale.',
    ]);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }

    if (array_key_exists('og_image_media_id', $candidate) && $candidate['og_image_media_id'] !== null) {
      $media = Media::query()->find($candidate['og_image_media_id']);

      if (! $media?->isImage()) {
        throw ValidationException::withMessages(['og_image_media_id' => 'The Open Graph image must be an image from Media.'])->errorBag('translation_field');
      }
    }

    return $candidate;
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

  private function uniquePerSiteLocale(string $column, Page $page, Locale $locale, ?PageTranslation $translation)
  {
    return Rule::unique(PageTranslation::class, $column)
      ->ignore($translation?->id)
      ->where(fn ($query) => $query
        ->where('site_id', $page->site_id)
        ->where('locale_id', $locale->id)
      );
  }

  private function nullableTrimmed(mixed $value): ?string
  {
    $value = trim((string) $value);

    return $value !== '' ? $value : null;
  }
}
