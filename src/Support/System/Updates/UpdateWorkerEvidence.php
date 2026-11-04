<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Facades\Schema;

class UpdateWorkerEvidence
{
  public function available(string $table): bool
  {
    return Schema::hasTable($table) && Schema::hasColumns($table, ['worker_pid', 'worker_host', 'heartbeat_at', 'recovery_required']);
  }

  public function capture(string $table): array
  {
    return $this->available($table) ? [
      'worker_pid' => getmypid(), 'worker_host' => app(UpdateWorkerProcess::class)->host(), 'heartbeat_at' => now(),
    ] : [];
  }
}
