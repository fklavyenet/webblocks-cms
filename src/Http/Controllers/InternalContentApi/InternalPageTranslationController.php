<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiPresenter;
use WebBlocks\Cms\Support\Pages\PagePath;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;

/**
 * Page translations own localized page identity and the page-level SEO and
 * Open Graph overrides. The content plan writes one translation row for one
 * locale at page creation and nothing has been able to touch it since, so a
 * page created through the API could never gain a second locale, be renamed,
 * be moved to another path, or carry any SEO metadata at all.
 *
 * The write shape mirrors the browser admin's PageTranslationRequest. Where it
 * differs, it is because a partial API update is useful and a partial form
 * post is not: PATCH changes only the fields it carries.
 */
class InternalPageTranslationController extends Controller
{
  private const TEXT_FIELDS = [
    'seo_title' => 255,
    'seo_description' => 1000,
    'seo_keywords' => 500,
    'og_title' => 255,
    'og_description' => 1000,
  ];

  public function __construct(
    private readonly InternalContentApiPresenter $presenter,
    private readonly PageRevisionManager $revisionManager,
  ) {}

  public function index(Page $page): JsonResponse
  {
    $page->loadMissing(['translations.locale', 'translations.ogImageMedia']);

    return response()->json([
      'ok' => true,
      'page' => ['id' => $page->id, 'site_id' => $page->site_id],
      'translations' => $page->translations
        ->map(fn (PageTranslation $translation) => $this->presenter->pageTranslation($translation))
        ->values()
        ->all(),
      'warnings' => [],
      'errors' => [],
    ]);
  }

  public function store(Request $request, Page $page, string $locale): JsonResponse
  {
    $resolved = $this->resolveLocale($locale);

    if (! $resolved) {
      return $this->error('locale', 'Locale must resolve to a code or ID.', 'invalid_page_translation');
    }

    if (! $this->localeEnabledForSite($page, $resolved)) {
      return $this->error('locale', 'Locale must be enabled for the page site.', 'invalid_page_translation');
    }

    $existing = $page->translations()->where('locale_id', $resolved->id)->first();

    if ($existing) {
      return $this->error(
        'locale',
        'This page already has a translation for that locale. Use PATCH /webadmin/api/pages/{page}/translations/{translation} to change it.',
        'page_translation_exists',
      );
    }

    $data = $this->validatePayload($request, $page, $resolved, null, creating: true);

    if ($data instanceof JsonResponse) {
      return $data;
    }

    $translation = DB::transaction(function () use ($page, $resolved, $data): PageTranslation {
      $translation = $page->translations()->create(['locale_id' => $resolved->id] + $data);

      $this->touchPage($page, 'Translation added', 'A page translation was added for locale '.$resolved->code.' through the Internal Content API.');

      return $translation;
    });

    return response()->json([
      'ok' => true,
      'translation' => $this->presenter->pageTranslation($translation->fresh(['locale', 'ogImageMedia'])),
      'writes' => [['type' => 'page_translation', 'id' => $translation->id]],
      'warnings' => [],
      'errors' => [],
    ], 201);
  }

  public function update(Request $request, Page $page, PageTranslation $translation): JsonResponse
  {
    if ((int) $translation->page_id !== (int) $page->id) {
      return $this->error('translation', 'Translation must belong to the page.', 'invalid_page_translation');
    }

    $locale = $translation->locale ?: Locale::query()->find($translation->locale_id);

    if (! $locale || ! $this->localeEnabledForSite($page, $locale)) {
      return $this->error('locale', 'Locale must be enabled for the page site.', 'invalid_page_translation');
    }

    $data = $this->validatePayload($request, $page, $locale, $translation, creating: false);

    if ($data instanceof JsonResponse) {
      return $data;
    }

    if ($data === []) {
      return $this->error('translation', 'Provide at least one field to update.', 'invalid_page_translation');
    }

    DB::transaction(function () use ($page, $translation, $data, $locale): void {
      $translation->update($data);

      $this->touchPage($page, 'Translation updated', 'A page translation was updated for locale '.$locale->code.' through the Internal Content API.');
    });

    return response()->json([
      'ok' => true,
      'translation' => $this->presenter->pageTranslation($translation->fresh(['locale', 'ogImageMedia'])),
      'writes' => [['type' => 'page_translation', 'id' => $translation->id]],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  /**
   * @return array<string, mixed>|JsonResponse the fields to write, or the error response
   */
  private function validatePayload(Request $request, Page $page, Locale $locale, ?PageTranslation $translation, bool $creating): array|JsonResponse
  {
    $payload = $request->json()->all();

    $unknown = array_diff(array_keys($payload), ['name', 'slug', 'path', 'og_image_media_id', ...array_keys(self::TEXT_FIELDS)]);

    if ($unknown !== []) {
      return response()->json([
        'ok' => false,
        'code' => 'unsupported_page_translation_fields',
        'message' => 'Page translation updates may only change localized page identity and SEO metadata.',
        'blocked_fields' => array_values($unknown),
        'warnings' => [],
        'errors' => collect($unknown)
          ->map(fn (string $field) => ['path' => $field, 'message' => 'This field is not part of a page translation.'])
          ->values()
          ->all(),
      ], 422);
    }

    $identity = $this->resolveIdentity($payload, $translation, $creating);

    if ($identity instanceof JsonResponse) {
      return $identity;
    }

    $candidate = $identity;

    foreach (self::TEXT_FIELDS as $field => $max) {
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
      return response()->json([
        'ok' => false,
        'code' => 'invalid_page_translation',
        'message' => 'The page translation payload is not valid.',
        'warnings' => [],
        'errors' => collect($validator->errors()->toArray())
          ->map(fn (array $messages, string $field) => ['path' => $field, 'message' => $messages[0] ?? 'Invalid value.'])
          ->values()
          ->all(),
      ], 422);
    }

    if (array_key_exists('og_image_media_id', $candidate) && $candidate['og_image_media_id'] !== null) {
      $media = Media::query()->find($candidate['og_image_media_id']);

      if (! $media?->isImage()) {
        return $this->error('og_image_media_id', 'The Open Graph image must be an image from Media.', 'invalid_page_translation');
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
   * @return array<string, mixed>|JsonResponse
   */
  private function resolveIdentity(array $payload, ?PageTranslation $translation, bool $creating): array|JsonResponse
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
        return $this->error('path', $exception->getMessage(), 'invalid_page_translation');
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

  private function resolveLocale(string $value): ?Locale
  {
    $value = trim($value);

    if ($value === '') {
      return null;
    }

    return Locale::query()
      ->when(
        is_numeric($value),
        fn ($query) => $query->whereKey((int) $value),
        fn ($query) => $query->where('code', Locale::normalizeCode($value)),
      )
      ->first();
  }

  private function localeEnabledForSite(Page $page, Locale $locale): bool
  {
    $page->loadMissing('site');

    return (bool) $page->site?->enabledLocales()
      ->where((new Locale)->qualifyColumn('id'), $locale->id)
      ->exists();
  }

  private function touchPage(Page $page, string $label, string $summary): void
  {
    $page->forceFill(['updated_by_user_id' => null])->save();

    $this->revisionManager->capture(
      $page->fresh(),
      null,
      $label,
      $summary,
      event: 'page_updated',
      source: 'internal-content-api',
    );
  }

  private function nullableTrimmed(mixed $value): ?string
  {
    $value = trim((string) $value);

    return $value !== '' ? $value : null;
  }

  private function error(string $path, string $message, string $code): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'code' => $code,
      'message' => $message,
      'warnings' => [],
      'errors' => [['path' => $path, 'message' => $message]],
    ], 422);
  }
}
