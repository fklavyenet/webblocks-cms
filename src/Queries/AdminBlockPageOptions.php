<?php

namespace WebBlocks\Cms\Queries;

use Illuminate\Database\Eloquent\Builder;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class AdminBlockPageOptions
{
  public const LIMIT = 50;

  public function __construct(private readonly AdminAuthorization $authorization) {}

  public function options($actor, array $filters): array
  {
    return $this->window($actor, $filters)['options'];
  }

  public function window($actor, array $filters): array
  {
    $scope = fn () => $this->authorization->scopePagesForUser(Page::query(), $actor);
    $search = $filters['page_search'];
    $id = preg_match('/^#?(\d+)$/', $search, $matches) === 1 ? (int) $matches[1] : null;
    $paginator = $scope()->with('translations')
      ->whereHas('blocks')
      ->when($filters['site'] !== '', fn (Builder $query) => $query->where('site_id', (int) $filters['site']))
      ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search, $id): void {
        $query->whereHas('translations', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
          $query->where('name', 'like', '%'.$search.'%')->orWhere('path', 'like', '%'.$search.'%')->orWhere('slug', 'like', '%'.$search.'%');
        }))->when($id !== null, fn (Builder $query) => $query->orWhereKey($id));
      }))
      ->orderByDesc('id')
      ->paginate(self::LIMIT, ['*'], 'page_options_page', max(1, (int) ($filters['page_options_page'] ?? 1)))
      ->withQueryString();
    $pages = $paginator->getCollection()->toBase();

    // Keep a previously selected, accessible page even outside the search window.
    if ($filters['page_id'] !== '' && ! $pages->contains('id', (int) $filters['page_id'])) {
      $selected = $scope()->with('translations')->find((int) $filters['page_id']);
      if ($selected) {
        $pages->push($selected);
      }
    }

    $fallback = app(CmsTranslator::class)->admin('blocks_index.page', app(AdminLocaleResolver::class)->locale());

    $options = $pages->mapWithKeys(function (Page $page) use ($fallback): array {
      $translation = $page->defaultTranslation();
      $parts = [trim((string) ($translation?->name ?? $page->title)) ?: $fallback];
      if (trim((string) $translation?->path) !== '') {
        $parts[] = $translation->path;
      }
      $parts[] = '#'.$page->id;

      return [(string) $page->id => implode(' | ', $parts)];
    })->all();

    return ['options' => $options, 'paginator' => $paginator];
  }
}
