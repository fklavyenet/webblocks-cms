<?php

namespace WebBlocks\Cms\Queries;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Policies\ConsentPolicy;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Consent\ConsentConfiguration;
use WebBlocks\Cms\Support\Consent\ConsentPresets;

final class ConsentQuery
{
  public function workspace(Request $request): array
  {
    $sites = Site::query()->whereIn('id', app(CmsAuthorization::class)->siteIds($request->user(), 'cms.consent.read'))->primaryFirst()->orderBy('name')->get();
    $site = $request->filled('site') ? $sites->firstWhere('id', $request->integer('site')) : ($sites->firstWhere('id', $request->session()->get('cms.consent.site')) ?? $sites->first());
    abort_unless($site, 403);
    $request->session()->put('cms.consent.site', $site->id);

    return $this->forSite($site) + ['sites' => $sites, 'site' => $site, 'canWrite' => app(ConsentPolicy::class)->access($request, $site, true), 'canCode' => app(ConsentPolicy::class)->code($request)];
  }

  public function forSite(Site $site): array
  {
    $config = app(ConsentConfiguration::class);

    return ['settings' => $config->settings($site), 'services' => $config->services($site), 'presets' => app(ConsentPresets::class)->all(),
      'locales' => $site->enabledLocales()->get(), 'translations' => DB::table('wbcms_consent_translations')->where('site_id', $site->id)->orderBy('locale')->get(['locale', 'key', 'value'])];
  }
}
