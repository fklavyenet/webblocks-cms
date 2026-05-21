<?php

namespace Tests\Unit\System\Updates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\System\Updates\UpdateCommandRunner;
use WebBlocks\Cms\Support\System\Updates\UpdateMigrationRunner;

class UpdateMigrationRunnerTest extends TestCase
{
    private array $temporaryDirectories = [];

    #[Test]
    public function package_strategy_skips_host_migrations_when_no_package_update_migrations_exist(): void
    {
        $targetRoot = $this->makeTargetRoot('consumer/app');
        File::ensureDirectoryExists($targetRoot.'/database/migrations');
        File::put($targetRoot.'/database/migrations/0001_01_01_000000_create_users_table.php', "<?php\n");

        config()->set('webblocks-updates.installer.migration_strategy', 'package');
        $commandRunner = new FakeMigrationCommandRunner;
        $output = [];

        (new UpdateMigrationRunner($commandRunner))->run($targetRoot, $output);

        $this->assertSame([], $commandRunner->commands);
        $this->assertStringContainsString('host application migrations were skipped', implode("\n", $output));
        $this->assertStringNotContainsString('0001_01_01_000000_create_users_table', implode("\n", $output));
    }

    #[Test]
    public function package_strategy_runs_only_dedicated_package_update_migrations_when_present(): void
    {
        $targetRoot = $this->makeTargetRoot('consumer/app');
        $migrationPath = $targetRoot.'/packages/webblocks-cms/database/migrations/updates';
        File::ensureDirectoryExists($migrationPath);
        File::put($migrationPath.'/2026_05_21_120000_package_update.php', "<?php\n");
        File::ensureDirectoryExists($targetRoot.'/database/migrations');
        File::put($targetRoot.'/database/migrations/0001_01_01_000000_create_users_table.php', "<?php\n");

        config()->set('webblocks-updates.installer.migration_strategy', 'package');
        $commandRunner = new FakeMigrationCommandRunner;
        $output = [];

        (new UpdateMigrationRunner($commandRunner))->run($targetRoot, $output);

        $this->assertSame([
            'php artisan migrate --path='.$migrationPath.' --realpath --force',
        ], $commandRunner->commands);
        $this->assertStringContainsString('package-native update migrations at '.$migrationPath, implode("\n", $output));
        $this->assertStringContainsString('Host application migrations were skipped', implode("\n", $output));
    }

    #[Test]
    public function source_maintained_strategy_keeps_root_migration_authority(): void
    {
        $targetRoot = $this->makeTargetRoot('fklavyenet/webblocks-cms');
        File::ensureDirectoryExists($targetRoot.'/database/migrations');
        File::ensureDirectoryExists($targetRoot.'/packages/webblocks-cms');

        config()->set('webblocks-updates.installer.migration_strategy', 'auto');
        $commandRunner = new FakeMigrationCommandRunner;
        $output = [];

        (new UpdateMigrationRunner($commandRunner))->run($targetRoot, $output);

        $this->assertSame(['php artisan migrate --force'], $commandRunner->commands);
        $this->assertStringContainsString('Migration strategy: source-maintained root migrations.', implode("\n", $output));
    }

    private function makeTargetRoot(string $composerName): string
    {
        $path = storage_path('app/testing-update-migrations/'.Str::uuid());
        File::ensureDirectoryExists($path);
        File::put($path.'/composer.json', json_encode(['name' => $composerName], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->temporaryDirectories[] = $path;

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            File::deleteDirectory($directory);
        }

        parent::tearDown();
    }
}

class FakeMigrationCommandRunner extends UpdateCommandRunner
{
    public array $commands = [];

    public function run(array $command, string $workingDirectory, array &$output): void
    {
        $formatted = implode(' ', array_map(static function (string $part): string {
            return $part === PHP_BINARY ? 'php' : $part;
        }, $command));

        $this->commands[] = $formatted;
        $output[] = '$ '.$formatted;
    }

    public function phpBinary(): string
    {
        return 'php';
    }
}
