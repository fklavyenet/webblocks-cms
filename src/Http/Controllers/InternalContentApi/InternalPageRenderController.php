<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

/**
 * A tool could build a page and never look at it. The browser admin preview is
 * a session-authenticated route, and admin.render was allowlisted to the System
 * Updates screen, so the only feedback available to an API caller was the JSON
 * it had just written -- which says what was stored, not what renders.
 *
 * This is the same render the admin preview performs: draft blocks included,
 * noindex, and never the public route, so nothing here publishes anything.
 */
class InternalPageRenderController extends Controller
{
  public function __construct(
    private readonly PublicPagePresenter $presenter,
  ) {}

  public function show(Request $request, Page $page): JsonResponse|Response
  {
    // Shared Slot source pages are editing scaffolding rather than real pages;
    // the browser admin preview 404s on them and so does this.
    if ($page->isSharedSlotSourcePage()) {
      return response()->json([
        'ok' => false,
        'code' => 'page_not_renderable',
        'message' => 'Shared Slot source pages are editing scaffolding and are not rendered. Read the Shared Slot instead.',
        'warnings' => [],
        'errors' => [['path' => 'page', 'message' => 'This page backs a Shared Slot.']],
      ], 422);
    }

    $page->load([
      'site',
      'translations.locale',
      'slots.slotType',
      'slots.sharedSlot',
      'pageAssets',
      'blocks' => fn ($query) => $query
        ->with($this->blockRelations())
        ->orderBy('sort_order')
        ->orderBy('id'),
    ]);

    $translation = $this->resolveTranslation($request, $page);

    if ($translation instanceof JsonResponse) {
      return $translation;
    }

    if ($translation) {
      $page->setRelation('currentTranslation', $translation);
    }

    $html = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::public.pages.show', [
      ...$this->presenter->present($page, preview: true),
      'previewMode' => true,
    ])->render();

    if ($request->query('format') === 'html') {
      return response($html, 200, [
        'Content-Type' => 'text/html; charset=UTF-8',
        'X-Robots-Tag' => 'noindex, nofollow',
      ]);
    }

    return response()->json([
      'ok' => true,
      'page' => [
        'id' => $page->id,
        'site_id' => $page->site_id,
        'status' => $page->status,
        'title' => $page->name,
        'locale' => $translation?->locale?->code,
        'path' => $translation?->path,
      ],
      'rendered_at' => now()->toIso8601String(),
      'cms_version' => WebBlocks::version(),
      'format' => 'html',
      'html' => $html,
      '_links' => [
        'self' => '/webadmin/api/pages/'.$page->id.'/render',
        'html' => '/webadmin/api/pages/'.$page->id.'/render?format=html',
        'browser_admin' => route('admin.pages.preview', $page, false),
      ],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  /**
   * @return PageTranslation|JsonResponse|null
   */
  private function resolveTranslation(Request $request, Page $page): mixed
  {
    $requested = trim((string) $request->query('locale', ''));

    if ($requested === '') {
      return $page->defaultTranslation();
    }

    $locale = Locale::query()
      ->when(
        is_numeric($requested),
        fn ($query) => $query->whereKey((int) $requested),
        fn ($query) => $query->where('code', Locale::normalizeCode($requested)),
      )
      ->first();

    $translation = $locale
      ? $page->translations->first(fn (PageTranslation $candidate) => (int) $candidate->locale_id === (int) $locale->id)
      : null;

    if (! $translation) {
      return response()->json([
        'ok' => false,
        'code' => 'page_translation_missing',
        'message' => 'This page has no translation for the requested locale.',
        'available_locales' => $page->translations->map(fn (PageTranslation $candidate) => $candidate->locale?->code)->filter()->values()->all(),
        'warnings' => [],
        'errors' => [['path' => 'locale', 'message' => 'Add it with POST /webadmin/api/pages/{page}/translations/{locale} first.']],
      ], 422);
    }

    return $translation;
  }

  /**
   * @return array<int|string, mixed>
   */
  private function blockRelations(): array
  {
    return [
      'blockType',
      'slotType',
      'asset',
      'blockAssets.asset',
      'textTranslations',
      'buttonTranslations',
      'imageTranslations',
      'contactFormTranslations',
      'children' => fn ($query) => $query
        ->with($this->blockRelations())
        ->orderBy('sort_order')
        ->orderBy('id'),
    ];
  }
}
