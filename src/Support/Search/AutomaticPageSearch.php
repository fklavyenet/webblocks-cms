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
    // Turkish has two distinct I/i pairs; Unicode's language-neutral lowercase
    // mapping leaves a combining dot after İ and cannot match typed lowercase.
    $fold = fn (string $text, bool $localized = false): string => mb_convert_case($localized && $locale->code === 'tr' ? strtr($text, ['I' => 'ı', 'İ' => 'i']) : $text, MB_CASE_FOLD, 'UTF-8');
    // Retain standard Unicode matching for international names (MEMBERSHIPS),
    // alongside Turkish matching for translated prose (İhtiyacınız / IŞIK).
    $contains = fn (string $text, string $needle): bool => str_contains($fold($text), $fold($needle))
      || ($locale->code === 'tr' && str_contains($fold($text, true), $fold($needle, true)));
    $terms = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
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
        $haystack = $this->normalizer->join([$title, $url, $content]);
        if (collect($terms)->contains(fn ($term) => ! $contains($haystack, $term))) {
          continue;
        }
        $result = new PublicSearchIndex(['title' => $title, 'content' => $content, 'url' => $url]);
        $result->setAttribute('search_rank', $contains($title, $query) ? 0 : ($contains($url, $query) ? 1 : ($contains($content, $query) ? 2 : 3)));
        $result->setAttribute('display_excerpt', $this->normalizer->excerpt($content, $query));
        $results->push($result);
      }
    }

    return $results->unique('url')->sortBy(fn ($result) => mb_strtolower($result->title).' '.$result->url)->values();
  }
}
