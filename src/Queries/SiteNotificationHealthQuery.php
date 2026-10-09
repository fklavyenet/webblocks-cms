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

    $sites = Site::query()->tap(fn ($query) => $this->authorization->scopeSitesForUser($query, $user))->get();
    $snapshot = $sites->isNotEmpty() ? $this->health->snapshot() : [];

    return $sites->map(fn (Site $site) => ['site' => $site, 'health' => $snapshot + ['required' => $this->health->requiredForSite($site)]]);
  }

  public function dashboardForUser($user): array
  {
    $sites = $this->forUser($user);
    $health = $sites->first()['health'] ?? null;
    if ($health !== null) {
      // A legacy/immediate site must not hide another accessible site's need
      // for scheduling, and inaccessible sites must not influence the warning.
      $health['required'] = $sites->contains(fn (array $row) => $row['health']['required']);
    }

    return ['sites' => $sites, 'health' => $health];
  }
}
