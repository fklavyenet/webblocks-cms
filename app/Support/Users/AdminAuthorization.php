<?php

namespace App\Support\Users;

use App\Models\Block;
use App\Models\Asset;
use App\Models\ContactMessage;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AdminAuthorization
{
    public function abortUnlessSystem(User $user): void
    {
        abort_unless($user->can('access-system'), 403);
    }

    public function abortUnlessSiteAccess(User $user, Site|Page|NavigationItem|Block|ContactMessage|int|null $resource): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        $siteId = $this->siteIdFor($resource);

        abort_unless($siteId && $user->hasSiteAccess($siteId), 403);
    }

    public function abortUnlessAssetAccess(User $user, Asset $asset): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        $allowed = $this->scopeAssetsForUser(Asset::query(), $user)
            ->whereKey($asset->id)
            ->exists();

        abort_unless($allowed, 403);
    }

    public function filterAllowedAssetIds(User $user, array $assetIds): array
    {
        $resolvedIds = collect($assetIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($resolvedIds->isEmpty()) {
            return [];
        }

        return $this->scopeAssetsForUser(Asset::query(), $user)
            ->whereIn('id', $resolvedIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function normalizeAllowedAssetId(User $user, ?int $assetId): ?int
    {
        if (! $assetId || $assetId < 1) {
            return null;
        }

        return $this->filterAllowedAssetIds($user, [$assetId])[0] ?? null;
    }

    public function scopeSitesForUser(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereIn('sites.id', $user->accessibleSiteIds());
    }

    public function scopePagesForUser(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereIn('site_id', $user->accessibleSiteIds());
    }

    public function scopeNavigationForUser(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereIn('site_id', $user->accessibleSiteIds());
    }

    public function scopeBlocksForUser(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $blockQuery) use ($user): void {
            $blockQuery
                ->whereHas('page', fn (Builder $pageQuery) => $pageQuery->whereIn('site_id', $user->accessibleSiteIds()))
                ->orWhereHas('layoutTypeSlot');
        });
    }

    public function scopeAssetsForUser(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $assetQuery) use ($user): void {
            $assetQuery
                ->where('uploaded_by', $user->id)
                ->orWhereHas('blocks.page', fn (Builder $pageQuery) => $pageQuery->whereIn('site_id', $user->accessibleSiteIds()))
                ->orWhereHas('blockAssets.block.page', fn (Builder $pageQuery) => $pageQuery->whereIn('site_id', $user->accessibleSiteIds()));
        });
    }

    public function scopeContactMessagesForUser(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereHas('page', fn (Builder $pageQuery) => $pageQuery->whereIn('site_id', $user->accessibleSiteIds()));
    }

    private function siteIdFor(Site|Page|NavigationItem|Block|ContactMessage|int|null $resource): ?int
    {
        return match (true) {
            $resource instanceof Site => $resource->id,
            $resource instanceof Page => $resource->site_id,
            $resource instanceof NavigationItem => $resource->site_id,
            $resource instanceof Block => $resource->page?->site_id ?? $resource->page()->value('site_id'),
            $resource instanceof ContactMessage => $resource->page?->site_id ?? $resource->page()->value('site_id'),
            is_numeric($resource) => (int) $resource,
            default => null,
        };
    }
}
