<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use WebBlocks\Cms\Http\Requests\PublicConsentRequest;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Consent\ConsentConfiguration;
use WebBlocks\Cms\Support\Consent\ConsentState;

class PublicConsentController
{
  public function sync(PublicConsentRequest $request, Site $site, ConsentConfiguration $config, ConsentState $state): JsonResponse
  {
    $services = $config->publicServices($site);
    $values = $request->validated();
    // An already-open page cannot silently grant a changed/new service.
    $grants = [];
    foreach ($services as $handle => $service) {
      $grants[$handle] = $service['category'] === 'necessary' || (($values['revisions'][$handle] ?? null) === $service['revision'] && ($values['services'][$handle] ?? false));
    }
    $cookie = $state->cookie($site, $services, $grants, $values['status'], $config->settings($site)['lifetime_days']);

    $previous = $state->read($request, $site, $services);
    $response = response()->json(['ok' => true, 'status' => $values['status'], 'services' => $grants,
      'server_decision' => ($grants['cms-analytics'] ?? false) ? 'accepted' : 'declined'])
      ->header('Cache-Control', 'private, no-store')->cookie($cookie);
    foreach ($services as $handle => $service) {
      if (($previous['services'][$handle] ?? false) && ! $grants[$handle]) {
        foreach ($service['cookies'] as $knownCookie) {
          $response->withCookie(cookie()->forget($knownCookie['name'], $knownCookie['path']));
        }
      }
    }

    return $response;
  }
}
