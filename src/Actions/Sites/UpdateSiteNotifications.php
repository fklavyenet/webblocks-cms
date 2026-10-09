<?php

namespace WebBlocks\Cms\Actions\Sites;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\SiteNotifications\SiteNotificationPolicy;

class UpdateSiteNotifications
{
  public function handle(Site $site, array $data): Site
  {
    return DB::transaction(function () use ($site, $data): Site {
      $site = Site::query()->whereKey($site->id)->lockForUpdate()->firstOrFail();
      $site->forceFill(['notification_settings' => array_replace(SiteNotificationPolicy::forSite($site), $data)])->save();

      return $site;
    });
  }
}
