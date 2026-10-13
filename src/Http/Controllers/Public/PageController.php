<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;
use WebBlocks\Cms\Support\Pages\PublicPageAccess;
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;
use WebBlocks\Cms\Support\Visitors\VisitorEventLogger;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PageController extends Controller
{
  public function __construct(
    private readonly PublicPagePresenter $presenter,
    private readonly PageRouteResolver $routeResolver,
    private readonly VisitorEventLogger $visitorEventLogger,
  ) {}

  public function home(Request $request): View|Response
  {
    $homePage = $this->routeResolver->findPublishedPage($request);

    if ($homePage) {
      return $this->renderPage($request, $homePage);
    }

    $localeSegment = (string) $request->route('locale');

    if ($localeSegment !== '') {
      // The {locale} route pattern also matches slug-like segments such as
      // "odd-slug", so a single-segment page whose slug resembles a locale code
      // lands on this /{locale} route instead of the /{slug} page route. When
      // the segment is not a real localized home, resolve it as a top-level
      // page path under the default locale before giving up.
      $request->route()->forgetParameter('locale');

      $page = $this->routeResolver->findPublishedPageByPath($request, '/'.$localeSegment);

      abort_unless($page, 404);

      return $this->renderPage($request, $page);
    }

    return view('welcome');
  }

  public function show(Request $request, string $localeOrPath, ?string $path = null): View|Response
  {
    $resolvedPath = $path ?? $localeOrPath;

    $page = $this->routeResolver->findPublishedPageByPath($request, $resolvedPath);

    abort_unless($page, 404);

    return $this->renderPage($request, $page);
  }

  public function legacy(Request $request, string $localeOrPath, ?string $path = null): RedirectResponse
  {
    $resolvedPath = $path ?? $localeOrPath;
    $canonicalPath = $this->routeResolver->legacyRedirectPath($request, $resolvedPath);

    abort_unless($canonicalPath, 404);

    return redirect($canonicalPath, 301);
  }

  private function renderPage(Request $request, Page $page): View|Response
  {
    abort_if($page->isSharedSlotSourcePage(), 404);
    abort_unless(app(PublicPageAccess::class)->allows($page, $request), 403);

    $page->load([
      'site',
      'translations.locale',
      'slots.slotType',
      'pageAssets',
      'blocks' => fn ($query) => $query
        ->where('status', 'published')
        ->with($this->publishedBlockRelations())
        ->orderBy('sort_order')
        ->orderBy('id'),
    ]);

    $this->visitorEventLogger->logPageView($request, $page);

    $view = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::public.pages.show', $this->presenter->present($page));

    if (app(PublicPageAccess::class)->protected($page)) {
      return response($view)->header('Cache-Control', 'private, no-store')->header('Vary', 'Cookie')->header('X-Robots-Tag', 'noindex, noarchive');
    }
    if ($request->hasSession() && collect($request->session()->all())->has('webblocks_memberships')) {
      return response($view)->header('Cache-Control', 'private, no-store')->header('Vary', 'Cookie');
    }

    return $view;
  }

  private function publishedBlockRelations(): array
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
        ->where('status', 'published')
        ->with($this->publishedBlockRelations())
        ->orderBy('sort_order')
        ->orderBy('id'),
    ];
  }
}
