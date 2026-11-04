<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use WebBlocks\Cms\Actions\Consent\SavePublicConsentDecision;
use WebBlocks\Cms\Http\Requests\PublicConsentFormRequest;
use WebBlocks\Cms\Support\Consent\ConsentConfiguration;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;
use WebBlocks\Cms\Support\Sites\SiteResolver;

final class PublicConsentPreferencesController
{
  public function show(Request $request): RedirectResponse
  {
    $site = app(SiteResolver::class)->current($request);
    abort_unless(app(ConsentConfiguration::class)->settings($site)['enabled'], 404);
    $code = $request->route('locale');
    $locales = app(LocaleResolver::class);
    $locale = $code ? $locales->enabled($code, $site) : $locales->default();
    abort_unless($locale, 404);
    $home = app(PageRouteResolver::class)->homePath($locale->code, $site);
    abort_unless($home, 404);

    return redirect($home.'#cookie-settings')->header('Cache-Control', 'private, no-store');
  }

  public function store(PublicConsentFormRequest $request, SavePublicConsentDecision $save): RedirectResponse
  {
    $site = app(SiteResolver::class)->current($request);
    $locales = app(LocaleResolver::class);
    $code = $request->route('locale');
    abort_if($code && ! $locales->enabled($code, $site), 404);
    $values = $request->validated();
    if ($values['status'] === 'accepted') {
      $values['services'] = array_fill_keys(array_keys($values['revisions']), true);
    } elseif ($values['status'] === 'rejected') {
      $values['services'] = [];
    }
    $decision = $save->execute($request, $site, $values);
    $home = app(PageRouteResolver::class)->homePath($code, $site);
    $response = redirect(($home ?? '/').'#cookie-settings')->with('cms_consent_saved', true);
    $save->withCookies($response, $decision);

    return $response;
  }
}
