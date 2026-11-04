<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Http\Requests\PublicSearchRequest;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;
use WebBlocks\Cms\Support\Search\PublicSearchQuery;
use WebBlocks\Cms\Support\Search\PublicSearchResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

class PublicSearchController extends Controller
{
  public function __construct(
    private readonly PageRouteResolver $pageRouteResolver,
    private readonly PublicSearchQuery $search,
    private readonly CmsTranslator $translator,
  ) {}

  public function __invoke(PublicSearchRequest $request): RedirectResponse
  {
    $site = $this->pageRouteResolver->currentSite($request);
    $locale = $this->pageRouteResolver->currentLocale($request);
    $home = $this->pageRouteResolver->homePath($locale->code, $site);
    abort_unless($home, 404);
    $query = $this->search->normalize($request->validated('q'));

    return redirect($home.'?'.http_build_query(['cms_search' => 1, 'q' => $query]).'#site-search')
      ->header('Cache-Control', 'private, no-store');
  }

  public function json(PublicSearchRequest $request): JsonResponse
  {
    $search = $this->resolveSearch($request);
    $query = $search['query'];
    $results = $search['results'];
    $minimumLength = $search['minimumLength'];
    $localeCode = $search['locale']->code;

    return response()->json([
      'query' => $query,
      'count' => $results?->total() ?? 0,
      'minimum_length' => $minimumLength,
      'results' => $results?->getCollection()->map(fn ($result) => [
        'title' => (string) $result->title,
        'url' => (string) $result->url,
        'excerpt' => (string) ($result->display_excerpt ?? ''),
      ])->values()->all() ?? [],
      'no_results' => $search['state'] === 'no-results'
        ? $this->translator->public('search.no_results', $localeCode, ['query' => $query])
        : null,
      'minimum_query_length' => $search['state'] === 'short'
        ? $this->translator->public('search.minimum_query_length', $localeCode, ['count' => $minimumLength])
        : null,
    ]);
  }

  public function resolveSearch(PublicSearchRequest $request): array
  {
    return app(PublicSearchResolver::class)->resolve($request);
  }
}
