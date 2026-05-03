<?php

namespace Tests\Feature\Admin;

use App\Models\SystemUpdateRun;
use App\Models\SystemBackup;
use App\Models\User;
use App\Support\WebBlocks;
use App\Support\System\SystemBackupManager;
use App\Support\System\InstalledVersionStore;
use App\Support\System\Updates\UpdateCheckResult;
use App\Support\System\Updates\UpdateCommandRunner;
use App\Support\System\Updates\UpdateException;
use App\Support\System\Updates\UpdateServerClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class SystemUpdatesTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryDirectories = [];

    private ?FakeUpdateCommandRunner $fakeCommandRunner = null;

    #[Test]
    public function system_updates_page_renders(): void
    {
        $user = User::factory()->superAdmin()->create();
        app(InstalledVersionStore::class)->persist('0.1.4');
        $this->mockClientResult('up_to_date', 'Already up to date', 'This install already matches the latest published release for the selected channel.', true, '0.1.4');

        $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

        $response->assertOk();
        $response->assertSee('System Updates');
        $response->assertSee('Already up to date');
        $response->assertSee('Installed version');
        $response->assertSee(WebBlocks::version());
        $response->assertSee('<div class="wb-text-sm wb-text-muted">Latest version</div>', false);
        $response->assertSee('Update Summary');
        $response->assertSee('Actions');
        $response->assertSee('Check again');
        $response->assertDontSee('Recent Backup');
        $response->assertSee('Technical details');
        $response->assertSee('WebBlocks CMS v'.WebBlocks::version());
    }

    #[Test]
    public function disabled_client_state_is_honest_when_no_installed_version_has_been_recorded_yet(): void
    {
        $user = User::factory()->superAdmin()->create();

        config()->set('webblocks-updates.enabled', false);
        config()->set('app.version', '0.1.8');

        $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

        $response->assertOk();
        $response->assertSee('Update checks disabled');
        $response->assertSee(WebBlocks::version());
        $response->assertSee('The CMS update client is disabled in configuration.');
    }

    #[Test]
    public function check_flow_shows_correct_state_from_mocked_client_response(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.');

        $response = $this->actingAs($user)->get(route('admin.system.updates.check'));

        $response->assertRedirect(route('admin.system.updates.index'));
        $response->assertSessionHas('status', 'Update 0.2.0 is available.');

        $followUp = $this->actingAs($user)->get(route('admin.system.updates.index'));
        $followUp->assertSee('Update available');
        $followUp->assertSee('Update now');
        $followUp->assertDontSee('Download package');
        $followUp->assertSee('Check again');
        $followUp->assertSee('Latest version');
        $followUp->assertSee('A pre-update backup will be created automatically before installation.');
        $followUp->assertDontSee('Automatic backup is not created before update in this version.');
    }

    #[Test]
    public function page_can_show_update_server_unavailable_state(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->mockClientResult('server_unreachable', 'Update server unavailable', 'The update server could not be reached within the configured timeout.', false, null, null, 'server_unreachable', 'timeout');

        $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

        $response->assertOk();
        $response->assertSee('Update server unavailable');
        $response->assertSee('Server detail');
        $response->assertSee('Technical details');
    }

    #[Test]
    public function successful_update_flow_persists_version_records_run_and_updates_sidebar(): void
    {
        $user = User::factory()->superAdmin()->create();
        app(InstalledVersionStore::class)->persist('0.1.0');
        Storage::fake('backups');

        [$targetRoot, $archivePath, $checksum] = $this->prepareSuccessfulUpdateScenario();

        config()->set('webblocks-updates.installer.target_path', $targetRoot);
        $this->bindFakeCommandRunner();
        $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.', true, '0.2.0', ['status' => 'compatible', 'reasons' => []], null, null, 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip', $checksum);

        Http::fake([
            'https://updates.example.test/downloads/*' => Http::response(File::get($archivePath), 200, ['Content-Type' => 'application/zip']),
        ]);

        $response = $this->actingAs($user)->post(route('admin.system.updates.store'));

        $response->assertRedirect(route('admin.system.updates.index'));
        $response->assertSessionHas('status', 'Updated to 0.2.0 successfully.');

        $this->assertSame(WebBlocks::version(), app(InstalledVersionStore::class)->currentVersion());
        $this->assertSame('new-artisan', trim((string) File::get($targetRoot.'/artisan')));
        $this->assertSame('new-bootstrap', trim((string) File::get($targetRoot.'/bootstrap/app.php')));
        $this->assertSame('APP_NAME=Original', trim((string) File::get($targetRoot.'/.env')));
        $this->assertSame('runtime-data', trim((string) File::get($targetRoot.'/storage/app/public/user.txt')));
        $this->assertSame('runtime-cache', trim((string) File::get($targetRoot.'/bootstrap/cache/config.php')));
        $this->assertSame("<?php\n\nreturn ['source' => 'runtime-project'];\n", File::get($targetRoot.'/project/config/sites.php'));

        $run = SystemUpdateRun::query()->latest()->first();
        $this->assertNotNull($run);
        $this->assertSame(SystemUpdateRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(WebBlocks::version(), $run->from_version);
        $this->assertSame('0.2.0', $run->to_version);
        $this->assertStringContainsString('Using PHP binary: php', (string) $run->output);
        $this->assertStringContainsString('Package checksum verified', (string) $run->output);
        $this->assertStringContainsString('composer install', (string) $run->output);
        $this->assertStringContainsString('Pre-update backup created:', (string) $run->output);

        $backup = SystemBackup::query()->latest()->first();
        $this->assertNotNull($backup);
        $this->assertSame(SystemBackup::TYPE_PRE_UPDATE, $backup->type);
        $this->assertSame(SystemBackup::STATUS_COMPLETED, $backup->status);

        $sidebar = $this->actingAs($user)->get(route('admin.system.updates.index'));
        $sidebar->assertSee('WebBlocks CMS v'.WebBlocks::version());
        $sidebar->assertDontSee('Download package');
    }

    #[Test]
    public function failed_update_flow_keeps_version_old_records_failure_and_recovers_maintenance(): void
    {
        $user = User::factory()->superAdmin()->create();
        app(InstalledVersionStore::class)->persist('0.1.0');
        Storage::fake('backups');

        [$targetRoot, $archivePath, $checksum] = $this->prepareSuccessfulUpdateScenario();

        config()->set('webblocks-updates.installer.target_path', $targetRoot);
        $runner = $this->bindFakeCommandRunner([
            'php artisan migrate --force' => 1,
        ]);
        $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.', true, '0.2.0', ['status' => 'compatible', 'reasons' => []], null, null, 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip', $checksum);

        Http::fake([
            'https://updates.example.test/downloads/*' => Http::response(File::get($archivePath), 200, ['Content-Type' => 'application/zip']),
        ]);

        $response = $this->actingAs($user)->from(route('admin.system.updates.index'))->post(route('admin.system.updates.store'));

        $response->assertRedirect(route('admin.system.updates.index'));
        $response->assertSessionHasErrors(['system_update']);
        $this->assertSame(WebBlocks::version(), app(InstalledVersionStore::class)->currentVersion());

        $run = SystemUpdateRun::query()->latest()->first();
        $this->assertNotNull($run);
        $this->assertSame(SystemUpdateRun::STATUS_FAILED, $run->status);
        $this->assertStringContainsString('Command failed: php artisan migrate --force', (string) $run->output);
        $this->assertStringNotContainsString('php-fpm', (string) $run->output);
        $this->assertContains('php artisan up', $runner->commands);
    }

    #[Test]
    public function second_update_cannot_start_while_lock_is_held(): void
    {
        $user = User::factory()->superAdmin()->create();
        app(InstalledVersionStore::class)->persist('0.1.0');
        $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.');

        $lock = Cache::lock((string) config('webblocks-updates.installer.lock_name', 'system-updates:run'), 30);
        $this->assertTrue($lock->get());

        try {
            $response = $this->actingAs($user)->from(route('admin.system.updates.index'))->post(route('admin.system.updates.store'));

            $response->assertRedirect(route('admin.system.updates.index'));
            $response->assertSessionHasErrors(['system_update']);
            $this->assertDatabaseCount('system_update_runs', 0);
        } finally {
            $lock->release();
        }
    }

    #[Test]
    public function update_page_renders_backup_guarantee_and_not_old_warning_text(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.');

        $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

        $response->assertOk();
        $response->assertSee('A pre-update backup will be created automatically before installation.');
        $response->assertSee('Download backup before update');
        $response->assertSee('A pre-update backup is always created. Enable this option if you also want to download the backup file before installation starts.');
        $response->assertDontSee('Automatic backup is not created before update in this version.');
    }

    #[Test]
    public function update_does_not_install_if_pre_update_backup_creation_fails(): void
    {
        $user = User::factory()->superAdmin()->create();
        app(InstalledVersionStore::class)->persist('0.1.0');
        $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.');

        $backupManager = Mockery::mock(SystemBackupManager::class);
        $backupManager->shouldReceive('createPreUpdateBackup')->once()->andThrow(new \RuntimeException('backup disk unavailable'));
        $this->app->instance(SystemBackupManager::class, $backupManager);

        $response = $this->actingAs($user)
            ->from(route('admin.system.updates.index'))
            ->post(route('admin.system.updates.store'));

        $response->assertRedirect(route('admin.system.updates.index'));
        $response->assertSessionHasErrors(['system_update' => 'Update was not installed because the pre-update backup could not be created.']);
        $this->assertSame(WebBlocks::version(), app(InstalledVersionStore::class)->currentVersion());
        $this->assertDatabaseCount('system_update_runs', 0);
    }

    #[Test]
    public function checked_download_checkbox_creates_backup_and_shows_pending_continue_screen(): void
    {
        $user = User::factory()->superAdmin()->create();
        app(InstalledVersionStore::class)->persist('0.1.0');
        Storage::fake('backups');
        $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.');

        $response = $this->actingAs($user)->post(route('admin.system.updates.store'), [
            'download_pre_update_backup' => '1',
        ]);

        $response->assertRedirect(route('admin.system.updates.index'));

        $run = SystemUpdateRun::query()->latest()->firstOrFail();
        $backup = SystemBackup::query()->latest()->firstOrFail();

        $this->assertSame(SystemUpdateRun::STATUS_PENDING, $run->status);
        $this->assertSame(SystemBackup::TYPE_PRE_UPDATE, $backup->type);

        $followUp = $this->actingAs($user)->get(route('admin.system.updates.index'));
        $followUp->assertSee('Pre-update backup created.');
        $followUp->assertSee('Download backup');
        $followUp->assertSee('Continue update');
        $followUp->assertSee('Cancel');
        $followUp->assertSee((string) $backup->archive_filename);
    }

    #[Test]
    public function continue_update_installs_the_original_pending_target_version(): void
    {
        $user = User::factory()->superAdmin()->create();
        app(InstalledVersionStore::class)->persist('0.1.0');
        Storage::fake('backups');

        [$targetRoot, $archivePath, $checksum] = $this->prepareSuccessfulUpdateScenario();

        config()->set('webblocks-updates.installer.target_path', $targetRoot);
        $this->bindFakeCommandRunner();
        $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.', true, '0.2.0', ['status' => 'compatible', 'reasons' => []], null, null, 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip', $checksum);

        Http::fake([
            'https://updates.example.test/downloads/*' => Http::response(File::get($archivePath), 200, ['Content-Type' => 'application/zip']),
        ]);

        $this->actingAs($user)->post(route('admin.system.updates.store'), [
            'download_pre_update_backup' => '1',
        ]);

        $response = $this->actingAs($user)->post(route('admin.system.updates.continue'));

        $response->assertRedirect(route('admin.system.updates.index'));
        $response->assertSessionHas('status', 'Updated to 0.2.0 successfully.');
        $this->assertSame(WebBlocks::version(), app(InstalledVersionStore::class)->currentVersion());
    }

    #[Test]
    public function stale_pending_update_cannot_be_continued(): void
    {
        $user = User::factory()->superAdmin()->create();

        Cache::put('system-updates:pending', [
            'run_id' => 99999,
            'from_version' => '0.1.0',
            'to_version' => '0.2.0',
            'backup_id' => 99999,
            'release' => ['version' => '0.2.0'],
            'download_url' => 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip',
        ], now()->addHour());

        $response = $this->actingAs($user)
            ->from(route('admin.system.updates.index'))
            ->post(route('admin.system.updates.continue'));

        $response->assertRedirect(route('admin.system.updates.index'));
        $response->assertSessionHasErrors(['system_update']);
    }

    #[Test]
    public function pending_update_can_be_cancelled_without_installing(): void
    {
        $user = User::factory()->superAdmin()->create();
        app(InstalledVersionStore::class)->persist('0.1.0');
        Storage::fake('backups');
        $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.');

        $this->actingAs($user)->post(route('admin.system.updates.store'), [
            'download_pre_update_backup' => '1',
        ]);

        $response = $this->actingAs($user)->post(route('admin.system.updates.cancel'));

        $response->assertRedirect(route('admin.system.updates.index'));
        $response->assertSessionHas('status', 'Pending update cancelled. The pre-update backup was kept.');
        $this->assertSame(WebBlocks::version(), app(InstalledVersionStore::class)->currentVersion());
        $this->assertSame(SystemUpdateRun::STATUS_CANCELLED, SystemUpdateRun::query()->latest()->firstOrFail()->status);
    }

    private function mockClientResult(
        string $state,
        string $label,
        string $message,
        bool $serverReachable = true,
        ?string $latestVersion = '0.2.0',
        ?array $compatibility = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?string $downloadUrl = null,
        ?string $checksum = 'abcdef123456',
    ): void {
        $client = Mockery::mock(UpdateServerClient::class);
        $client->shouldReceive('check')->andReturn(new UpdateCheckResult(
            state: $state,
            label: $label,
            message: $message,
            badgeClass: $state === 'server_unreachable' ? 'wb-status-danger' : 'wb-status-info',
            serverReachable: $serverReachable,
            apiVersion: '1',
            serverUrl: 'https://updates.example.test',
            product: 'webblocks-cms',
            channel: 'stable',
            installedVersion: '0.1.0',
            latestVersion: $latestVersion,
            updateAvailable: $state === 'update_available',
            compatibility: $compatibility ?? ['status' => 'compatible', 'reasons' => []],
            release: $latestVersion ? [
                'version' => $latestVersion,
                'name' => 'WebBlocks CMS '.$latestVersion,
                'description' => 'Stability and admin improvements.',
                'changelog' => 'Compact changelog text here.',
                'download_url' => $downloadUrl ?? 'https://updates.example.test/downloads/webblocks-cms-'.$latestVersion.'.zip',
                'checksum_sha256' => $checksum,
                'published_at' => '2026-04-19T10:00:00Z',
                'is_critical' => false,
                'is_security' => false,
                'requirements' => [
                    'min_php_version' => '8.3.0',
                    'min_laravel_version' => '13.0.0',
                    'supported_from_version' => '0.1.0',
                    'supported_until_version' => null,
                ],
            ] : null,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            checkedAt: CarbonImmutable::now(),
        ));

        $this->app->instance(UpdateServerClient::class, $client);
    }

    private function prepareSuccessfulUpdateScenario(): array
    {
        $targetRoot = $this->makeTemporaryDirectory('target-root');
        File::ensureDirectoryExists($targetRoot.'/bootstrap/cache');
        File::ensureDirectoryExists($targetRoot.'/storage/app/public');
        File::put($targetRoot.'/artisan', "old-artisan\n");
        File::put($targetRoot.'/bootstrap/app.php', "old-bootstrap\n");
        File::put($targetRoot.'/composer.json', json_encode(['name' => 'fklavyenet/webblocks-cms'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($targetRoot.'/.env', "APP_NAME=Original\n");
        File::put($targetRoot.'/storage/app/public/user.txt', "runtime-data\n");
        File::put($targetRoot.'/bootstrap/cache/config.php', "runtime-cache\n");
        File::ensureDirectoryExists($targetRoot.'/project/config');
        File::put($targetRoot.'/project/config/sites.php', "<?php\n\nreturn ['source' => 'runtime-project'];\n");

        $archiveDirectory = $this->makeTemporaryDirectory('release-archive');
        $archivePath = $archiveDirectory.'/webblocks-cms-0.2.0.zip';
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $archive->addFromString('artisan', "new-artisan\n");
        $archive->addFromString('bootstrap/app.php', "new-bootstrap\n");
        $archive->addFromString('composer.json', json_encode(['name' => 'fklavyenet/webblocks-cms', 'version' => '0.2.0'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $archive->addFromString('.env', "APP_NAME=Overwritten\n");
        $archive->addFromString('storage/app/public/user.txt', "should-not-overwrite\n");
        $archive->addFromString('bootstrap/cache/config.php', "should-not-overwrite\n");
        $archive->addFromString('project/config/sites.php', "<?php\n\nreturn ['source' => 'release-package'];\n");
        $archive->close();

        return [$targetRoot, $archivePath, hash_file('sha256', $archivePath)];
    }

    private function bindFakeCommandRunner(array $exitCodes = []): FakeUpdateCommandRunner
    {
        $runner = new FakeUpdateCommandRunner($exitCodes);
        $this->app->instance(UpdateCommandRunner::class, $runner);
        $this->fakeCommandRunner = $runner;

        return $runner;
    }

    private function makeTemporaryDirectory(string $prefix): string
    {
        $path = storage_path('app/testing-system-updates/'.$prefix.'-'.Str::uuid());
        File::ensureDirectoryExists($path);
        $this->temporaryDirectories[] = $path;

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            File::deleteDirectory($directory);
        }

        Mockery::close();

        parent::tearDown();
    }
}

class FakeUpdateCommandRunner extends UpdateCommandRunner
{
    public array $commands = [];

    public function __construct(
        private readonly array $exitCodes = [],
    ) {}

    public function run(array $command, string $workingDirectory, array &$output): void
    {
        $formatted = implode(' ', array_map(static function (string $part): string {
            return $part === PHP_BINARY ? 'php' : $part;
        }, $command));

        $this->commands[] = $formatted;
        $output[] = '$ '.$formatted;
        $output[] = 'Simulated command in '.$workingDirectory;

        if (($this->exitCodes[$formatted] ?? 0) !== 0) {
            throw new UpdateException(
                'The update command sequence failed. Review the latest update log for details.',
                'Command failed: '.$formatted,
            );
        }
    }

    public function phpBinary(): string
    {
        return 'php';
    }
}
