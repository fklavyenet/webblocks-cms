<?php

namespace Tests\Feature\Console;

use App\Support\Database\DestructiveDatabaseCommandGuard;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DestructiveDatabaseCommandGuardTest extends TestCase
{
    #[Test]
    public function destructive_commands_are_blocked_in_local_like_environments_when_override_is_false(): void
    {
        $guard = app(DestructiveDatabaseCommandGuard::class);

        foreach (['migrate:fresh', 'migrate:reset', 'migrate:refresh', 'db:wipe'] as $command) {
            $this->assertTrue($guard->shouldBlock($command, 'local', false));
        }
    }

    #[Test]
    public function override_allows_destructive_commands_to_pass(): void
    {
        $guard = app(DestructiveDatabaseCommandGuard::class);

        foreach (['migrate:fresh', 'migrate:reset', 'migrate:refresh', 'db:wipe'] as $command) {
            $this->assertFalse($guard->shouldBlock($command, 'local', true));
        }
    }

    #[Test]
    public function testing_environment_is_not_blocked(): void
    {
        $guard = app(DestructiveDatabaseCommandGuard::class);

        foreach (['migrate:fresh', 'migrate:reset', 'migrate:refresh', 'db:wipe'] as $command) {
            $this->assertFalse($guard->shouldBlock($command, 'testing', false));
        }
    }

    #[Test]
    public function harmless_commands_are_not_blocked(): void
    {
        $guard = app(DestructiveDatabaseCommandGuard::class);

        foreach (['migrate', 'migrate:status', 'db:seed', 'test', 'project:webblocksui-import'] as $command) {
            $this->assertFalse($guard->shouldBlock($command, 'local', false));
        }
    }

    #[Test]
    public function blocked_message_is_clear_and_recommends_a_safety_dump(): void
    {
        $guard = app(DestructiveDatabaseCommandGuard::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Destructive database command blocked by WebBlocks CMS safety guard.');
        $this->expectExceptionMessage('ddev export-db --file=before-destructive-db-command.sql.gz');

        $guard->ensureAllowed('migrate:fresh', 'local', false);
    }
}
