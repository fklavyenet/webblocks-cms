<?php

namespace Tests\Feature\Api\Updates;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublishReleaseApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function publish_endpoint_requires_valid_token(): void
    {
        config()->set('webblocks-release.publish.token', 'secret-token');

        $response = $this->postJson('/api/updates/publish', []);

        $response->assertStatus(401)->assertJsonPath('error.code', 'unauthorized');
    }

    #[Test]
    public function publish_endpoint_stores_package_and_release_metadata(): void
    {
        Storage::fake('local');
        config()->set('webblocks-release.publish.token', 'secret-token');

        $response = $this->withToken('secret-token')->post('/api/updates/publish', [
            'product' => 'webblocks-cms',
            'version' => '0.2.0',
            'channel' => 'stable',
            'name' => 'WebBlocks CMS 0.2.0',
            'description' => 'Stability release',
            'changelog' => 'Compact changelog text here.',
            'checksum_sha256' => hash('sha256', 'test-package'),
            'package' => UploadedFile::fake()->create('webblocks-cms-0.2.0.zip', 64, 'application/zip'),
        ]);

        $response->assertOk()->assertJsonPath('data.release.version', '0.2.0');
        $this->assertDatabaseHas('system_releases', [
            'product' => 'webblocks-cms',
            'channel' => 'stable',
            'version' => '0.2.0',
        ]);
    }
}
