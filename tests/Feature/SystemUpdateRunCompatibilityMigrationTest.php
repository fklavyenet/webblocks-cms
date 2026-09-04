<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateRunRetention;
use WebBlocks\Cms\Tests\TestCase;

class SystemUpdateRunCompatibilityMigrationTest extends TestCase
{
  #[Test]
  public function legacy_update_history_is_safe_and_repaired_idempotently(): void
  {
    Schema::dropIfExists('wbcms_system_update_runs');
    Schema::create('wbcms_system_update_runs', function (Blueprint $table): void {
      $table->id();
      $table->string('from_version');
      $table->string('to_version');
      $table->string('status', 32);
      $table->longText('output')->nullable();
      $table->timestamps();
    });
    DB::table('wbcms_system_update_runs')->insert([
      'from_version' => '1.20.0', 'to_version' => '1.78.0', 'status' => 'failed',
      'created_at' => '2026-09-04 18:00:00', 'updated_at' => '2026-09-04 18:00:00',
    ]);

    $retention = app(SystemUpdateRunRetention::class);
    $this->assertFalse($retention->schemaReady());
    $this->assertTrue($retention->retainedRuns()->isEmpty());

    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_09_04_200000_ensure_system_update_run_columns.php';
    $migration->up();
    $migration->up();

    $this->assertTrue($retention->schemaReady());
    $this->assertCount(1, $retention->retainedRuns());
    $this->assertSame('2026-09-04 18:00:00', DB::table('wbcms_system_update_runs')->value('started_at'));
  }
}
