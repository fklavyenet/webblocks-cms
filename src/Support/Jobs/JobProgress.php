<?php

namespace WebBlocks\Cms\Support\Jobs;

use WebBlocks\Cms\Models\JobRun;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;

/** Read-only presentation of one authorized run; never advances work. */
final class JobProgress
{
  public static function payload(JobRun $run): array
  {
    $locale = app(AdminLocaleResolver::class)->locale();
    $notice = match ($run->reason) {
      'partial' => 'seo_workspace.incomplete', 'stale' => 'seo.stale_report',
      'policy_changed' => 'jobs.policy_changed', 'manual_limit' => 'jobs.manual_limit',
      null => null, default => 'jobs.error',
    };

    return ['run_id' => $run->id, 'subject_id' => $run->subject_id, 'state' => $run->state, 'processed' => $run->processed, 'total' => $run->total,
      'requested_state' => $run->requested_state,
      'message' => trans('webblocks-cms::jobs.states.'.$run->state, [], $locale),
      'notice' => $notice ? trans('webblocks-cms::'.$notice, [], $locale) : null,
      'pause_url' => route('admin.jobs.transition', $run), 'progress_url' => route('admin.jobs.progress', $run)];
  }
}
