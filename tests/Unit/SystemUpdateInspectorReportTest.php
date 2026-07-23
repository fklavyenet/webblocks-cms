<?php

namespace WebBlocks\Cms\Tests\Unit;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Support\System\SystemUpdateInspector;
use WebBlocks\Cms\Support\System\Updates\UpdateCheckResult;
use WebBlocks\Cms\Support\System\Updates\UpdateServerClient;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\TestCase;

class SystemUpdateInspectorReportTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    Schema::create('wbcms_system_update_runs', function (Blueprint $table) {
      $table->id();
      $table->string('from_version');
      $table->string('to_version');
      $table->string('status', 32);
      $table->timestamps();
    });
  }

  #[Test]
  public function report_exposes_the_reduced_preflight_shape(): void
  {
    $this->bindServerClient($this->upToDateCheckResult());

    $report = app(SystemUpdateInspector::class)->report();

    foreach (['checked_at', 'installed_version', 'version', 'checks', 'can_update', 'environment'] as $key) {
      $this->assertArrayHasKey($key, $report);
    }

    $this->assertArrayNotHasKey('diagnostics', $report, 'The blocker-era diagnostics key must be gone.');
    $this->assertArrayNotHasKey('auto_update', $report, 'The blocker state machine must be gone.');

    foreach ($report['checks'] as $check) {
      $this->assertContains($check['status'], ['pass', 'fail'], 'Preflight checks report a binary pass/fail status.');
      $this->assertNotSame('', (string) $check['label']);
      $this->assertNotSame('', (string) $check['message']);
    }

    $labels = collect($report['checks'])->pluck('label')->all();

    foreach ([
      'Database connection',
      'Archive extraction',
      'Release signature verification',
      'Command execution',
      'Application root write access',
      'Update workspace',
      'Free disk space',
    ] as $expectedLabel) {
      $this->assertContains($expectedLabel, $labels);
    }
  }

  #[Test]
  public function can_update_is_false_when_no_newer_release_is_available(): void
  {
    $this->bindServerClient($this->upToDateCheckResult());

    $report = app(SystemUpdateInspector::class)->report();

    $this->assertFalse($report['can_update']);
    $this->assertSame('up_to_date', $report['version']['state']);
  }

  #[Test]
  public function can_update_is_true_when_a_release_is_ready_and_preflight_passes(): void
  {
    $this->bindServerClient($this->updateAvailableCheckResult());

    $report = app(SystemUpdateInspector::class)->report();

    $failing = collect($report['checks'])->where('status', 'fail')->pluck('label')->all();
    $this->assertSame([], $failing, 'Expected all preflight checks to pass in the test environment.');
    $this->assertTrue($report['can_update']);
  }

  #[Test]
  public function can_update_is_false_without_a_download_url(): void
  {
    $this->bindServerClient($this->updateAvailableCheckResult(downloadUrl: ''));

    $report = app(SystemUpdateInspector::class)->report();

    $this->assertFalse($report['can_update']);
  }

  #[Test]
  public function can_update_is_false_while_another_update_holds_the_lock(): void
  {
    $this->bindServerClient($this->updateAvailableCheckResult());

    $lock = Cache::lock((string) config('webblocks-updates.installer.lock_name', 'system-updates:run'), 900);
    $this->assertTrue($lock->get());

    try {
      $report = app(SystemUpdateInspector::class)->report();

      $this->assertFalse($report['can_update']);
    } finally {
      $lock->release();
    }
  }

  #[Test]
  public function can_update_is_false_when_the_runs_table_is_missing(): void
  {
    Schema::drop('wbcms_system_update_runs');

    $this->bindServerClient($this->updateAvailableCheckResult());

    $report = app(SystemUpdateInspector::class)->report();

    $this->assertFalse($report['can_update']);

    $databaseCheck = collect($report['checks'])->firstWhere('label', 'Database connection');
    $this->assertSame('fail', $databaseCheck['status']);
  }

  private function bindServerClient(UpdateCheckResult $checkResult): void
  {
    $client = Mockery::mock(UpdateServerClient::class);
    $client->shouldReceive('check')->andReturn($checkResult);

    $this->app->instance(UpdateServerClient::class, $client);
  }

  private function upToDateCheckResult(): UpdateCheckResult
  {
    return $this->checkResult(
      state: 'up_to_date',
      latestVersion: WebBlocks::version(),
      updateAvailable: false,
      release: null,
    );
  }

  private function updateAvailableCheckResult(string $downloadUrl = 'https://updates.example.test/webblocks-cms-99.0.0.zip'): UpdateCheckResult
  {
    return $this->checkResult(
      state: 'update_available',
      latestVersion: '99.0.0',
      updateAvailable: true,
      release: [
        'version' => '99.0.0',
        'download_url' => $downloadUrl,
        'checksum_sha256' => str_repeat('a', 64),
      ],
    );
  }

  private function checkResult(string $state, ?string $latestVersion, bool $updateAvailable, ?array $release): UpdateCheckResult
  {
    return new UpdateCheckResult(
      state: $state,
      label: 'Status',
      message: 'Status message.',
      badgeClass: 'wb-status-active',
      serverReachable: true,
      apiVersion: '1',
      serverUrl: 'https://updates.example.test',
      product: 'webblocks-cms',
      channel: 'stable',
      installedVersion: WebBlocks::version(),
      latestVersion: $latestVersion,
      updateAvailable: $updateAvailable,
      compatibility: ['status' => 'compatible', 'reasons' => []],
      release: $release,
      errorCode: null,
      errorMessage: null,
      checkedAt: CarbonImmutable::now(),
    );
  }
}
