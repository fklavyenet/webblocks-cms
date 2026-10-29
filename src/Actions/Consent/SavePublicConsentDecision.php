<?php

namespace WebBlocks\Cms\Actions\Consent;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Consent\ConsentConfiguration;
use WebBlocks\Cms\Support\Consent\ConsentState;

final class SavePublicConsentDecision
{
  public function execute(Request $request, Site $site, array $values): array
  {
    $config = app(ConsentConfiguration::class);
    $state = app(ConsentState::class);
    $services = $config->publicServices($site);
    $grants = [];
    foreach ($services as $handle => $service) {
      $grants[$handle] = $service['category'] === 'necessary'
        || (($values['revisions'][$handle] ?? null) === $service['revision'] && ($values['services'][$handle] ?? false));
    }
    $previous = $state->read($request, $site, $services);
    $cookies = [$state->cookie($site, $services, $grants, $values['status'], $config->settings($site)['lifetime_days'])];
    foreach ($services as $handle => $service) {
      if (($previous['services'][$handle] ?? false) && ! $grants[$handle]) {
        foreach ($service['cookies'] as $knownCookie) {
          $cookies[] = cookie()->forget($knownCookie['name'], $knownCookie['path']);
        }
      }
    }

    return ['services' => $grants, 'status' => $values['status'], 'cookies' => $cookies];
  }

  public function withCookies(Response $response, array $decision): Response
  {
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->setVary('Cookie', false);
    foreach ($decision['cookies'] as $cookie) {
      $response->headers->setCookie($cookie);
    }

    return $response;
  }
}
