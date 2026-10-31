<?php

namespace WebBlocks\Cms\Support\Navigation;

use Illuminate\Database\Eloquent\Collection as Models;
use Illuminate\Support\Collection;
use UnexpectedValueException;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\NavigationItemTranslation;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;

/** Public attributes only; no HTML, visitor state or serialized model graphs. */
class NavigationSnapshot
{
  public function encode(Collection $items): array
  {
    return $items->map(fn (NavigationItem $item): array => [
      'attributes' => $item->only(['id', 'site_id', 'menu_key', 'parent_id', 'page_id', 'title', 'link_type', 'url', 'target', 'icon', 'position', 'visibility', 'is_system']),
      'site' => $item->site?->only(['id', 'name', 'handle', 'is_primary']),
      'page' => $item->page ? [
        'attributes' => $item->page->only(['id', 'site_id', 'page_type', 'page_type_id', 'status', 'title', 'slug']),
        'translations' => $item->page->translations->map(fn (PageTranslation $translation) => $translation->only(['id', 'site_id', 'page_id', 'locale_id', 'name', 'slug', 'path']))->all(),
      ] : null,
      'translations' => $item->translations->map(fn (NavigationItemTranslation $translation): array => [
        'attributes' => $translation->only(['id', 'navigation_item_id', 'locale_id', 'title']),
        'locale' => $translation->locale?->only(['id', 'code', 'name', 'is_default', 'is_enabled']),
      ])->all(),
      'children' => $this->encode($item->children),
    ])->values()->all();
  }

  public function decode(array $rows, int $depth = 1): Collection
  {
    if (! array_is_list($rows) || ($rows !== [] && $depth > NavigationTree::MAX_DEPTH)) {
      throw new UnexpectedValueException('Invalid cached navigation tree.');
    }
    $connection = (new NavigationItem)->getConnectionName();

    return collect($rows)->map(function (array $row) use ($connection, $depth): NavigationItem {
      if (! isset($row['attributes'], $row['translations'], $row['children']) || ! array_key_exists('site', $row) || ! array_key_exists('page', $row)) {
        throw new UnexpectedValueException('Invalid cached navigation item.');
      }
      $item = (new NavigationItem)->newFromBuilder($row['attributes'], $connection);
      $site = $row['site'] === null ? null : (new Site)->newFromBuilder($row['site'], $connection);
      $item->setRelation('site', $site);
      $page = null;
      if ($row['page'] !== null) {
        $page = (new Page)->newFromBuilder($row['page']['attributes'], $connection);
        $page->setRelation('site', $site);
        $page->setRelation('accessRule', null);
        $page->setRelation('translations', new Models(array_map(fn (array $translation) => (new PageTranslation)->newFromBuilder($translation, $connection), $row['page']['translations'])));
      }
      $item->setRelation('page', $page);
      $item->setRelation('translations', new Models(array_map(function (array $data) use ($connection): NavigationItemTranslation {
        $translation = (new NavigationItemTranslation)->newFromBuilder($data['attributes'], $connection);
        $translation->setRelation('locale', $data['locale'] === null ? null : (new Locale)->newFromBuilder($data['locale'], $connection));

        return $translation;
      }, $row['translations'])));
      $item->setRelation('children', $this->decode($row['children'], $depth + 1));

      return $item;
    });
  }
}
