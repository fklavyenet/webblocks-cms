<?php

namespace Tests\Feature\Api\Updates;

use App\Models\SystemRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateServerApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function service_root_endpoint_returns_json(): void
    {
        SystemRelease::factory()->create();

        $response = $this->getJson('/api/updates');

        $response->assertOk()
            ->assertJsonPath('api_version', '1')
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.service', 'WebBlocks Update Server')
            ->assertJsonPath('data.products.0.product', 'webblocks-cms');
    }

    #[Test]
    public function latest_release_returns_newest_published_version(): void
    {
        SystemRelease::factory()->create(['version' => '0.1.0', 'version_normalized' => '00000000.00000001.00000000.zzzzzzzz']);
        SystemRelease::factory()->create(['version' => '0.2.0', 'version_normalized' => '00000000.00000002.00000000.zzzzzzzz']);

        $response = $this->getJson('/api/updates/webblocks-cms/latest?channel=stable&installed_version=0.1.0&php_version=8.3.1&laravel_version=13.0.0');

        $response->assertOk()
            ->assertJsonPath('data.latest_version', '0.2.0')
            ->assertJsonPath('data.update_available', true)
            ->assertJsonPath('data.compatibility.status', 'compatible');
    }

    #[Test]
    public function unpublished_release_is_ignored(): void
    {
        SystemRelease::factory()->create(['version' => '0.1.0']);
        SystemRelease::factory()->unpublished()->create(['version' => '0.3.0', 'version_normalized' => '00000000.00000003.00000000.zzzzzzzz']);

        $response = $this->getJson('/api/updates/webblocks-cms/latest?channel=stable');

        $response->assertOk()->assertJsonPath('data.latest_version', '0.1.0');
    }

    #[Test]
    public function product_and_channel_filtering_work(): void
    {
        SystemRelease::factory()->create(['product' => 'webblocks-cms', 'channel' => 'stable', 'version' => '0.2.0']);
        SystemRelease::factory()->create(['product' => 'webblocks-cms', 'channel' => 'beta', 'version' => '0.3.0-beta.1', 'version_normalized' => '00000000.00000003.00000000.beta.1']);

        $response = $this->getJson('/api/updates/webblocks-cms/latest?channel=beta');

        $response->assertOk()->assertJsonPath('data.channel', 'beta')->assertJsonPath('data.latest_version', '0.3.0-beta.1');
    }

    #[Test]
    public function release_by_version_works(): void
    {
        SystemRelease::factory()->create(['version' => '0.2.0']);

        $response = $this->getJson('/api/updates/webblocks-cms/releases/0.2.0');

        $response->assertOk()->assertJsonPath('data.release.version', '0.2.0');
    }

    #[Test]
    public function not_found_cases_return_json_errors(): void
    {
        $response = $this->getJson('/api/updates/webblocks-cms/latest?channel=stable');

        $response->assertNotFound()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('error.code', 'release_not_found');
    }

    #[Test]
    public function validation_errors_return_json_properly(): void
    {
        $response = $this->getJson('/api/updates/webblocks-cms/releases?channel=invalid&limit=999');

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('error.code', 'validation_failed');
    }
}
