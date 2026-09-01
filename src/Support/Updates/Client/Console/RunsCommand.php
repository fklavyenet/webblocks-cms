<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Support\Updates\Client\Contracts\RunRecorder;
use WebBlocks\Cms\Support\Updates\Client\Support\RunStatus;

final class RunsCommand extends Command
{
    protected $signature = 'publisher:updates:runs
        {--failed : Show only failed/restored runs}
        {--last : Show only the most recent run}
        {--prune : Trim stored runs to the configured retention}';

    protected $description = 'Show recorded update-run history.';

    public function handle(RunRecorder $recorder): int
    {
        if ($this->option('prune')) {
            $recorder->prune();
            $this->info('Runs pruned to the configured retention.');
        }

        $runs = $recorder->all();

        if ($this->option('failed')) {
            $runs = array_values(array_filter(
                $runs,
                fn (array $run): bool => in_array($run['status'] ?? '', [RunStatus::FAILED, RunStatus::RESTORED], true),
            ));
        }

        if ($this->option('last')) {
            $runs = array_slice($runs, 0, 1);
        }

        if ($runs === []) {
            $this->line('No update runs recorded.');

            return self::SUCCESS;
        }

        $this->table(
            ['From', 'To', 'Status', 'Warnings', 'Finished', 'Summary'],
            array_map(fn (array $run): array => [
                $run['from_version'] ?? '—',
                $run['to_version'] ?? '—',
                $run['status'] ?? '—',
                (string) ($run['warning_count'] ?? 0),
                $run['finished_at'] ?? '—',
                mb_strimwidth((string) ($run['summary'] ?? ''), 0, 50, '…'),
            ], $runs),
        );

        return self::SUCCESS;
    }
}
