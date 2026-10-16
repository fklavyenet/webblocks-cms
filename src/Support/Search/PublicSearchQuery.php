<?php

namespace WebBlocks\Cms\Support\Search;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PublicSearchIndex;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Pages\PublicPageAccess;

class PublicSearchQuery
{
  public function __construct(
    private readonly SearchTextNormalizer $normalizer,
    private readonly PublicSearchSchema $schema,
  ) {}

  public function normalize(?string $query): string
  {
    return $this->normalizer->query($query);
  }

  public function isSearchable(?string $query, int $minimumLength = 2): bool
  {
    return mb_strlen($this->normalize($query)) >= $minimumLength;
  }

  public function search(Site $site, Locale $locale, string $query, int $perPage = 10): LengthAwarePaginator
  {
    if (! $this->schema->tableExists()) {
      return new Paginator([], 0, $perPage, 1, [
        'path' => request()->url(),
        'query' => request()->query(),
      ]);
    }

    $normalizedQuery = $this->normalize($query);
    $terms = collect(preg_split('/\s+/u', $normalizedQuery) ?: [])
      ->filter()
      ->unique()
      ->values();
    $like = '%'.mb_strtolower($normalizedQuery).'%';
    $rankSql = 'case when LOWER(title) like ? then 0 when LOWER(url) like ? then 1 when LOWER(content) like ? then 2 else 3 end';
    $rankBindings = [$like, $like, $like];

    $automatic = app(AutomaticPageSearch::class);
    $rules = $automatic->rules($site);
    $builder = PublicSearchIndex::query()
      ->with(['page.site', 'page.translations.locale'])
      ->whereHas('page', fn ($pages) => app(PublicPageAccess::class)->scopePublic($pages)->where('status', Page::STATUS_PUBLISHED))
      ->where('site_id', $site->id)
      ->where('locale_id', $locale->id)
      ->where(function ($queryBuilder) use ($terms) {
        foreach ($terms as $term) {
          $termLike = '%'.mb_strtolower($term).'%';

          $queryBuilder->where(function ($nested) use ($termLike) {
            $nested->whereRaw('LOWER(title) LIKE ?', [$termLike])
              ->orWhereRaw('LOWER(url) LIKE ?', [$termLike])
              ->orWhereRaw('LOWER(content) LIKE ?', [$termLike]);
          });
        }
      })
      ->orderByRaw($rankSql, $rankBindings)
      ->orderByDesc('indexed_at')
      ->orderBy('title')->orderBy('id');

    foreach ($rules as $rule) {
      // Active prefixes own these URLs, even if a native copy is republished.
      // Compare canonical translation paths, not potentially stale index URLs.
      $prefix = $rule->base_path.'/';
      $builder->whereDoesntHave('page.translations', fn ($translations) => $translations
        ->where('locale_id', $locale->id)->whereRaw('SUBSTR(path, 1, ?) = ?', [mb_strlen($prefix), $prefix]));
    }
    if ($rules->isEmpty()) {
      $results = $builder->paginate($perPage)->withQueryString();
    } else {
      $sourceResults = $automatic->results($rules, $locale, $normalizedQuery);
      $page = max(1, Paginator::resolveCurrentPage());
      $counts = (clone $builder)->reorder()->selectRaw($rankSql.' as search_rank, COUNT(*) as aggregate', $rankBindings)
        ->groupBy('search_rank')->pluck('aggregate', 'search_rank');
      $total = $counts->sum() + $sourceResults->count();
      $offset = ($page - 1) * $perPage;
      $items = collect();
      // Merge relevance buckets without loading preceding native pages into
      // memory. Native recency/title order wins ties; source titles are stable.
      foreach (range(0, 3) as $rank) {
        $nativeCount = (int) $counts->get($rank, 0);
        $source = $sourceResults->where('search_rank', $rank)->values();
        if ($offset >= $nativeCount + $source->count()) {
          $offset -= $nativeCount + $source->count();

          continue;
        }
        if ($offset < $nativeCount) {
          $items = $items->concat((clone $builder)->whereRaw('('.$rankSql.') = ?', [...$rankBindings, $rank])
            ->offset($offset)->limit($perPage - $items->count())->get());
        }
        $offset = max(0, $offset - $nativeCount);
        $items = $items->concat($source->slice($offset, $perPage - $items->count()));
        $offset = 0;
        if ($items->count() >= $perPage) {
          break;
        }
      }
      $results = new Paginator($items->values(), $total, $perPage, $page, [
        'path' => request()->url(), 'query' => request()->query(),
      ]);
    }

    $results->setCollection(
      $results->getCollection()->map(function (PublicSearchIndex $result) use ($normalizedQuery, $locale) {
        // The index can predate a deployment mount change. Resolve outgoing URLs
        // from page identity rather than returning a stale stored index URL.
        if ($result->page_id !== null) {
          $result->setAttribute('url', $result->page->publicPath($locale->code));
        }
        $result->setAttribute('display_excerpt', $this->normalizer->excerpt($result->content, $normalizedQuery));

        return $result;
      }),
    );

    return $results;
  }
}
