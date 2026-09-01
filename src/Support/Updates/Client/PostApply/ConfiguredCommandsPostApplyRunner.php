<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\PostApply;

use WebBlocks\Cms\Support\Updates\Client\Contracts\PostApplyRunner;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateCommandRunner;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;

/**
 * Default post-apply runner: executes the allowlisted `commands.post_apply`
 * entries (empty by default, so a no-op out of the box). Each entry is either a
 * bare artisan command name (run via the resolved PHP binary) or a full argv
 * array. Non-artisan/non-allowlisted forms are rejected.
 *
 * Strategy-aware DB migrations are layered on in Slice 5 by decorating or
 * replacing this default.
 */
class ConfiguredCommandsPostApplyRunner implements PostApplyRunner
{
  public function __construct(private readonly UpdateCommandRunner $commandRunner)
  {
  }

  public function run(string $commandDir, array &$output): void
  {
    foreach ($this->postApplyCommands() as $entry) {
      $command = $this->toCommand($entry);
      $this->commandRunner->run($command, $commandDir, $output);
    }
  }

  protected function commandRunner(): UpdateCommandRunner
  {
    return $this->commandRunner;
  }

  /**
   * @return list<string|array<int,string>>
   */
  private function postApplyCommands(): array
  {
    $configured = config('publisher-client.commands.post_apply', []);

    return is_array($configured) ? array_values($configured) : [];
  }

  private function toCommand(string|array $entry): array
  {
    if (is_array($entry)) {
      return array_values(array_map('strval', $entry));
    }

    $entry = trim($entry);

    if ($entry === '') {
      throw new UpdateException('A post-apply command is empty.', 'Empty post_apply command entry.');
    }

    if (str_starts_with($entry, 'artisan:')) {
      $arguments = preg_split('/\s+/', trim(substr($entry, strlen('artisan:')))) ?: [];

      return $this->commandRunner->artisanCommand($arguments);
    }

    // Bare token is treated as an artisan command name.
    return $this->commandRunner->artisanCommand(preg_split('/\s+/', $entry) ?: []);
  }
}
