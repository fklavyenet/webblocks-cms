<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\PostApply;

use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateCommandRunner;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateMigrationRunner;

/**
 * Default post-apply runner (Slice 5): runs strategy-aware DB migrations first,
 * then the configured allowlisted post_apply commands. Migrations are gated by
 * `migrations.enabled`.
 */
class MigratingPostApplyRunner extends ConfiguredCommandsPostApplyRunner
{
  public function __construct(
    UpdateCommandRunner $commandRunner,
    private readonly UpdateMigrationRunner $migrationRunner,
  ) {
    parent::__construct($commandRunner);
  }

  public function run(string $commandDir, array &$output): void
  {
    // Full-root source-only apps refresh vendor/ + the autoloader from the new
    // lock before anything boots the updated code (migrations, artisan commands).
    if ((bool) config('publisher-client.apply.composer_install', false)) {
      $args = config('publisher-client.apply.composer_install_args', ['install', '--no-interaction']);
      $this->commandRunner()->run(
        $this->commandRunner()->composerCommand(is_array($args) ? $args : ['install']),
        $commandDir,
        $output,
      );
    }

    if ((bool) config('publisher-client.migrations.enabled', true)) {
      $this->migrationRunner->run($commandDir, $output);
    }

    parent::run($commandDir, $output);
  }
}
