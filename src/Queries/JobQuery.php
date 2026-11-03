<?php

namespace WebBlocks\Cms\Queries;

use Illuminate\Http\Request;
use WebBlocks\Cms\Models\JobRun;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Policies\JobPolicy;
use WebBlocks\Cms\Support\Jobs\JobRegistry;
use WebBlocks\Cms\Support\Jobs\JobSchema;

final class JobQuery
{
  public function read(Request $request): array
  {
    $system = app(JobPolicy::class)->system($request->user());
    $sites = app(JobPolicy::class)->sites($request);
    $ready = JobSchema::ready();
    $jobs = array_filter(app(JobRegistry::class)->all(), fn ($job) => $system || $job->key === 'seo');
    $runs = JobRun::query()->when(! $system, fn ($q) => $q->where('job_key', 'seo')->whereIn('site_id', $sites));

    return ['system' => $system, 'ready' => $ready, 'definitions' => $jobs,
      'jobSites' => Site::query()->whereIn('id', $sites)->get(),
      'runs' => $ready ? (clone $runs)->latest('id')->paginate(30) : null,
      'activeRuns' => $ready ? $runs->whereIn('state', ['queued', 'running', 'paused'])->latest('id')->limit(50)->get() : collect()];
  }
}
