<?php

namespace WebBlocks\Cms\Tests\Unit;

use Mockery;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\System\Updates\UpdateWorkerProcess;

class UpdateWorkerProcessTest extends TestCase
{
  public function test_missing_and_foreign_worker_identity_never_prove_death(): void
  {
    $probe = Mockery::mock(UpdateWorkerProcess::class)->makePartial();
    $probe->shouldReceive('host')->andReturn(str_repeat('a', 64));
    $this->assertSame('unknown', $probe->state(null, str_repeat('a', 64)));
    $this->assertSame('unknown', $probe->state(getmypid(), null));
    $this->assertSame('unknown', $probe->state(getmypid(), str_repeat('b', 64)));
    Mockery::close();
  }

  public function test_local_live_and_absent_pids_are_distinguished_without_sending_a_signal(): void
  {
    if (! function_exists('posix_kill')) {
      $this->markTestSkipped('POSIX process inspection is unavailable.');
    }
    $probe = Mockery::mock(UpdateWorkerProcess::class)->makePartial();
    $probe->shouldReceive('host')->andReturn(str_repeat('a', 64));
    $this->assertSame('alive', $probe->state(getmypid(), str_repeat('a', 64)));
    $this->assertSame('dead', $probe->state(2147483646, str_repeat('a', 64)));
    Mockery::close();
  }

  public function test_unavailable_host_identity_disables_process_death_claims(): void
  {
    $probe = Mockery::mock(UpdateWorkerProcess::class)->makePartial();
    $probe->shouldReceive('host')->andReturnNull();
    $this->assertSame('unknown', $probe->state(2147483646, str_repeat('a', 64)));
    Mockery::close();
  }
}
