<?php

namespace Tests\Unit\System\Updates;

use App\Support\System\InstalledVersionStore;
use App\Support\System\Updates\UpdateServerClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateServerClientTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function successful_update_available_case_is_parsed(): void
    {
        Http::fake([
            'https://updates.example.test/api/updates/webblocks-cms/latest*' => Http::response([
                'api_version' => '1',
                'status' => 'ok',
                'data' => [
                    'product' => 'webblocks-cms',
                    'channel' => 'stable',
                    'installed_version' => '0.1.0',
                    'latest_version' => '0.2.0',
                    'update_available' => true,
                    'compatibility' => ['status' => 'compatible', 'reasons' => []],
                    'release' => [
                        'version' => '0.2.0',
                        'name' => 'WebBlocks CMS 0.2.0',
                        'description' => 'Stability and admin improvements.',
                        'changelog' => 'Compact changelog text here.',
                        'download_url' => 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip',
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
                    ],
                ],
                'meta' => ['generated_at' => now()->toIso8601String()],
            ]),
        ]);

        config()->set('webblocks-updates.client.server_url', 'https://updates.example.test');

        $result = app(UpdateServerClient::class)->check();

        $this->assertSame('update_available', $result->state);
        $this->assertTrue($result->updateAvailable);
        $this->assertSame('0.2.0', $result->latestVersion);
    }

    #[Test]
    public function up_to_date_case_is_parsed(): void
    {
        Http::fake([
            '*' => Http::response([
                'api_version' => '1',
                'status' => 'ok',
                'data' => [
                    'product' => 'webblocks-cms',
                    'channel' => 'stable',
                    'installed_version' => '0.2.0',
                    'latest_version' => '0.2.0',
                    'update_available' => false,
                    'compatibility' => ['status' => 'compatible', 'reasons' => []],
                    'release' => [
                        'version' => '0.2.0',
                        'name' => 'WebBlocks CMS 0.2.0',
                        'description' => null,
                        'changelog' => null,
                        'download_url' => 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip',
                        'checksum_sha256' => null,
                        'published_at' => '2026-04-19T10:00:00Z',
                        'is_critical' => false,
                        'is_security' => false,
                        'requirements' => [
                            'min_php_version' => null,
                            'min_laravel_version' => null,
                            'supported_from_version' => null,
                            'supported_until_version' => null,
                        ],
                    ],
                ],
            ]),
        ]);

        config()->set('webblocks-updates.client.server_url', 'https://updates.example.test');
        app(InstalledVersionStore::class)->persist('0.2.0');

        $result = app(UpdateServerClient::class)->check();

        $this->assertSame('up_to_date', $result->state);
    }

    #[Test]
    public function unreachable_server_case_is_handled(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        config()->set('webblocks-updates.client.server_url', 'https://updates.example.test');

        $result = app(UpdateServerClient::class)->check();

        $this->assertSame('server_unreachable', $result->state);
    }

    #[Test]
    public function malformed_json_case_is_handled(): void
    {
        Http::fake([
            '*' => Http::response('not json', 200, ['Content-Type' => 'application/json']),
        ]);

        config()->set('webblocks-updates.client.server_url', 'https://updates.example.test');

        $result = app(UpdateServerClient::class)->check();

        $this->assertSame('invalid_response', $result->state);
    }

    #[Test]
    public function incompatible_release_case_is_parsed(): void
    {
        Http::fake([
            '*' => Http::response([
                'api_version' => '1',
                'status' => 'ok',
                'data' => [
                    'product' => 'webblocks-cms',
                    'channel' => 'stable',
                    'installed_version' => '0.1.0',
                    'latest_version' => '0.2.0',
                    'update_available' => true,
                    'compatibility' => ['status' => 'incompatible', 'reasons' => ['PHP version does not meet the minimum requirement.']],
                    'release' => [
                        'version' => '0.2.0',
                        'name' => 'WebBlocks CMS 0.2.0',
                        'description' => null,
                        'changelog' => null,
                        'download_url' => 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip',
                        'checksum_sha256' => null,
                        'published_at' => '2026-04-19T10:00:00Z',
                        'is_critical' => false,
                        'is_security' => false,
                        'requirements' => [
                            'min_php_version' => '9.0.0',
                            'min_laravel_version' => null,
                            'supported_from_version' => '0.1.0',
                            'supported_until_version' => null,
                        ],
                    ],
                ],
            ]),
        ]);

        config()->set('webblocks-updates.client.server_url', 'https://updates.example.test');

        $result = app(UpdateServerClient::class)->check();

        $this->assertSame('incompatible', $result->state);
    }
}
