<?php

namespace WebBlocks\Cms\Tests\Unit;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Mockery;
use WebBlocks\Cms\Support\System\Updates\SystemUpdater;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater as ClientSystemUpdater;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException as ClientUpdateException;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateResult as ClientUpdateResult;
use WebBlocks\Cms\Tests\TestCase;

class SystemUpdaterFlowTest extends TestCase
{
  public static function setUpBeforeClass(): void
  {
    parent::setUpBeforeClass();

    if (! class_exists(User::class)) {
      class_alias(SystemUpdaterTestUser::class, User::class);
    }
  }

  public function test_cms_boundary_maps_the_shared_client_result(): void
  {
    $now = CarbonImmutable::now();
    $client = Mockery::mock(ClientSystemUpdater::class);
    $client->expects('run')->with(42)->andReturn(new ClientUpdateResult(
      fromVersion: '1.0.0',
      toVersion: '1.1.0',
      status: 'success',
      summary: 'Updated.',
      output: 'Done.',
      warningCount: 0,
      startedAt: $now,
      finishedAt: $now,
      durationMs: 10,
      preUpdateBackup: null,
    ));

    $user = new User;
    $user->setAttribute($user->getKeyName(), 42);
    $result = (new SystemUpdater($client))->run($user);

    $this->assertSame('1.1.0', $result->toVersion);
    $this->assertSame('success', $result->status);
    $this->assertNull($result->preUpdateBackup);
  }

  public function test_cms_boundary_preserves_user_safe_update_errors(): void
  {
    $client = Mockery::mock(ClientSystemUpdater::class);
    $client->expects('run')->andThrow(new ClientUpdateException('Update failed.', 'Internal detail.'));

    $this->expectException(UpdateException::class);
    $this->expectExceptionMessage('Internal detail.');

    (new SystemUpdater($client))->run(new User);
  }
}

class SystemUpdaterTestUser extends Model {}
