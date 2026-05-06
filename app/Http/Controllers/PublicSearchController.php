<?php

namespace App\Http\Controllers;

use App\Support\Pages\PageRouteResolver;
use App\Support\Search\PublicSearchQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicSearchController extends Controller
{
    public function __construct(
        private readonly PageRouteResolver $pageRouteResolver,
        private readonly PublicSearchQuery $search,
    ) {}

    public function __invoke(Request $request): View
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

        return view('search.show', [
            'site' => $site,
            'locale' => $locale,
            'query' => $query,
            'results' => $results,
            'state' => $state,
            'minimumLength' => $minimumLength,
            'searchPath' => $this->pageRouteResolver->searchPath($locale->code, $site),
        ]);
    }
}
