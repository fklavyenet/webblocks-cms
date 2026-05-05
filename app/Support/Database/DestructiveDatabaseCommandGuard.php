<?php

namespace App\Support\Database;

use RuntimeException;

class DestructiveDatabaseCommandGuard
{
    private const BLOCKED_COMMANDS = [
        'migrate:fresh',
        'migrate:reset',
        'migrate:refresh',
        'db:wipe',
    ];

    public function blockedCommands(): array
    {
        return self::BLOCKED_COMMANDS;
    }

    public function shouldBlock(?string $command, ?string $environment = null, ?bool $allowOverride = null): bool
    {
        $normalizedCommand = $this->normalizeCommand($command);

        if ($normalizedCommand === '') {
            return false;
        }

        $environment = strtolower(trim((string) ($environment ?? app()->environment())));

        if ($environment === 'testing') {
            return false;
        }

        if ($allowOverride ?? (bool) config('cms.database.allow_destructive_commands', false)) {
            return false;
        }

        return in_array($normalizedCommand, self::BLOCKED_COMMANDS, true);
    }

    public function ensureAllowed(?string $command, ?string $environment = null, ?bool $allowOverride = null): void
    {
        if (! $this->shouldBlock($command, $environment, $allowOverride)) {
            return;
        }

        throw new RuntimeException($this->blockMessage());
    }

    public function blockMessage(): string
    {
        return 'Destructive database command blocked by WebBlocks CMS safety guard. Local CMS databases may contain active content. Create a safety dump first with: ddev export-db --file=before-destructive-db-command.sql.gz';
    }

    public function normalizeCommand(?string $command): string
    {
        $value = trim((string) $command);

        if ($value === '') {
            return '';
        }

        return strtok($value, ' ') ?: '';
    }
}
