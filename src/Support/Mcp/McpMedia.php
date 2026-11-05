<?php

namespace WebBlocks\Cms\Support\Mcp;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Policies\McpPagePolicy;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

final class McpMedia
{
  public function __construct(
    private readonly McpPagePolicy $policy,
    private readonly AdminAuthorization $authorization,
  ) {}

  public function find(User $user, int $siteId, array $input): array
  {
    $query = $this->images($user, $siteId);
    if (isset($input['q'])) {
      $pattern = '%'.strtr($input['q'], ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
      $query->where(fn (Builder $query) => $query->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])
        ->orWhereRaw("original_name LIKE ? ESCAPE '!'", [$pattern]));
    }
    $images = $query->orderBy('id')->simplePaginate(20, ['*'], 'page', $input['page'] ?? 1);

    return [
      'images' => $images->getCollection()->map(fn (Media $media) => [
        'media_id' => $media->id,
        'title' => $media->displayTitle(),
        'width' => $media->width,
        'height' => $media->height,
      ])->all(),
      'next_page' => $images->hasMorePages() ? $images->currentPage() + 1 : null,
    ];
  }

  public function selectedImage(User $user, int $siteId, int $id): Media
  {
    return $this->images($user, $siteId)->whereKey($id)->lockForUpdate()->first()
      ?? throw new McpToolException('media_unavailable');
  }

  private function images(User $user, int $siteId): Builder
  {
    if (! $this->policy->readMedia($user, $siteId)) {
      throw new McpToolException('forbidden');
    }

    return $this->authorization->scopeMediaForUser(Media::query(), $user)
      ->where('kind', Media::KIND_IMAGE)->where('visibility', 'public')
      ->where(fn (Builder $query) => $query
        ->where(fn (Builder $unused) => $unused->where('uploaded_by', $user->id)->withoutSiteUsage())
        ->orWhere(fn (Builder $siteMedia) => $siteMedia->usedBySites([$siteId])));
  }
}
