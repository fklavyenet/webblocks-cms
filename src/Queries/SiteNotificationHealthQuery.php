<?php

namespace WebBlocks\Cms\Queries;

use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class SiteNotificationHealthQuery
{
  public function __construct(private readonly AdminAuthorization $authorization, private readonly SchedulerHealth $health) {}

  public function forUser($user): Collection
  {
    if (! $user?->can('manage-site-operations')) {
      return collect();
    }

    $sites = Site::query()->tap(fn ($query) => $this->authorization->scopeSitesForUser($query, $user))->get()
      ->filter(fn (Site $site) => $this->health->requiredForSite($site));
    $snapshot = $sites->isNotEmpty() ? $this->health->snapshot() : [];

    return $sites->map(fn (Site $site) => ['site' => $site, 'health' => $snapshot + ['required' => true]]);
  }
}
