<?php

namespace WebBlocks\Cms\Policies;

use Illuminate\Http\Request;
use WebBlocks\Cms\Models\JobRun;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Access\CmsAuthorization;

final class JobPolicy
{
  public function system($user): bool
  {
    return $user && ($user->is_active ?? false) && app(CmsAuthorization::class)->isSuperAdmin($user);
  }

  public function sites(Request $request): array
  {
    if (! $request->user() || ! $request->user()->is_active) {
      return [];
    }

    return Site::query()->get()->filter(fn ($site) => app(SiteSeoPolicy::class)->access($request, $site))->pluck('id')->all();
  }

  public function run(Request $request, JobRun $run): bool
  {
    return $this->system($request->user()) || ($run->job_key === 'seo' && in_array($run->site_id, $this->sites($request), true));
  }
}
