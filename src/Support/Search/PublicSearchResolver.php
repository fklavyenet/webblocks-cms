<?php

namespace WebBlocks\Cms\Support\Search;

use WebBlocks\Cms\Http\Requests\PublicSearchRequest;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;

final class PublicSearchResolver
{
  public function __construct(private readonly PageRouteResolver $pageRouteResolver, private readonly PublicSearchQuery $search) {}

  public function resolve(PublicSearchRequest $request): array
  {
    $site = $this->pageRouteResolver->currentSite($request);
    $locale = $this->pageRouteResolver->currentLocale($request);
    $query = $this->search->normalize($request->validated('q'));
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
