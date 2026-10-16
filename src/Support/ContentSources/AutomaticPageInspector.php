<?php

namespace WebBlocks\Cms\Support\ContentSources;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Throwable;
use WebBlocks\Cms\Models\AutomaticPageRule;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Support\Pages\PagePath;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;

final class AutomaticPageInspector
{
  public function __construct(private readonly PublicationSources $sources, private readonly AutomaticPageDesign $design) {}

  public function entries(AutomaticPageRule $rule, ?Authenticatable $actor, ?string $cursor = null, bool $preview = true): array
  {
    $page = $this->design->page($rule);
    $config = $page ? $this->sources->find($rule->source_handle, $rule->site, $actor, $page, $preview) : null;
    if (! $config) {
      return ['status' => 'unavailable', 'page' => null];
    }
    try {
      $context = new ContentSourceContext($rule->site, $page, 'en', $preview, $actor);
      $page = app($config['source']->resolverClass())->publicationPage($cursor, 20, $context);
      if (count($page->entries) > 20 || ($cursor !== null && $cursor === $page->nextCursor)) {
        throw new \RuntimeException('Non-advancing publication page.');
      }

      return ['status' => 'available', 'page' => $page];
    } catch (Throwable $e) {
      report($e);

      return ['status' => 'unavailable', 'page' => null];
    }
  }

  public function record(AutomaticPageRule $rule, string $key, ?Authenticatable $actor, bool $preview = true): array
  {
    $page = $this->design->page($rule);
    $config = $page ? $this->sources->find($rule->source_handle, $rule->site, $actor, $page, $preview) : null;
    if (! $config) {
      return ['status' => 'unavailable', 'records' => [], 'locales' => []];
    }
    $records = [];
    $states = [];
    foreach (ContentSourceContract::languages() as $locale) {
      try {
        $context = new ContentSourceContext($rule->site, $page, $locale, $preview, $actor);
        $result = app($config['source']->resolverClass())->publicationRecord($key, $context);
        $states[$locale] = $result->status;
        if ($result->status === 'available') {
          $record = ContentSourceContract::record($config['entity'], $result->record, $context);
          $identity = $config['entity']->contract()['record_key'];
          $slug = data_get($record, $config['publication']['slug_field'], '');
          if (data_get($record, $identity) !== $key || ! is_string($slug) || strlen($slug) > 190 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1
            || trim((string) data_get($record, $config['publication']['title_field'], '')) === '') {
            throw new \InvalidArgumentException('Publication identity or slug mismatch.');
          }
          $records[$locale] = [];
          foreach (array_keys($config['entity']->fieldDefinitions()) as $field) {
            Arr::set($records[$locale], $field, data_get($record, $field));
          }
        }
      } catch (\InvalidArgumentException $e) {
        $states[$locale] = 'incomplete';
      } catch (Throwable $e) {
        report($e);
        $states[$locale] = 'unavailable';
      }
    }
    $status = in_array('unavailable', $states, true) ? 'unavailable' : (in_array('incomplete', $states, true) ? 'incomplete' : (in_array('missing', $states, true) ? 'missing' : 'available'));
    $slugs = array_unique(array_map(fn ($record) => data_get($record, $config['publication']['slug_field']), $records));
    if ($status === 'available' && count($slugs) !== 1) {
      $status = 'incomplete';
    }
    $slug = $status === 'available' ? reset($slugs) : null;
    $conflicts = $slug ? PageTranslation::query()->where('site_id', $rule->site_id)->where('path', $rule->base_path.'/'.$slug)->distinct()->pluck('page_id')->all() : [];

      return ['status' => $status, 'records' => $records, 'locales' => $states, 'slug' => $slug, 'conflicts' => $conflicts, 'configuration' => $config];
  }

  public function url(AutomaticPageRule $rule, string $slug, string $locale): string
  {
    $path = PagePath::canonicalize($rule->base_path.'/'.$slug);

    return rtrim(app(PageRouteResolver::class)->homeUrl($locale, $rule->site), '/').$path;
  }
}
