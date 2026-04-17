<?php

namespace App\Support\System;

use App\Models\SystemUpdateRun;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemUpdater
{
    public function __construct(
        private readonly SystemUpdateInspector $systemUpdateInspector,
        private readonly InstalledVersionStore $installedVersionStore,
    ) {}

    public function run(?int $triggeredByUserId = null): array
    {
        $report = $this->systemUpdateInspector->report();
        $status = $report['version'];
        $eligibility = $report['eligibility'];
        $fromVersion = $status['current_version'];
        $toVersion = $status['latest_version'];
        $startedAt = now();
        $warnings = [];

        if (! $eligibility['can_update']) {
            throw new \RuntimeException($eligibility['message']);
        }

        $log = [];
        $maintenanceEnabled = false;

        try {
            $this->runCommand('down', ['--refresh' => 15], $log);
            $maintenanceEnabled = true;

            $this->runCommand('migrate', ['--force' => true], $log);
            $this->runCommand('config:clear', [], $log);
            $this->runCommand('view:clear', [], $log);

            try {
                $this->runCommand('cache:clear', [], $log);
            } catch (Throwable $throwable) {
                $warning = 'cache:clear warning: '.$throwable->getMessage();
                $warnings[] = $warning;
                $log[] = $warning;
            }

            $this->runCommand('up', [], $log);
            $maintenanceEnabled = false;

            $this->installedVersionStore->persist($toVersion);
            $log[] = 'Persisted installed version '.$toVersion.' in system settings.';

            $finishedAt = now();
            $statusName = $warnings === []
                ? SystemUpdateRun::STATUS_SUCCESS
                : SystemUpdateRun::STATUS_SUCCESS_WITH_WARNINGS;
            $summary = $warnings === []
                ? 'Update completed successfully.'
                : 'Update completed with warnings.';

            $run = $this->createUpdateRun([
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
                'status' => $statusName,
                'summary' => $summary,
                'output' => implode(PHP_EOL.PHP_EOL, $log),
                'warning_count' => count($warnings),
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
                'triggered_by_user_id' => $triggeredByUserId,
            ]);

            return [
                'run' => $run,
                'output' => $log,
                'warnings' => $warnings,
                'summary' => $summary,
            ];
        } catch (Throwable $throwable) {
            $log[] = 'Update failed: '.$throwable->getMessage();

            if ($maintenanceEnabled) {
                try {
                    $this->runCommand('up', [], $log);
                } catch (Throwable $upThrowable) {
                    $log[] = 'Failed to restore normal mode: '.$upThrowable->getMessage();
                }
            }

            $finishedAt = now();

            $this->createUpdateRun([
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
                'status' => SystemUpdateRun::STATUS_FAILED,
                'summary' => $throwable->getMessage(),
                'output' => implode(PHP_EOL.PHP_EOL, $log),
                'warning_count' => count($warnings),
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
                'triggered_by_user_id' => $triggeredByUserId,
            ]);

            throw new \RuntimeException($throwable->getMessage(), previous: $throwable);
        }
    }

    private function runCommand(string $command, array $arguments, array &$log): void
    {
        $exitCode = Artisan::call($command, $arguments);

        $output = trim(Artisan::output());
        $log[] = '$ php artisan '.$command.($arguments !== [] ? ' '.$this->formatArguments($arguments) : '');
        $log[] = $output !== '' ? $output : 'Command completed without output.';

        if ($exitCode !== 0) {
            throw new \RuntimeException('The '.$command.' command failed.');
        }
    }

    private function formatArguments(array $arguments): string
    {
        return collect($arguments)
            ->map(function ($value, $key) {
                if (is_bool($value)) {
                    return $value ? $key : null;
                }

                return $key.'='.escapeshellarg((string) $value);
            })
            ->filter()
            ->implode(' ');
    }

    private function createUpdateRun(array $attributes): ?SystemUpdateRun
    {
        if (! Schema::hasTable('system_update_runs')) {
            return null;
        }

        try {
            return SystemUpdateRun::query()->create($attributes);
        } catch (Throwable) {
            return null;
        }
    }
}
