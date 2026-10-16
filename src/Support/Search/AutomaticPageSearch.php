<?php

namespace WebBlocks\Cms\Support\Search;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\AutomaticPageAdoption;
use WebBlocks\Cms\Models\AutomaticPageRule;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PublicSearchIndex;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\ContentSources\AutomaticPageCatalogue;
use WebBlocks\Cms\Support\ContentSources\AutomaticPageDesign;
use WebBlocks\Cms\Support\ContentSources\AutomaticPageInspector;
use WebBlocks\Cms\Support\ContentSources\PublicationSources;
use WebBlocks\Cms\Support\Pages\PublicPageAccess;

/** Request-time discovery: no persisted source copy can outlive withdrawal. */
final class AutomaticPageSearch
{
  public function __construct(private readonly SearchTextNormalizer $normalizer) {}

  public function rules(Site $site): Collection
  {
    return Schema::hasTable('wbcms_automatic_page_rules')
      ? AutomaticPageRule::query()->where('site_id', $site->id)->where('status', 'active')->orderBy('id')->get()
      : collect();
  }

  public function results(Collection $rules, Locale $locale, string $query): Collection
  {
    $results = collect();
    $terms = array_filter(preg_split('/\s+/u', mb_strtolower($query)) ?: []);
    foreach ($rules as $rule) {
      if (! $rule->site->enabledLocales()->whereKey($locale->id)->exists()) {
        continue;
      }
      $template = app(AutomaticPageDesign::class)->page($rule);
      if (! $template || app(PublicPageAccess::class)->protected($template)) {
        continue;
      }
      try {
        $records = app(AutomaticPageCatalogue::class)->records($rule);
      } catch (\Throwable $exception) {
        report($exception);
        abort(503);
      }
      $adoptions = AutomaticPageAdoption::query()->where('automatic_page_rule_id', $rule->id)->get()->keyBy('record_key');
      foreach ($records as $key => $record) {
        $adoption = $adoptions->get($key);
        $design = $adoption ? Page::query()->visibleInAdmin()->where('site_id', $rule->site_id)->find($adoption->page_id) : $template;
        if (! $design || app(PublicPageAccess::class)->protected($design)
          || ! app(PublicationSources::class)->find($rule->source_handle, $rule->site, null, $design, false)) {
          continue;
        }
        $fields = $record['configuration']['publication'];
        $values = $record['records'][$locale->code] ?? null;
        if ($values === null) {
          continue;
        }
        // Only declared public publication metadata is searchable. Never ingest
        // arbitrary provider fields, admin labels or the retained native copy.
        $title = $this->normalizer->normalize(data_get($values, $fields['title_field']));
        $content = $fields['description_field'] === null ? '' : $this->normalizer->normalize(data_get($values, $fields['description_field']));
        $url = parse_url(app(AutomaticPageInspector::class)->url($rule, $record['slug'], $locale->code), PHP_URL_PATH);
        $haystack = mb_strtolower($this->normalizer->join([$title, $url, $content]));
        if (collect($terms)->contains(fn ($term) => ! str_contains($haystack, $term))) {
          continue;
        }
        $result = new PublicSearchIndex(['title' => $title, 'content' => $content, 'url' => $url]);
        $like = mb_strtolower($query);
        $result->setAttribute('search_rank', str_contains(mb_strtolower($title), $like) ? 0 : (str_contains(mb_strtolower($url), $like) ? 1 : (str_contains(mb_strtolower($content), $like) ? 2 : 3)));
        $result->setAttribute('display_excerpt', $this->normalizer->excerpt($content, $query));
        $results->push($result);
      }
    }

    return $results->unique('url')->sortBy(fn ($result) => mb_strtolower($result->title).' '.$result->url)->values();
  }
}
