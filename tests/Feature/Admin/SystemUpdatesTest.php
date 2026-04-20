<?php

namespace Tests\Feature\Admin;

use App\Models\SystemUpdateRun;
use App\Models\User;
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
        $user = User::factory()->create();
        app(InstalledVersionStore::class)->persist('0.1.4');
        $this->mockClientResult('up_to_date', 'Already up to date', 'This install already matches the latest published release for the selected channel.');

        $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

        $response->assertOk();
        $response->assertSee('System Updates');
        $response->assertSee('Already up to date');
        $response->assertSee('Installed version');
        $response->assertSee('0.1.4');
        $response->assertSee('Latest version');
        $response->assertSee('0.2.0');
        $response->assertSee('Update Summary');
        $response->assertSee('Actions');
        $response->assertDontSee('Recent Backup');
        $response->assertSee('Technical details');
        $response->assertSee('WebBlocks CMS v0.1.4');
    }

    #[Test]
    public function check_flow_shows_correct_state_from_mocked_client_response(): void
    {
        $user = User::factory()->create();
        $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.');

        $response = $this->actingAs($user)->get(route('admin.system.updates.check'));

        $response->assertRedirect(route('admin.system.updates.index'));
        $response->assertSessionHas('status', 'Update 0.2.0 is available.');

        $followUp = $this->actingAs($user)->get(route('admin.system.updates.index'));
        $followUp->assertSee('Update available');
        $followUp->assertSee('Update now');
        $followUp->assertDontSee('Download package');
        $followUp->assertSee('Check again');
    }

    #[Test]
    public function page_can_show_update_server_unavailable_state(): void
    {
        $user = User::factory()->create();
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
        $user = User::factory()->create();
        app(InstalledVersionStore::class)->persist('0.1.0');

        [$targetRoot, $archivePath, $checksum] = $this->prepareSuccessfulUpdateScenario();

        config()->set('webblocks-updates.installer.target_path', $targetRoot);
        $this->bindFakeCommandRunner();
        $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.', true, '0.2.0', ['status' => 'compatible', 'reasons' => []], null, null, 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip', $checksum);

        Http::fake([
            'https://updates.example.test/downloads/*' => Http::response(File::get($archivePath), 200, ['Content-Type' => 'application/zip']),
        ]);

        $response = $this->actingAs($user)->post(route('admin.system.updates.store'), [
            'acknowledge_backup_risk' => '1',
        ]);

        $response->assertRedirect(route('admin.system.updates.index'));
        $response->assertSessionHas('status', 'Updated to 0.2.0 successfully.');

        $this->assertSame('0.2.0', app(InstalledVersionStore::class)->currentVersion());
        $this->assertSame('new-artisan', trim((string) File::get($targetRoot.'/artisan')));
        $this->assertSame('new-bootstrap', trim((string) File::get($targetRoot.'/bootstrap/app.php')));
        $this->assertSame('APP_NAME=Original', trim((string) File::get($targetRoot.'/.env')));
        $this->assertSame('runtime-data', trim((string) File::get($targetRoot.'/storage/app/public/user.txt')));
        $this->assertSame('runtime-cache', trim((string) File::get($targetRoot.'/bootstrap/cache/config.php')));

        $run = SystemUpdateRun::query()->latest()->first();
        $this->assertNotNull($run);
        $this->assertSame(SystemUpdateRun::STATUS_SUCCESS, $run->status);
        $this->assertSame('0.1.0', $run->from_version);
        $this->assertSame('0.2.0', $run->to_version);
        $this->assertStringContainsString('Using PHP binary: php', (string) $run->output);
        $this->assertStringContainsString('Package checksum verified', (string) $run->output);
        $this->assertStringContainsString('composer install', (string) $run->output);

        $sidebar = $this->actingAs($user)->get(route('admin.system.updates.index'));
        $sidebar->assertSee('WebBlocks CMS v0.2.0');
        $sidebar->assertDontSee('Download package');
    }

    #[Test]
    public function failed_update_flow_keeps_version_old_records_failure_and_recovers_maintenance(): void
    {
        $user = User::factory()->create();
        app(InstalledVersionStore::class)->persist('0.1.0');

        [$targetRoot, $archivePath, $checksum] = $this->prepareSuccessfulUpdateScenario();

        config()->set('webblocks-updates.installer.target_path', $targetRoot);
        $runner = $this->bindFakeCommandRunner([
            'php artisan migrate --force' => 1,
        ]);
        $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.', true, '0.2.0', ['status' => 'compatible', 'reasons' => []], null, null, 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip', $checksum);

        Http::fake([
            'https://updates.example.test/downloads/*' => Http::response(File::get($archivePath), 200, ['Content-Type' => 'application/zip']),
        ]);

        $response = $this->actingAs($user)->from(route('admin.system.updates.index'))->post(route('admin.system.updates.store'), [
            'acknowledge_backup_risk' => '1',
        ]);

        $response->assertRedirect(route('admin.system.updates.index'));
        $response->assertSessionHasErrors(['system_update']);
        $this->assertSame('0.1.0', app(InstalledVersionStore::class)->currentVersion());

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
        $user = User::factory()->create();
        app(InstalledVersionStore::class)->persist('0.1.0');
        $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.');

        $lock = Cache::lock((string) config('webblocks-updates.installer.lock_name', 'system-updates:run'), 30);
        $this->assertTrue($lock->get());

        try {
            $response = $this->actingAs($user)->from(route('admin.system.updates.index'))->post(route('admin.system.updates.store'), [
                'acknowledge_backup_risk' => '1',
            ]);

            $response->assertRedirect(route('admin.system.updates.index'));
            $response->assertSessionHasErrors(['system_update']);
            $this->assertDatabaseCount('system_update_runs', 0);
        } finally {
            $lock->release();
        }
    }

    #[Test]
    public function update_requires_backup_acknowledgement(): void
    {
        $user = User::factory()->create();
        $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.');

        $response = $this->actingAs($user)->from(route('admin.system.updates.index'))->post(route('admin.system.updates.store'));

        $response->assertRedirect(route('admin.system.updates.index'));
        $response->assertSessionHasErrors(['acknowledge_backup_risk']);
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
