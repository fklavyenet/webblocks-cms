<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemUpdatePublishTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function publish_form_success_creates_flash_and_log_record(): void
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

        $response = $this->actingAs(User::factory()->create())->post(route('admin.system.updates.publish'), [
            'version' => '0.2.1',
            'channel' => 'stable',
            'notes' => 'Initial live publish test',
            'source_url' => 'https://github.com/fklavyenet/webblocks-cms/releases/tag/v0.2.1',
            'tag' => 'v0.2.1',
        ]);

        $response->assertRedirect(route('admin.system.updates.index'));
        $response->assertSessionHas('status', 'Release published to Updates Server.');

        $this->assertDatabaseHas('system_release_publishes', [
            'version' => '0.2.1',
            'channel' => 'stable',
            'status' => 'success',
        ]);
    }

    #[Test]
    public function publish_form_handles_unauthorized_response_with_error_flash(): void
    {
        Http::fake([
            'https://updates.example.test/api/updates/publish' => Http::response([
                'status' => 'error',
                'error' => [
                    'code' => 'unauthorized',
                    'message' => 'Invalid token.',
                ],
            ], 401),
        ]);

        config()->set('webblocks-updates.publish.server_url', 'https://updates.example.test');
        config()->set('webblocks-updates.publish.token', 'invalid-token');

        $response = $this->actingAs(User::factory()->create())->from(route('admin.system.updates.index'))->post(route('admin.system.updates.publish'), [
            'version' => '0.2.1',
            'channel' => 'stable',
        ]);

        $response->assertRedirect(route('admin.system.updates.index'));
        $response->assertSessionHasErrors('release_publish');

        $this->assertDatabaseHas('system_release_publishes', [
            'version' => '0.2.1',
            'status' => 'failed',
        ]);
    }
}
