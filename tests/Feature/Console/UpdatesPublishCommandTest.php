<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdatesPublishCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function command_can_publish_successfully(): void
    {
        Http::fake([
            'https://updates.example.test/api/updates/publish' => Http::response([
                'status' => 'ok',
                'data' => [
                    'release' => [
                        'download_url' => 'https://updates.example.test/releases/webblocks-cms-0.2.1.zip',
                    ],
                ],
            ]),
        ]);

        config()->set('webblocks-updates.publish.server_url', 'https://updates.example.test');
        config()->set('webblocks-updates.publish.token', 'secret-token');

        $this->artisan('updates:publish', [
            'version' => '0.2.1',
            '--notes' => 'Initial live publish test',
            '--tag' => 'v0.2.1',
        ])->assertExitCode(0);
    }

    #[Test]
    public function dry_run_does_not_send_http_request(): void
    {
        Http::fake();

        config()->set('webblocks-updates.publish.server_url', 'https://updates.example.test');
        config()->set('webblocks-updates.publish.token', 'secret-token');

        $this->artisan('updates:publish', [
            'version' => '0.2.1',
            '--dry-run' => true,
        ])->assertExitCode(0);

        Http::assertNothingSent();
    }
}
