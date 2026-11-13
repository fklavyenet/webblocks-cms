<?php

namespace WebBlocks\Cms\Support\Admin;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\View\View;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Locales\LocaleResolver;

final class PublicSiteMenuComposer
{
  public function __construct(
    private readonly CmsAuthorization $authorization,
    private readonly LocaleResolver $locales,
  ) {}

  public function compose(View $view): void
  {
    $view->with('adminPublicSiteChoices', []);
    $user = request()->user();
    $siteModel = new Site;
    if (! $user || ! ($user->is_active ?? true) || ! $siteModel->getConnection()->getSchemaBuilder()->hasTable($siteModel->getTable())) {
      return;
    }

    $sites = Site::query();
    if (! $this->authorization->isSuperAdmin($user)) {
      $sites->whereKey($this->authorization->siteIds($user)->all());
    }
    $choices = $sites->with(['siteDomains', 'enabledLocales'])
      ->orderByDesc('is_primary')->orderBy('name')->orderBy('id')->get();

    if ($choices->isEmpty()) {
      return;
    }

    try {
      $this->locales->default();
    } catch (ModelNotFoundException) {
      return;
    }

    $view->with('adminPublicSiteChoices', $choices->map(fn (Site $site) => [
      'name' => $site->name,
      'url' => $site->publicHomeUrl(),
    ])->all());
  }
}
