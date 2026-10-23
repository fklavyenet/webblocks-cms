<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MaintenanceCleanupState
{
  public const TABLE = 'wbcms_maintenance_cleanup_states';

  public static function migrate(): void
  {
    if (! Schema::hasTable(self::TABLE)) {
      Schema::create(self::TABLE, function (Blueprint $table): void {
        $table->string('category', 32)->primary();
        $table->string('status', 16);
        $table->string('source', 16);
        $table->timestamp('started_at')->nullable();
        $table->timestamp('finished_at')->nullable();
        $table->timestamp('last_success_at')->nullable();
        $table->unsignedBigInteger('candidate_count')->default(0);
        $table->unsignedBigInteger('candidate_bytes')->default(0);
        $table->unsignedBigInteger('deleted_count')->default(0);
        $table->unsignedBigInteger('deleted_bytes')->default(0);
        $table->unsignedInteger('failure_count')->default(0);
      });
    }
  }

  public function ready(): bool
  {
    return Schema::hasTable(self::TABLE);
  }

  public function overview(): array
  {
    return [
      'ready' => $this->ready(),
      'categories' => $this->ready() ? DB::table(self::TABLE)->orderBy('category')->get()->keyBy('category')->map(function ($row): array {
        $values = (array) $row;
        if ($values['status'] === 'running' && $values['started_at'] !== null && Carbon::parse($values['started_at'])->lt(now()->subDay())) {
          $values['status'] = 'interrupted';
        }

        return $values;
      })->all() : [],
    ];
  }

  public function start(string $category, string $source): void
  {
    DB::table(self::TABLE)->updateOrInsert(['category' => $category], [
      'status' => 'running', 'source' => $source, 'started_at' => now(), 'finished_at' => null,
      'candidate_count' => 0, 'candidate_bytes' => 0, 'deleted_count' => 0, 'deleted_bytes' => 0, 'failure_count' => 0,
    ]);
  }

  public function finish(string $category, MaintenanceCleanupResult $result): void
  {
    $values = [
      'status' => $result->failures === [] ? 'completed' : 'failed',
      'finished_at' => now(),
      'candidate_count' => $result->candidateCount, 'candidate_bytes' => $result->candidateBytes,
      'deleted_count' => $result->deletedCount, 'deleted_bytes' => $result->deletedBytes,
      'failure_count' => count($result->failures),
    ];
    if ($result->failures === []) {
      $values['last_success_at'] = now();
    }
    DB::table(self::TABLE)->where('category', $category)->update($values);
  }
}
