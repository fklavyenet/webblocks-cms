<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use WebBlocks\Cms\Actions\Consent\SaveConsentService;
use WebBlocks\Cms\Actions\Consent\UpdateConsentSettings;
use WebBlocks\Cms\Http\Requests\ConsentRequest;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Queries\ConsentQuery;

class InternalConsentController
{
  public function show(ConsentRequest $request, Site $site, ConsentQuery $query): JsonResponse
  {
    return response()->json(['ok' => true, 'site_id' => $site->id] + $query->forSite($site));
  }

  public function settings(ConsentRequest $request, Site $site, UpdateConsentSettings $action, ConsentQuery $query): JsonResponse
  {
    $action->settings($site, $request->validated());

    return $this->show($request, $site, $query);
  }

  public function service(ConsentRequest $request, Site $site, string $service, SaveConsentService $action): JsonResponse
  {
    $record = $action->execute($site, $service, $request->validated());

    return response()->json(['ok' => true, 'service' => $record]);
  }

  public function destroy(ConsentRequest $request, Site $site, string $service, SaveConsentService $action): JsonResponse
  {
    $action->delete($site, $service);

    return response()->json(['ok' => true]);
  }

  public function texts(ConsentRequest $request, Site $site, UpdateConsentSettings $action): JsonResponse
  {
    $action->texts($site, $request->validated('locale'), $request->validated('texts'));

    return response()->json(['ok' => true]);
  }
}
