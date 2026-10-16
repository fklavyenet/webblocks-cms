<?php

namespace WebBlocks\Cms\Support\ContentSources;

use App\Models\User;
use Throwable;
use WebBlocks\Cms\Models\AutomaticPageRule;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentPublicationRouteSource;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Pages\PublicPageAccess;
use WebBlocks\Cms\Support\Revisions\SnapshotContent;

final class AutomaticPageTransition
{
  public function hash(Page $page): string
  {
    return SnapshotContent::hash(app(PageRevisionManager::class)->snapshotForInspection($page));
  }

  /** A read-only plan. Digest covers source data, native content and rule revision. */
  public function inspect(AutomaticPageRule $rule, User $actor): array
  {
    $errors = [];
    $rows = [];
    $design = app(AutomaticPageDesign::class)->page($rule);
    $config = $design ? app(PublicationSources::class)->find($rule->source_handle, $rule->site, null, $design, false) : null;
    if (! $config || ! is_a($config['source']->resolverClass(), ContentPublicationRouteSource::class, true)) {
      $errors[] = 'routing_unavailable';
    }
    if (! in_array($rule->status, ['draft', 'paused'], true)) {
      $errors[] = 'already_active';
    }
    if (AutomaticPageRule::query()->where('site_id', $rule->site_id)->whereKeyNot($rule->id)->get()->contains(
      fn ($other) => $other->base_path === $rule->base_path || str_starts_with($other->base_path, $rule->base_path.'/') || str_starts_with($rule->base_path, $other->base_path.'/'))) {
      $errors[] = 'overlap';
    }
    if ($design && (app(PublicPageAccess::class)->protected($design) || $rule->site->enabledLocales()->whereNotIn('wbcms_locales.id', $design->translations()->pluck('locale_id'))->exists())) {
      $errors[] = 'design_invalid';
    }
    try {
      $records = $errors === [] ? app(AutomaticPageCatalogue::class)->records($rule) : [];
    } catch (Throwable $exception) {
      report($exception);
      $records = [];
      $errors[] = 'source_unavailable';
    }
    $pages = Page::query()->visibleInAdmin()->where('site_id', $rule->site_id)->whereHas('translations', fn ($q) => $q->where('path', 'like', $rule->base_path.'/%'))->with('translations.locale')->get();
    $seen = [];
    foreach ($records as $key => $record) {
      $path = $rule->base_path.'/'.$record['slug'];
      $matches = $pages->filter(fn ($page) => $page->translations->contains('path', $path));
      $page = $matches->first();
      if ($matches->count() > 1 || ($page && (isset($seen[$page->id]) || $page->translations->contains(fn ($translation) => $translation->path !== $path)
        || $rule->site->enabledLocales()->whereNotIn('wbcms_locales.id', $page->translations->pluck('locale_id'))->exists()
        || app(PublicPageAccess::class)->protected($page) || $page->isSharedSlotSourcePage()))) {
        $errors[] = 'page_conflict';
      }
      if ($page) {
        $seen[$page->id] = true;
      }
      $rows[$key] = ['key' => $key, 'slug' => $record['slug'], 'record' => $record, 'page' => $page, 'hash' => $page ? $this->hash($page) : null];
    }
    if ($pages->contains(fn ($page) => ! isset($seen[$page->id]))) {
      $errors[] = 'unmatched_pages';
    }
    $fingerprint = ['rule' => $rule->only(['id', 'site_id', 'source_handle', 'template_page_id', 'catalogue_page_id', 'base_path', 'revision']),
      'design' => $design ? $this->hash($design) : null,
      'records' => array_map(fn ($row) => [$row['key'], $row['slug'], $row['hash'], $row['record']['records']], $rows), 'errors' => $errors];

    return ['rows' => $rows, 'errors' => array_unique($errors), 'digest' => SnapshotContent::hash($fingerprint),
      'pages' => $pages->count(), 'urls' => count($rows) * $rule->site->enabledLocales()->count()];
  }
}
