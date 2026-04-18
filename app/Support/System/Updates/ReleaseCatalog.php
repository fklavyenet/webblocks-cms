<?php

namespace App\Support\System\Updates;

use App\Models\SystemRelease;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ReleaseCatalog
{
    public function __construct(
        private readonly VersionComparator $versionComparator,
    ) {}

    public function latestPublished(string $product, string $channel, ?string $phpVersion = null, ?string $laravelVersion = null): ?SystemRelease
    {
        return $this->versionComparator->latest($this->publishedQuery($product, $channel)->get());
    }

    public function releaseByVersion(string $product, string $version): ?SystemRelease
    {
        return SystemRelease::query()
            ->forProduct($product)
            ->published()
            ->where('version', $version)
            ->first();
    }

    public function releaseHistory(string $product, string $channel, int $limit): LengthAwarePaginator
    {
        return $this->publishedQuery($product, $channel)
            ->orderByDesc('version_normalized')
            ->paginate($limit);
    }

    public function productCatalog(): array
    {
        return SystemRelease::query()
            ->published()
            ->select('product', 'channel')
            ->distinct()
            ->orderBy('product')
            ->orderBy('channel')
            ->get()
            ->groupBy('product')
            ->map(fn ($group, $product): array => [
                'product' => $product,
                'channels' => $group->pluck('channel')->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function publishedQuery(string $product, string $channel): Builder
    {
        return SystemRelease::query()
            ->forProduct($product)
            ->forChannel($channel)
            ->published();
    }
}
