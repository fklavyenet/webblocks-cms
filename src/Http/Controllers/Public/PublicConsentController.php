<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use WebBlocks\Cms\Actions\Consent\SavePublicConsentDecision;
use WebBlocks\Cms\Http\Requests\PublicConsentRequest;
use WebBlocks\Cms\Models\Site;

class PublicConsentController
{
  public function sync(PublicConsentRequest $request, Site $site, SavePublicConsentDecision $save): JsonResponse
  {
    $decision = $save->execute($request, $site, $request->validated());
    $response = response()->json(['ok' => true, 'status' => $decision['status'], 'services' => $decision['services'],
      'server_decision' => ($decision['services']['cms-analytics'] ?? false) ? 'accepted' : 'declined']);
    $save->withCookies($response, $decision);

    return $response;
  }
}
