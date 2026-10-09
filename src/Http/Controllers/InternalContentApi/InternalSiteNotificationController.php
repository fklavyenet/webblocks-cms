<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use WebBlocks\Cms\Actions\Sites\UpdateSiteNotifications;
use WebBlocks\Cms\Http\Requests\SiteNotificationSettingsRequest;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\SiteNotifications\SiteNotificationPolicy;

class InternalSiteNotificationController
{
  public function show(SiteNotificationSettingsRequest $request, Site $site): JsonResponse
  {
    return response()->json([
      'ok' => true,
      'notification_settings' => SiteNotificationPolicy::forSite($site),
      'delivery_status' => SiteNotificationPolicy::deliveryStatus($site),
      'summary_delivery_status' => SiteNotificationPolicy::summaryDeliveryStatus($site),
    ]);
  }

  public function update(SiteNotificationSettingsRequest $request, Site $site, UpdateSiteNotifications $update): JsonResponse
  {
    $site = $update->handle($site, $request->validated());

    return response()->json(['ok' => true, 'notification_settings' => SiteNotificationPolicy::forSite($site)]);
  }
}
