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
            'https://updates.example.test/api/updates/latest*' => Http::response([
                'status' => 'ok',
                'data' => [
                    'product' => 'webblocks-cms',
                    'channel' => 'stable',
                    'version' => '0.2.0',
                    'published_at' => '2026-04-19T10:00:00Z',
                    'release_notes' => 'Stability and admin improvements.',
                    'artifact_url' => 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip',
                    'checksum_sha256' => str_repeat('a', 64),
                    'source_type' => 'github',
                    'source_reference' => 'v0.2.0',
                    'minimum_client_version' => '0.1.0',
                ],
            ]),
        ]);

        config()->set('webblocks-updates.server_url', 'https://updates.example.test');

        $result = app(UpdateServerClient::class)->check();

        $this->assertSame('update_available', $result->state);
        $this->assertTrue($result->updateAvailable);
        $this->assertSame('0.2.0', $result->latestVersion);
        $this->assertSame('https://updates.example.test/downloads/webblocks-cms-0.2.0.zip', $result->release['download_url']);
    }

    #[Test]
    public function up_to_date_case_is_parsed(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'ok',
                'data' => [
                    'product' => 'webblocks-cms',
                    'channel' => 'stable',
                    'version' => '0.2.0',
                    'published_at' => '2026-04-19T10:00:00Z',
                    'release_notes' => null,
                    'artifact_url' => 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip',
                    'checksum_sha256' => null,
                ],
            ]),
        ]);

        config()->set('webblocks-updates.server_url', 'https://updates.example.test');
        app(InstalledVersionStore::class)->persist('0.2.0');

        $result = app(UpdateServerClient::class)->check();

        $this->assertSame('up_to_date', $result->state);
    }

    #[Test]
    public function unreachable_server_case_is_handled(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        config()->set('webblocks-updates.server_url', 'https://updates.example.test');

        $result = app(UpdateServerClient::class)->check();

        $this->assertSame('server_unreachable', $result->state);
    }

    #[Test]
    public function malformed_json_case_is_handled(): void
    {
        Http::fake([
            '*' => Http::response('not json', 200, ['Content-Type' => 'application/json']),
        ]);

        config()->set('webblocks-updates.server_url', 'https://updates.example.test');

        $result = app(UpdateServerClient::class)->check();

        $this->assertSame('invalid_response', $result->state);
    }

    #[Test]
    public function incompatible_release_case_is_parsed(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'ok',
                'data' => [
                    'product' => 'webblocks-cms',
                    'channel' => 'stable',
                    'version' => '0.2.0',
                    'published_at' => '2026-04-19T10:00:00Z',
                    'artifact_url' => 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip',
                    'minimum_client_version' => '0.2.0',
                ],
            ]),
        ]);

        config()->set('webblocks-updates.server_url', 'https://updates.example.test');

        $result = app(UpdateServerClient::class)->check();

        $this->assertSame('incompatible', $result->state);
    }
}
