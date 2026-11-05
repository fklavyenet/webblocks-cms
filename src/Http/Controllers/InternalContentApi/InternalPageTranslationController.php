<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiPresenter;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Pages\PageTranslationPayload;

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
  public function __construct(
    private readonly InternalContentApiPresenter $presenter,
    private readonly PageRevisionManager $revisionManager,
    private readonly PageTranslationPayload $payload,
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

    $data = $this->validatePayload($request, $page, $resolved, null);

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

    $data = $this->validatePayload($request, $page, $locale, $translation);

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
  private function validatePayload(Request $request, Page $page, Locale $locale, ?PageTranslation $translation): array|JsonResponse
  {
    $payload = $request->json()->all();

    $unknown = array_diff(array_keys($payload), ['name', 'slug', 'path', 'og_image_media_id', ...array_keys(PageTranslationPayload::TEXT_FIELDS)]);

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

    try {
      return $this->payload->validate($payload, $page, $locale, $translation);
    } catch (ValidationException $exception) {
      return response()->json([
        'ok' => false,
        'code' => 'invalid_page_translation',
        'message' => $exception->errorBag === 'translation_field' ? $exception->getMessage() : 'The page translation payload is not valid.',
        'warnings' => [],
        'errors' => collect($exception->errors())
          ->map(fn (array $messages, string $field) => ['path' => $field, 'message' => $messages[0]])
          ->values()
          ->all(),
      ], 422);
    }
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
