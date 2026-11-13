<?php

namespace WebBlocks\Cms\Support\Admin;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\View\View;
use WebBlocks\Cms\Models\AutomaticPageRule;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Pages\PageIndexState;

final class PublicSiteMenuComposer
{
  public function __construct(
    private readonly CmsAuthorization $authorization,
    private readonly LocaleResolver $locales,
    private readonly PageIndexState $pageIndexState,
  ) {}

  public function compose(View $view): void
  {
    $request = request();
    $view->with('adminPublicSite', null)->with('adminPublicSiteChoices', []);
    $user = $request->user();
    $siteModel = new Site;
    if (! $user || ! ($user->is_active ?? true) || ! $siteModel->getConnection()->getSchemaBuilder()->hasTable($siteModel->getTable())) {
      return;
    }

    $sites = Site::query();
    if (! $this->authorization->isSuperAdmin($user)) {
      $sites->whereKey($this->authorization->siteIds($user)->all());
    }
    $siteId = $this->contextSiteId($request, $view->getData());
    $site = (is_int($siteId) || (is_string($siteId) && ctype_digit($siteId))) ? (clone $sites)->find((int) $siteId) : null;
    $choices = $site ? null : $sites->with(['siteDomains', 'enabledLocales'])
      ->orderByDesc('is_primary')->orderBy('name')->orderBy('id')->get();

    if (! $site && $choices->isEmpty()) {
      return;
    }

    try {
      $this->locales->default();
    } catch (ModelNotFoundException) {
      return;
    }

    if ($site) {
      $view->with('adminPublicSite', $this->link($site));

      return;
    }

    $view->with('adminPublicSiteChoices', $choices->map($this->link(...))->all());
  }

  private function contextSiteId(Request $request, array $data): mixed
  {
    $route = $request->route();
    foreach ($route?->parameters ?? [] as $resource) {
      $siteId = match (true) {
        $resource instanceof Site => $resource->id,
        $resource instanceof Page, $resource instanceof SharedSlot, $resource instanceof NavigationItem, $resource instanceof AutomaticPageRule => $resource->site_id,
        $resource instanceof Block => $resource->page()->without('translations')->value('site_id'),
        default => null,
      };
      if ($siteId !== null) {
        return $siteId;
      }
    }

    if (array_key_exists('activeSite', $data)) {
      return $data['activeSite'] instanceof Site ? $data['activeSite']->id : null;
    }

    foreach (['site', 'selectedSite'] as $key) {
      if (($data[$key] ?? null) instanceof Site && $data[$key]->exists) {
        return $data[$key]->id;
      }
    }

    $stored = $this->pageIndexState->storedQuery($request);

    return $request->query('site') ?? $request->query('site_id') ?? $stored['site'] ?? $stored['site_id'] ?? null;
  }

  private function link(Site $site): array
  {
    return ['name' => $site->name, 'url' => $site->publicHomeUrl()];
  }
}
