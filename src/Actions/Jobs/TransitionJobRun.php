<?php

namespace WebBlocks\Cms\Actions\Jobs;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Actions\Seo\ManageSeoAudit;
use WebBlocks\Cms\Models\JobRun;
use WebBlocks\Cms\Models\SeoAudit;

final class TransitionJobRun
{
  public function execute(JobRun $run, string $action): void
  {
    DB::transaction(function () use ($run, $action): void {
      $run = JobRun::query()->lockForUpdate()->findOrFail($run->id);
      abort_unless(in_array($run->state, match ($action) {
        'pause' => ['queued', 'running'], 'resume' => ['paused'], 'cancel' => ['queued', 'running', 'paused'], default => [],
      }, true), 409);
      $state = match ($action) {
      'pause' => 'paused', 'resume' => 'queued', 'cancel' => 'cancelled'
      };
      if ($run->lease_until?->isFuture()) {
        abort_if($action === 'resume', 409);
        $run->update(['requested_state' => $state]);

        return;
      }
      if ($run->job_key === 'seo') {
        $audit = SeoAudit::query()->where('site_id', $run->site_id)->findOrFail($run->subject_id);
        if ($action === 'resume' || $audit->state !== 'paused') {
          app(ManageSeoAudit::class)->transition($audit, $action === 'resume' ? 'resume' : 'pause');
        }
      }
      $run->update(['state' => $state, 'lease_token' => null, 'lease_until' => null, 'requested_state' => null,
        'active_key' => $state === 'cancelled' ? null : $run->active_key, 'completed_at' => $state === 'cancelled' ? now() : null]);
    });
  }
}
