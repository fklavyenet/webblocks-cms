<?php

namespace Tests\Unit\System\Updates;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\System\Updates\UpdateServerClient;

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
    app(InstalledVersionStore::class)->persist('0.1.0');

    $result = app(UpdateServerClient::class)->check();

    $this->assertSame('update_available', $result->state);
    $this->assertTrue($result->updateAvailable);
    $this->assertSame('0.2.0', $result->latestVersion);
    $this->assertSame('https://updates.example.test/downloads/webblocks-cms-0.2.0.zip', $result->release['download_url']);
  }

  #[Test]
  public function structured_release_metadata_is_normalized_for_rendering(): void
  {
    Http::fake([
      '*' => Http::response([
        'status' => 'ok',
        'data' => [
          'product' => 'webblocks-cms',
          'channel' => 'stable',
          'version' => '0.2.0',
          'published_at' => '2026-04-19T10:00:00Z',
          'title' => 'Release details for operators',
          'summary' => 'Review these notes before updating.',
          'highlights' => ['Clear release summaries', 'Grouped visible changes'],
          'fixes' => "- Fix update note rendering\n- Keep technical values collapsed",
          'operator_notes' => ['Download the pre-update backup if needed.'],
          'artifact_url' => 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip',
          'checksum_sha256' => str_repeat('a', 64),
        ],
      ]),
    ]);

    config()->set('webblocks-updates.server_url', 'https://updates.example.test');
    app(InstalledVersionStore::class)->persist('0.1.0');

    $result = app(UpdateServerClient::class)->check();

    $this->assertSame('update_available', $result->state);
    $this->assertSame('Release details for operators', $result->release['name']);
    $this->assertSame('Review these notes before updating.', $result->release['release_details']['summary']);
    $this->assertTrue($result->release['release_details']['has_notes']);
    $this->assertSame('Highlights', $result->release['release_details']['groups'][0]['label']);
    $this->assertSame(['Clear release summaries', 'Grouped visible changes'], $result->release['release_details']['groups'][0]['items']);
    $this->assertSame(['Fix update note rendering', 'Keep technical values collapsed'], $result->release['release_details']['groups'][1]['items']);
    $this->assertSame('Operator notes', $result->release['release_details']['groups'][2]['label']);
  }

  #[Test]
  public function nested_meta_release_details_are_normalized_for_rendering(): void
  {
    Http::fake([
      '*' => Http::response([
        'status' => 'ok',
        'data' => [
          'product' => 'webblocks-cms',
          'channel' => 'stable',
          'version' => '0.2.0',
          'published_at' => '2026-04-19T10:00:00Z',
          'meta' => [
            'release_details' => [
              'title' => 'Nested release metadata',
              'summary' => 'The publisher returned details under meta.',
              'highlights' => ['Nested highlights are visible.'],
            ],
          ],
          'artifact_url' => 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip',
          'checksum_sha256' => str_repeat('a', 64),
        ],
      ]),
    ]);

    config()->set('webblocks-updates.server_url', 'https://updates.example.test');
    app(InstalledVersionStore::class)->persist('0.1.0');

    $result = app(UpdateServerClient::class)->check();

    $this->assertSame('Nested release metadata', $result->release['release_details']['title']);
    $this->assertSame('The publisher returned details under meta.', $result->release['release_details']['summary']);
    $this->assertSame(['Nested highlights are visible.'], $result->release['release_details']['groups'][0]['items']);
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
    app(InstalledVersionStore::class)->persist('0.1.0');

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
    app(InstalledVersionStore::class)->persist('0.1.0');

    $result = app(UpdateServerClient::class)->check();

    $this->assertSame('incompatible', $result->state);
  }

  #[Test]
  public function legacy_0_1_8_install_sees_0_2_0_as_a_compatible_update_when_minimum_client_version_is_0_1_8(): void
  {
    Http::fake([
      '*' => Http::response([
        'status' => 'ok',
        'data' => [
          'product' => 'webblocks-cms',
          'channel' => 'stable',
          'version' => '0.2.0',
          'published_at' => '2026-04-21T10:00:00Z',
          'release_notes' => 'Multisite and multilingual upgrade path.',
          'artifact_url' => 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip',
          'checksum_sha256' => str_repeat('b', 64),
          'source_reference' => 'v0.2.0',
          'minimum_client_version' => '0.1.8',
        ],
      ]),
    ]);

    config()->set('webblocks-updates.server_url', 'https://updates.example.test');
    app(InstalledVersionStore::class)->persist('0.1.8');

    $result = app(UpdateServerClient::class)->check();

    $this->assertSame('update_available', $result->state);
    $this->assertTrue($result->updateAvailable);
    $this->assertSame('compatible', $result->compatibility['status']);
    $this->assertSame('0.1.8', $result->release['requirements']['supported_from_version']);
  }
}
