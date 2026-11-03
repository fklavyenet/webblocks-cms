<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Model;

final class JobRun extends Model
{
  protected $table = 'wbcms_job_runs';

  protected $guarded = [];

  protected function casts(): array
  {
    return ['site_id' => 'integer', 'subject_id' => 'integer', 'processed' => 'integer', 'total' => 'integer', 'cursor' => 'integer',
      'lease_until' => 'datetime', 'started_at' => 'datetime', 'progress_at' => 'datetime', 'completed_at' => 'datetime'];
  }
}
