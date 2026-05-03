<?php

namespace Tests\Feature\Console;

use App\Console\Commands\SystemBackupRestoreCommand;
use App\Models\SystemBackup;
use App\Support\System\BackupRestoreInspection;
use App\Support\System\BackupRestoreResult;
use App\Support\System\SystemBackupRestoreManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemBackupRestoreCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function command_requires_confirmation_without_force(): void
    {
        $mock = Mockery::mock(SystemBackupRestoreManager::class);
        $mock->shouldReceive('describeRestoreSource')
            ->once()
            ->with('demo.zip')
            ->andReturn([
                'display_name' => 'archive demo.zip',
            ]);
        $mock->shouldNotReceive('restoreReference');

        $this->app->instance(SystemBackupRestoreManager::class, $mock);

        $this->artisan(SystemBackupRestoreCommand::class, ['backup' => 'demo.zip'])
            ->expectsOutput('About to restore archive demo.zip.')
            ->expectsOutput('This will replace the current database and restore storage/app/public from the backup archive.')
            ->expectsConfirmation('Continue with this restore?', 'no')
            ->expectsOutput('Restore cancelled.')
            ->assertExitCode(1);
    }

    #[Test]
    public function command_restores_without_prompt_when_force_is_passed(): void
    {
        $safetyBackup = SystemBackup::query()->create([
            'type' => SystemBackup::TYPE_RESTORE_SAFETY,
            'status' => SystemBackup::STATUS_COMPLETED,
            'includes_database' => true,
            'includes_uploads' => true,
            'archive_disk' => 'backups',
            'archive_path' => 'safety.zip',
            'archive_filename' => 'safety.zip',
            'started_at' => now(),
            'finished_at' => now(),
            'summary' => 'Completed.',
        ]);

        $mock = Mockery::mock(SystemBackupRestoreManager::class);
        $mock->shouldReceive('describeRestoreSource')
            ->once()
            ->with('12')
            ->andReturn([
                'display_name' => 'backup #12 (demo.zip)',
            ]);
        $mock->shouldReceive('restoreReference')
            ->once()
            ->with('12')
            ->andReturn(new BackupRestoreResult(
                sourceBackup: null,
                sourceArchivePath: 'demo.zip',
                sourceArchiveFilename: 'demo.zip',
                inspection: new BackupRestoreInspection(
                    manifest: ['included_parts' => ['database' => true, 'uploads' => true]],
                    includesDatabase: true,
                    includesUploads: true,
                    databaseSqlPath: 'database/database.sql',
                    uploadsRootPath: 'uploads/public',
                ),
                safetyBackup: $safetyBackup,
                output: ['Archive validation passed.', 'Simulated database restore.'],
                restoreRecord: null,
            ));

        $this->app->instance(SystemBackupRestoreManager::class, $mock);

        $this->artisan(SystemBackupRestoreCommand::class, ['backup' => '12', '--force' => true])
            ->expectsOutput('About to restore backup #12 (demo.zip).')
            ->expectsOutput('This will replace the current database and restore storage/app/public from the backup archive.')
            ->expectsOutput('Pre-restore safety backup created: #'.$safetyBackup->id.' '.$safetyBackup->archive_filename)
            ->expectsOutput('Archive validation passed.')
            ->expectsOutput('Simulated database restore.')
            ->expectsOutput('Restore completed from demo.zip for database + uploads.')
            ->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
