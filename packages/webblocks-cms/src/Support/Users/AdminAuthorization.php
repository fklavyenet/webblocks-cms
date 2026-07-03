<?php

namespace WebBlocks\Cms\Support\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteVariable;

class AdminAuthorization
{
  public function abortUnlessSystem(User $user): void
  {
    abort_unless($user->can('access-system'), 403);
  }

  public function abortUnlessSiteAccess(User $user, Site|Page|NavigationItem|Block|ContactMessage|SharedSlot|int|null $resource): void
  {
    if ($user->isSuperAdmin()) {
      return;
    }

    $siteId = $this->siteIdFor($resource);

    abort_unless($siteId && $user->hasSiteAccess($siteId), 403);
  }

  public function abortUnlessSiteSettingsView(User $user, Site $site): void
  {
    abort_unless($this->canViewSiteSettings($user, $site), 403);
  }

  public function abortUnlessSiteSettingsMutation(User $user, Site $site): void
  {
    abort_unless($this->canMutateSiteSettings($user, $site), 403);
  }

  public function abortUnlessSiteVariableMutation(User $user, Site $site): void
  {
    abort_unless($this->canMutateSiteSettings($user, $site), 403);
  }

  public function canViewSiteSettings(User $user, Site $site): bool
  {
    return $user->isSuperAdmin() || $user->hasSiteAccess($site);
  }

  public function canMutateSiteSettings(User $user, Site $site): bool
  {
    return $user->isSuperAdmin() || ($user->isSiteAdmin() && $user->hasSiteAccess($site));
  }

  public function abortUnlessMediaAccess(User $user, Media $media): void
  {
    if ($user->isSuperAdmin()) {
      return;
    }

    $allowed = $this->scopeMediaForUser(Media::query(), $user)
      ->whereKey($media->id)
      ->exists();

    abort_unless($allowed, 403);
  }

  public function abortUnlessAssetAccess(User $user, Media $media): void
  {
    $this->abortUnlessMediaAccess($user, $media);
  }

  public function filterAllowedMediaIds(User $user, array $mediaIds): array
  {
    $resolvedIds = collect($mediaIds)
      ->map(fn ($id) => (int) $id)
      ->filter(fn ($id) => $id > 0)
      ->unique()
      ->values();

    if ($resolvedIds->isEmpty()) {
      return [];
    }

    $allowedIds = $this->scopeMediaForUser(Media::query(), $user)
      ->whereIn('id', $resolvedIds)
      ->pluck('id')
      ->map(fn ($id) => (int) $id)
      ->flip();

    return $resolvedIds
      ->filter(fn ($id) => $allowedIds->has($id))
      ->values()
      ->all();
  }

  public function filterAllowedAssetIds(User $user, array $assetIds): array
  {
    return $this->filterAllowedMediaIds($user, $assetIds);
  }

  public function normalizeAllowedMediaId(User $user, ?int $mediaId): ?int
  {
    if (! $mediaId || $mediaId < 1) {
      return null;
    }

    return $this->filterAllowedMediaIds($user, [$mediaId])[0] ?? null;
  }

  public function normalizeAllowedAssetId(User $user, ?int $assetId): ?int
  {
    return $this->normalizeAllowedMediaId($user, $assetId);
  }

  public function scopeSitesForUser(Builder $query, User $user): Builder
  {
    if ($user->isSuperAdmin()) {
      return $query;
    }

    return $query->whereIn((new Site)->qualifyColumn('id'), $user->accessibleSiteIds());
  }

  public function scopePagesForUser(Builder $query, User $user): Builder
  {
    $query->visibleInAdmin();

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

  public function scopeSharedSlotsForUser(Builder $query, User $user): Builder
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

    return $query->whereHas('page', fn (Builder $pageQuery) => $pageQuery->whereIn('site_id', $user->accessibleSiteIds()));
  }

  public function scopeMediaForUser(Builder $query, User $user): Builder
  {
    if ($user->isSuperAdmin()) {
      return $query;
    }

    return $query->where(function (Builder $mediaQuery) use ($user): void {
      $mediaQuery
        ->where('uploaded_by', $user->id)
        ->orWhereHas('blocks.page', fn (Builder $pageQuery) => $pageQuery->whereIn('site_id', $user->accessibleSiteIds()))
        ->orWhereHas('blockMedia.block.page', fn (Builder $pageQuery) => $pageQuery->whereIn('site_id', $user->accessibleSiteIds()))
        ->orWhereHas('sitesUsingAsFavicon', fn (Builder $siteQuery) => $siteQuery->whereIn((new Site)->qualifyColumn('id'), $user->accessibleSiteIds()))
        ->orWhereHas('sitesUsingAsSocialImage', fn (Builder $siteQuery) => $siteQuery->whereIn((new Site)->qualifyColumn('id'), $user->accessibleSiteIds()))
        ->orWhereHas('pageTranslationsUsingAsOgImage.page', fn (Builder $pageQuery) => $pageQuery->whereIn('site_id', $user->accessibleSiteIds()));
    });
  }

  public function scopeAssetsForUser(Builder $query, User $user): Builder
  {
    return $this->scopeMediaForUser($query, $user);
  }

  public function scopeContactMessagesForUser(Builder $query, User $user): Builder
  {
    if ($user->isSuperAdmin()) {
      return $query;
    }

    return $query->whereHas('page', fn (Builder $pageQuery) => $pageQuery->whereIn('site_id', $user->accessibleSiteIds()));
  }

  private function siteIdFor(Site|Page|NavigationItem|Block|ContactMessage|SharedSlot|SiteVariable|int|null $resource): ?int
  {
    return match (true) {
      $resource instanceof Site => $resource->id,
      $resource instanceof Page => $resource->site_id,
      $resource instanceof NavigationItem => $resource->site_id,
      $resource instanceof Block => $resource->page?->site_id ?? $resource->page()->value('site_id'),
      $resource instanceof ContactMessage => $resource->page?->site_id ?? $resource->page()->value('site_id'),
      $resource instanceof SharedSlot => $resource->site_id,
      $resource instanceof SiteVariable => $resource->site_id,
      is_numeric($resource) => (int) $resource,
      default => null,
    };
  }
}
