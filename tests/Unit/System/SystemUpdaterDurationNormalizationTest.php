<?php

namespace Tests\Unit\System;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\System\Updates\SystemUpdater;

class SystemUpdaterDurationNormalizationTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function fractional_duration_is_normalized_before_updater_persistence_and_reporting(): void
  {
    $updater = app(SystemUpdater::class);

    $normalizeDuration = new \ReflectionMethod(SystemUpdater::class, 'normalizeDurationMs');
    $normalizeDuration->setAccessible(true);

    $persistRun = new \ReflectionMethod(SystemUpdater::class, 'persistRun');
    $persistRun->setAccessible(true);

    $durationMs = $normalizeDuration->invoke($updater, 6120.728);

    $this->assertSame(6121, $durationMs);

    $run = SystemUpdateRun::query()->create([
      'from_version' => '1.32.6',
      'to_version' => '1.32.7',
      'status' => SystemUpdateRun::STATUS_FAILED,
      'summary' => 'Pending',
      'started_at' => CarbonImmutable::parse('2026-05-20 12:00:00'),
      'output' => '',
    ]);

    $persistRun->invoke(
      $updater,
      $run,
      SystemUpdateRun::STATUS_SUCCESS,
      'Updated to 1.32.7 successfully.',
      ['Installed version persisted as 1.32.7'],
      0,
      CarbonImmutable::parse('2026-05-20 12:00:06'),
      $durationMs,
    );

    $run->refresh();

    $this->assertSame(6121, $run->duration_ms);
    $this->assertSame(SystemUpdateRun::STATUS_SUCCESS, $run->status);
    $this->assertSame('Updated to 1.32.7 successfully.', $run->summary);
  }
}
