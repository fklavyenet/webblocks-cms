<?php

namespace WebBlocks\Cms\Support\Jobs;

use WebBlocks\Cms\Actions\EditorRecovery\WriteEditorRecovery;
use WebBlocks\Cms\Models\JobRun;
use WebBlocks\Cms\Support\Visitors\VisitorReportRetention;

final class ManualMaintenance
{
  public function visitors(JobRun $run): array
  {
    $batch = app(VisitorReportRetention::class)->runBatch();
    $processed = $run->processed + $batch['events'] + $batch['totals'];

    return ['state' => $batch['complete'] ? 'complete' : 'running', 'processed' => $processed,
      'total' => $batch['complete'] ? $processed : null];
  }

  public function recovery(JobRun $run): array
  {
    $count = app(WriteEditorRecovery::class)->expireBatch();
    $processed = $run->processed + $count;

    return ['state' => $count < 200 ? 'complete' : 'running', 'processed' => $processed,
      'total' => $count < 200 ? $processed : null];
  }
}
