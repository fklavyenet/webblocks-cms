<?php

namespace App\Support\System;

use App\Models\SystemUpdateRun;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemUpdater
{
    public function __construct(
        private readonly UpdateChecker $updateChecker,
        private readonly InstalledVersionStore $installedVersionStore,
    ) {}

    public function run(): array
    {
        $status = $this->updateChecker->status();
        $fromVersion = $status['current_version'];
        $toVersion = $status['latest_version'];

        if ($status['up_to_date']) {
            throw new \RuntimeException('WebBlocks CMS is already up to date.');
        }

        if (app()->isDownForMaintenance()) {
            throw new \RuntimeException('The application is already in maintenance mode.');
        }

        DB::connection()->getPdo();

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
                $log[] = 'cache:clear warning: '.$throwable->getMessage();
            }

            $this->installedVersionStore->update($toVersion);
            $log[] = 'Updated APP_VERSION to '.$toVersion.'.';

            $this->runCommand('up', [], $log);
            $maintenanceEnabled = false;

            $run = $this->createUpdateRun([
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
                'status' => SystemUpdateRun::STATUS_SUCCESS,
                'output' => implode(PHP_EOL.PHP_EOL, $log),
            ]);

            return [
                'run' => $run,
                'output' => $log,
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

            $this->createUpdateRun([
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
                'status' => SystemUpdateRun::STATUS_FAILED,
                'output' => implode(PHP_EOL.PHP_EOL, $log),
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

        return SystemUpdateRun::query()->create($attributes);
    }
}
