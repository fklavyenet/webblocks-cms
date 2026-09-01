<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;

final class UpdateCommand extends Command
{
    protected $signature = 'publisher:update {--force : Skip the confirmation prompt}';

    protected $description = 'Download, verify and apply the latest release (self-update).';

    public function handle(SystemUpdater $updater): int
    {
        if (! $this->option('force') && ! $this->confirm('Apply the latest available update now?', true)) {
            $this->warn('Update cancelled.');

            return self::SUCCESS;
        }

        try {
            $result = $updater->run();
        } catch (UpdateException $exception) {
            $this->error($exception->userMessage());

            return self::FAILURE;
        }

        $this->info($result->summary);
        $this->table(['Field', 'Value'], [
            ['From', $result->fromVersion],
            ['To', $result->toVersion],
            ['Status', $result->status],
            ['Warnings', (string) $result->warningCount],
            ['Duration (ms)', (string) $result->durationMs],
        ]);

        return self::SUCCESS;
    }
}
