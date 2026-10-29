<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use WebBlocks\Cms\Actions\Consent\SavePublicConsentDecision;
use WebBlocks\Cms\Http\Requests\PublicConsentFormRequest;
use WebBlocks\Cms\Support\Consent\ConsentConfiguration;
use WebBlocks\Cms\Support\Consent\ConsentPreferencesLink;
use WebBlocks\Cms\Support\Consent\ConsentPresenter;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Sites\SiteResolver;

final class PublicConsentPreferencesController
{
  public function show(Request $request): Response
  {
    $site = app(SiteResolver::class)->current($request);
    abort_unless(app(ConsentConfiguration::class)->settings($site)['enabled'], 404);
    $code = $request->route('locale');
    $locales = app(LocaleResolver::class);
    $locale = $code ? $locales->enabled($code, $site) : $locales->default();
    abort_unless($locale, 404);
    $consent = app(ConsentPresenter::class)->forSite($site, $locale->code);

    return response()->view('webblocks-cms::public.consent-preferences', [
      'site' => $site, 'publicLocaleCode' => $locale->code, 'consentPage' => $consent,
      'consentAutoOpen' => true, 'title' => $consent['texts']['preferences_title'],
      'preferencesUrl' => app(ConsentPreferencesLink::class)->url($site, $locale->code),
    ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow')->header('Vary', 'Cookie');
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
    $response = redirect(app(ConsentPreferencesLink::class)->url($site, $code))->with('cms_consent_saved', true);
    $save->withCookies($response, $decision);

    return $response;
  }
}
