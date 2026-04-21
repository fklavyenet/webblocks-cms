<?php

namespace App\Support\System;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class SystemBackupRestoreMaintenanceRunner
{
    public function run(array &$output = []): void
    {
        $this->runArtisanCommand('storage:link', ['--force' => true], $output);
        $this->runArtisanCommand('optimize:clear', [], $output);
    }

    private function runArtisanCommand(string $command, array $parameters, array &$output): void
    {
        $exitCode = Artisan::call($command, $parameters);
        $commandOutput = trim(Artisan::output());

        $output[] = '$ php artisan '.$command;

        if ($commandOutput !== '') {
            $output[] = $commandOutput;
        }

        if ($exitCode !== 0) {
            throw new RuntimeException('Restore maintenance command failed: '.$command.'.');
        }
    }
}
