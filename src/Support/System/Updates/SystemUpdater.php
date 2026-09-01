<?php

namespace WebBlocks\Cms\Support\System\Updates;

use App\Models\User;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater as ClientSystemUpdater;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException as ClientUpdateException;

/**
 * Backward-compatible CMS boundary around the generated shared update engine.
 * Controllers and inspectors keep their established CMS-facing types while
 * download, verification, apply, rollback, locking and run orchestration come
 * from the synchronized Client runtime.
 */
final class SystemUpdater
{
  public function __construct(private readonly ClientSystemUpdater $client) {}

  public function run(User $user): UpdateResult
  {
    try {
      $result = $this->client->run((int) $user->getKey());
    } catch (ClientUpdateException $exception) {
      throw new UpdateException(
        $exception->userMessage(),
        $exception->getMessage(),
        previous: $exception,
      );
    }
    $backup = is_string($result->preUpdateBackup) && $result->preUpdateBackup !== ''
      ? SystemBackup::query()->find($result->preUpdateBackup)
      : null;

    return new UpdateResult(
      fromVersion: $result->fromVersion,
      toVersion: $result->toVersion,
      status: $result->status,
      summary: $result->summary,
      output: $result->output,
      warningCount: $result->warningCount,
      startedAt: $result->startedAt,
      finishedAt: $result->finishedAt,
      durationMs: $result->durationMs,
      preUpdateBackup: $backup,
    );
  }

  public function isLocked(): bool
  {
    return $this->client->isLocked();
  }
}
