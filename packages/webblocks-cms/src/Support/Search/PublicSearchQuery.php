<?php

namespace WebBlocks\Cms\Support\Search;

use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Search\PublicSearchSchema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use WebBlocks\Cms\Models\PublicSearchIndex;

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

        $results = PublicSearchIndex::query()
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
            ->orderByRaw('case when LOWER(title) like ? then 0 when LOWER(url) like ? then 1 when LOWER(content) like ? then 2 else 3 end', [$like, $like, $like])
            ->orderByDesc('indexed_at')
            ->orderBy('title')
            ->paginate($perPage)
            ->withQueryString();

        $results->setCollection(
            $results->getCollection()->map(function (PublicSearchIndex $result) use ($normalizedQuery) {
                $result->setAttribute('display_excerpt', $this->normalizer->excerpt($result->content, $normalizedQuery));

                return $result;
            }),
        );

        return $results;
    }
}
