<?php

namespace App\Console\Commands;

use App\Support\System\SystemBackupRestoreManager;
use Illuminate\Console\Command;
use Throwable;

class SystemBackupRestoreCommand extends Command
{
    protected $signature = 'system:backup:restore {backup : Backup record ID or relative archive path on the backups disk} {--force : Skip the interactive confirmation prompt}';

    protected $description = 'Restore a WebBlocks CMS backup archive into the current database and uploads storage';

    public function __construct(
        private readonly SystemBackupRestoreManager $restoreManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $target = $this->restoreManager->describeRestoreSource((string) $this->argument('backup'));
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->line('About to restore '.$target['display_name'].'.');
        $this->line('This will replace the current database and restore storage/app/public from the backup archive.');

        if (! $this->option('force') && ! $this->confirm('Continue with this restore?', false)) {
            $this->warn('Restore cancelled.');

            return self::FAILURE;
        }

        try {
            $result = $this->restoreManager->restoreReference((string) $this->argument('backup'));

            if ($result->safetyBackup) {
                $this->info('Pre-restore safety backup created: #'.$result->safetyBackup->id.' '.$result->safetyBackup->archive_filename);
            }

            foreach ($result->output as $line) {
                $this->line($line);
            }

            $this->info($result->summary());

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }
    }
}
