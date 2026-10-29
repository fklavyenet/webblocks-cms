<?php

namespace WebBlocks\Cms\Support\Consent;

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;

final class ConsentPresenter
{
  public function forSite(Site $site, ?string $locale = null, bool $preview = false): array
  {
    $config = app(ConsentConfiguration::class);
    $settings = $config->settings($site);
    if ($settings['enabled'] || $preview) {
      request()->attributes->set('cms.consent.rendered', true);
    }
    $services = $config->publicServices($site, $locale);
    $state = app(ConsentState::class)->read(request(), $site, $services);
    if ($preview && request()->has('choice')) {
      $mode = request()->query('choice');
      $state = ['status' => in_array($mode, ['accepted', 'rejected'], true) ? $mode : null,
        'services' => array_fill_keys(array_keys($services), $mode === 'accepted'), 'needs_choice' => ! in_array($mode, ['accepted', 'rejected'], true)];
    }
    $texts = [];
    foreach (['title', 'body', 'preferences_title', 'preferences_body', 'accept', 'reject', 'customize', 'save', 'close', 'always_on',
      'necessary', 'necessary_body', 'preferences', 'analytics', 'marketing', 'external', 'settings', 'privacy', 'load_once', 'allow_service', 'blocked', 'sync_error', 'reload_required', 'reload', 'cookies', 'duration', 'saved'] as $key) {
      $texts[$key] = $config->text($site, $key, $locale);
    }
    $payload = ['site' => $site->id, 'preferencesUrl' => app(ConsentPreferencesLink::class)->url($site, $locale), 'syncUrl' => Route::has('public.privacy-consent.site-sync') ? route('public.privacy-consent.site-sync', $site) : null, 'csrfToken' => csrf_token(),
      'services' => $services, 'state' => $state, 'preview' => $preview];

    return ['enabled' => $preview || $settings['enabled'], 'settings' => $settings, 'services' => $services, 'state' => $state, 'texts' => $texts, 'payload' => $payload,
      'privacyUrl' => $settings['privacy_url'] ? app(PageRouteResolver::class)->localizedPublicUrl($settings['privacy_url'], $locale, $site) : null];
  }
}
