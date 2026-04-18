<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\System\Updates\UpdateCheckResult;
use App\Support\System\Updates\UpdateServerClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemUpdatesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function system_updates_page_renders(): void
    {
        $user = User::factory()->create();
        $this->mockClientResult('up_to_date', 'Already up to date', 'This install already matches the latest published release for the selected channel.');

        $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

        $response->assertOk();
        $response->assertSee('System Updates');
        $response->assertSee('Already up to date');
    }

    #[Test]
    public function check_flow_shows_correct_state_from_mocked_client_response(): void
    {
        $user = User::factory()->create();
        $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.');

        $response = $this->actingAs($user)->get(route('admin.system.updates.check'));

        $response->assertRedirect(route('admin.system.updates.index'));

        $followUp = $this->actingAs($user)->get(route('admin.system.updates.index'));
        $followUp->assertSee('Update available');
        $followUp->assertSee('Download package');
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
                'download_url' => 'https://updates.example.test/downloads/webblocks-cms-'.$latestVersion.'.zip',
                'checksum_sha256' => 'abcdef123456',
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

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
