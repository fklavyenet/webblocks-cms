<?php

namespace WebBlocks\Cms\Support\SystemPages;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemPage;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;
use WebBlocks\Cms\Support\Pages\PublicMount;
use WebBlocks\Cms\Support\Pages\PublicPageAccess;
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;

final class SystemPageRenderer
{
  public function identity(Page $page): ?string
  {
    if (! $page->isSystemPage() || ! Schema::hasTable('wbcms_system_pages')) {
      return null;
    }
    $sourceId = (int) $page->setting('staged_update.source_page_id', $page->id);

    return SystemPage::query()->where('site_id', $page->site_id)->where('page_id', $sourceId)->value('key');
  }

  public function design(Site $site, string $key): ?Page
  {
    if (! app(SystemPageRegistry::class)->find($key) || ! Schema::hasTable('wbcms_system_pages')) {
      return null;
    }

    return SystemPage::query()->where('site_id', $site->id)->where('key', $key)->first()?->page()
      ->where('site_id', $site->id)->where('page_type', Page::TYPE_SYSTEM)->where('status', Page::STATUS_PUBLISHED)->first();
  }

  public function data(Page $page, ?string $locale = null): array
  {
    $page->load([
      'site', 'translations.locale', 'slots.slotType', 'slots.sharedSlot', 'pageAssets',
      'blocks' => fn ($query) => $query->where('status', 'published')->with([
        'blockType', 'slotType', 'asset', 'blockAssets.asset', 'textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations',
      ])->orderBy('sort_order')->orderBy('id'),
    ]);
    $children = $page->blocks->groupBy('parent_id');
    foreach ($page->blocks as $block) {
      $block->setRelation('children', $children->get($block->id, collect())->values());
    }
    $translation = $page->translationForLocale($locale) ?? $page->defaultTranslation();
    if ($translation) {
      $page->setRelation('currentTranslation', $translation);
    }

    $data = app(PublicPagePresenter::class)->present($page, locale: $locale);

    return [...$data, 'systemPageRuntime' => true, 'systemPageHasContent' => $this->hasContent($data['slots'])];
  }

  private function hasContent(iterable $slots): bool
  {
    return collect($slots)->contains(fn ($slot) => ($slot['slug'] ?? null) === 'main' && collect($slot['blocks'] ?? [])->isNotEmpty());
  }

  public function previewContent(Page $page, string $locale): string
  {
    return in_array($this->identity($page), ['core:error-404', 'core:error-503'], true)
      ? '' : view('webblocks-cms::pages.partials.system-page-preview', ['locale' => $locale])->render();
  }

  /** The controller retains its form, status, headers and all authorization. */
  public function compose(View $view): void
  {
    $values = $view->getData();
    if (isset($values['page'])) {
      if ($values['page']->isSystemPage() && ($values['previewMode'] ?? false)) {
        $view->with('systemPageRuntime', true)->with('systemPagePreview', true)
          ->with('systemPageHasContent', $this->hasContent($values['slots'] ?? []));
      }

      return;
    }
    $definition = app(SystemPageRegistry::class)->forRoute(request()->route()?->getName());
    if (! $definition) {
      return;
    }
    $routes = app(PageRouteResolver::class);
    $site = $routes->currentSite(request());
    // A controller may intentionally resolve a resource from another site. Never wrap
    // it in the current host's design or expose a foreign site's shared content.
    if (isset($values['site']) && (int) $values['site']->id !== (int) $site->id) {
      return;
    }
    $page = $this->design($site, $definition->key);
    if (! $page || ! app(PublicPageAccess::class)->allows($page, request())) {
      return;
    }
    $data = $this->data($page, $values['publicLocaleCode'] ?? $routes->currentLocale(request())->code);
    // Feature titles and response metadata still describe the actual transaction.
    foreach (['publicMeta', 'publicLocaleCode'] as $key) {
      if (isset($values[$key])) {
        $data[$key] = $values[$key];
      }
    }
    if (isset($values['title']) && ! isset($values['publicMeta'])) {
      $data['publicMeta']['title'] = $values['title'];
      $data['publicMeta']['og_title'] = $values['title'];
    }
    $view->with($data);
  }

  public function notFound(Request $request): ?string
  {
    // Error handling must survive a missing schema, broken plugin, or unavailable DB.
    try {
      $routes = app(PageRouteResolver::class);
      $site = $routes->currentSite($request);
      $locales = app(LocaleResolver::class);
      $prefix = app(PublicMount::class)->prefix();
      $path = $prefix !== '' && str_starts_with($request->getPathInfo(), $prefix.'/') ? substr($request->getPathInfo(), strlen($prefix)) : $request->getPathInfo();
      $segment = explode('/', trim($path, '/'))[0] ?? '';
      $locale = $locales->enabled($segment, $site) ?? $locales->default();
      $page = $this->design($site, 'core:error-404');
      if (! $page || ! app(PublicPageAccess::class)->allows($page, $request)) {
        return null;
      }

      $data = $this->data($page, $locale->code);
      if (! $data['systemPageHasContent']) {
        return null;
      }

      return view('webblocks-cms::errors.system', [
        ...$data,
        'publicLocaleCode' => $locale->code, 'previewMode' => false,
      ])->render();
    } catch (Throwable) {
      return null;
    }
  }

  public static function maintenancePath(): string
  {
    return storage_path('framework/webblocks-maintenance.html');
  }

  /** Derived HTML, prepared while the application is healthy; never an authority. */
  public function prepareMaintenance(): void
  {
    $html = null;
    try {
      $site = Site::primary();
      $page = $site ? $this->design($site, 'core:error-503') : null;
      if ($page && ! app(PublicPageAccess::class)->protected($page)) {
        $locale = app(LocaleResolver::class)->default()->code;
        $data = $this->data($page, $locale);
        if ($data['systemPageHasContent']) {
          $html = view('webblocks-cms::errors.system', [
            ...$data,
            'publicLocaleCode' => $locale, 'systemMaintenance' => true,
          ])->render();
        }
      }
    } catch (Throwable) {
      // The built-in static template remains available when design rendering fails.
    }
    $path = self::maintenancePath();
    if ($html === null) {
      if (is_file($path)) {
        unlink($path);
      }

      return;
    }
    if (! is_dir(dirname($path))) {
      mkdir(dirname($path), 0755, true);
    }
    $temporary = tempnam(dirname($path), 'cms-maintenance-');
    try {
      file_put_contents($temporary, $html, LOCK_EX);
      rename($temporary, $path);
    } finally {
      if (is_file($temporary)) {
        unlink($temporary);
      }
    }
  }
}
