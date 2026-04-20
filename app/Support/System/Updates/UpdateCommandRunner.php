<?php

namespace App\Support\System\Updates;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

class UpdateCommandRunner
{
    public function run(array $command, string $workingDirectory, array &$output): void
    {
        $output[] = '$ '.$this->formatCommand($command);

        $result = Process::path($workingDirectory)
            ->timeout((int) config('webblocks-updates.installer.command_timeout_seconds', 600))
            ->run($command);

        $this->appendProcessOutput($result, $output);

        if (! $result->successful()) {
            throw new UpdateException(
                'The update command sequence failed. Review the latest update log for details.',
                'Command failed: '.$this->formatCommand($command),
            );
        }
    }

    private function appendProcessOutput(ProcessResult $result, array &$output): void
    {
        $stdout = trim($result->output());
        $stderr = trim($result->errorOutput());

        if ($stdout !== '') {
            $output[] = $stdout;
        }

        if ($stderr !== '') {
            $output[] = $stderr;
        }
    }

    private function formatCommand(array $command): string
    {
        return implode(' ', array_map(static function (string $part): string {
            if ($part === '' || preg_match('/[^A-Za-z0-9_:\/.=-]/', $part) === 1) {
                return "'".str_replace("'", "'\\''", $part)."'";
            }

            return $part;
        }, $command));
    }
}
