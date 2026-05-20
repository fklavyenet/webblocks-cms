<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;
use WebBlocks\Cms\Support\Search\PublicSearchQuery;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PublicSearchController extends Controller
{
    public function __construct(
        private readonly PageRouteResolver $pageRouteResolver,
        private readonly PublicSearchQuery $search,
    ) {}

    public function __invoke(Request $request): View
    {
        $search = $this->resolveSearch($request);

        return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::public.search.show', [
            'site' => $search['site'],
            'locale' => $search['locale'],
            'query' => $search['query'],
            'results' => $search['results'],
            'state' => $search['state'],
            'minimumLength' => $search['minimumLength'],
            'searchPath' => $search['searchPath'],
        ]);
    }

    public function json(Request $request): JsonResponse
    {
        $search = $this->resolveSearch($request);
        $query = $search['query'];
        $results = $search['results'];
        $minimumLength = $search['minimumLength'];

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
                ? sprintf('No results matched %s.', $query)
                : null,
            'minimum_query_length' => $search['state'] === 'short'
                ? sprintf('Enter at least %d characters to search.', $minimumLength)
                : null,
        ]);
    }

    private function resolveSearch(Request $request): array
    {
        $site = $this->pageRouteResolver->currentSite($request);
        $locale = $this->pageRouteResolver->currentLocale($request);
        $query = $this->search->normalize($request->query('q'));
        $results = null;
        $minimumLength = 2;
        $state = $query === '' ? 'empty' : 'short';

        if ($this->search->isSearchable($query, $minimumLength)) {
            $results = $this->search->search($site, $locale, $query);
            $state = $results->isEmpty() ? 'no-results' : 'results';
        }

        return [
            'site' => $site,
            'locale' => $locale,
            'query' => $query,
            'results' => $results,
            'state' => $state,
            'minimumLength' => $minimumLength,
            'searchPath' => $this->pageRouteResolver->searchPath($locale->code, $site),
            'searchJsonPath' => $this->pageRouteResolver->searchJsonPath($locale->code, $site),
        ];
    }
}
