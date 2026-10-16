<?php

namespace WebBlocks\Cms\Support\ContentSources;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Throwable;
use WebBlocks\Cms\Models\AutomaticPageAdoption;
use WebBlocks\Cms\Models\AutomaticPageRule;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentPublicationRouteSource;
use WebBlocks\Cms\Support\Pages\PagePath;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;
use WebBlocks\Cms\Support\Pages\PublicPageAccess;

final class AutomaticPageRouter
{
  public function response(Request $request, string $path): ?Response
  {
    if (! Schema::hasTable('wbcms_automatic_page_rules')) {
      return null;
    }
    $routes = app(PageRouteResolver::class);
    $site = $routes->currentSite($request);
    try {
      $path = PagePath::canonicalize('/'.ltrim($path, '/'));
    } catch (\InvalidArgumentException) {
      return null;
    }
    $rule = AutomaticPageRule::query()->where('site_id', $site->id)->where('status', 'active')->get()
      ->first(fn ($rule) => str_starts_with($path, $rule->base_path.'/'));
    if (! $rule) {
      return null;
    }
    // An active prefix owns its detail URLs. Never resurrect a retained native copy.
    $slug = substr($path, strlen($rule->base_path) + 1);
    abort_unless(preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) === 1, 404);
    $requestedLocale = Locale::normalizeCode((string) $request->route('locale'));
    abort_if($requestedLocale !== null && ! $site->enabledLocales()->where('code', $requestedLocale)->exists(), 404);
    $locale = $routes->currentLocale($request)->code;
    $page = app(AutomaticPageDesign::class)->page($rule);
    $config = $page ? app(PublicationSources::class)->find($rule->source_handle, $site, null, $page, false) : null;
    abort_unless($config && is_a($config['source']->resolverClass(), ContentPublicationRouteSource::class, true), 503);
    try {
      $context = new ContentSourceContext($site, $page, $locale, false, null);
      $key = app($config['source']->resolverClass())->publicationKey($slug, $context);
    } catch (Throwable $exception) {
      report($exception);
      abort(503);
    }
    abort_unless($key !== null, 404);
    abort_unless(trim($key) !== '' && strlen($key) <= 190, 503);
    $inspection = app(AutomaticPageInspector::class)->record($rule, $key, null, false);
    abort_if($inspection['status'] === 'missing', 404);
    abort_unless($inspection['status'] === 'available', 503);
    abort_unless($inspection['slug'] === $slug, 404);
    $adoption = AutomaticPageAdoption::query()->where('automatic_page_rule_id', $rule->id)->where('record_key', $key)->first();
    if ($adoption) {
      $page = Page::query()->visibleInAdmin()->where('site_id', $site->id)->find($adoption->page_id);
    }
    abort_unless($page && app(PublicationSources::class)->find($rule->source_handle, $site, null, $page, false), 503);
    abort_unless(app(PublicPageAccess::class)->allows($page, $request), 403);
    $html = app(AutomaticPagePreview::class)->render($rule, $key, $locale, null, true, $page);

    return response($html)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', app(PublicPageAccess::class)->protected($page) ? 'noindex, noarchive' : 'all');
  }
}
