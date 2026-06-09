<?php

namespace Tests\Unit\System\Updates;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\System\Updates\UpdateServerClient;
use WebBlocks\Cms\Support\Updates\ReleaseDefaults;
use WebBlocks\Cms\Support\WebBlocks;

class UpdateServerClientTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    $targetPath = storage_path('app/testing-package-update-client');
    File::ensureDirectoryExists($targetPath);
    config()->set('webblocks-updates.installer.target_path', $targetPath);
  }

  #[Test]
  public function update_check_uses_release_defaults_when_no_update_env_override_exists(): void
  {
    Http::fake([
      ReleaseDefaults::latestUrl().'*' => Http::response([
        'status' => 'ok',
        'data' => [
          'product' => ReleaseDefaults::PRODUCT_KEY,
          'channel' => ReleaseDefaults::CHANNEL,
          'version' => WebBlocks::version(),
          'published_at' => '2026-06-09T10:00:00Z',
          'artifact_url' => ReleaseDefaults::SERVER_URL.'/downloads/webblocks-cms-current.zip',
          'checksum_sha256' => str_repeat('a', 64),
        ],
      ]),
    ]);

    app(InstalledVersionStore::class)->persist('0.1.0');

    $result = app(UpdateServerClient::class)->check();

    $this->assertSame('up_to_date', $result->state);
    $this->assertSame(ReleaseDefaults::SERVER_URL, $result->serverUrl);
    $this->assertSame(ReleaseDefaults::PRODUCT_KEY, $result->product);
    $this->assertSame(ReleaseDefaults::CHANNEL, $result->channel);

    Http::assertSent(fn ($request): bool => $request->method() === 'GET'
      && str_starts_with($request->url(), ReleaseDefaults::latestUrl())
      && $request['product'] === ReleaseDefaults::PRODUCT_KEY
      && $request['channel'] === ReleaseDefaults::CHANNEL);
  }

  #[Test]
  public function successful_update_available_case_is_parsed(): void
  {
    Http::fake([
      ReleaseDefaults::latestUrl().'*' => Http::response([
        'status' => 'ok',
        'data' => [
          'product' => ReleaseDefaults::PRODUCT_KEY,
          'channel' => ReleaseDefaults::CHANNEL,
          'version' => '99.0.0',
          'published_at' => '2026-04-19T10:00:00Z',
          'release_notes' => 'Stability and admin improvements.',
          'artifact_url' => ReleaseDefaults::SERVER_URL.'/downloads/webblocks-cms-99.0.0.zip',
          'checksum_sha256' => str_repeat('a', 64),
          'source_type' => 'github',
          'source_reference' => 'v99.0.0',
          'minimum_client_version' => '0.1.0',
        ],
      ]),
    ]);

    app(InstalledVersionStore::class)->persist('0.1.0');

    $result = app(UpdateServerClient::class)->check();

    $this->assertSame('update_available', $result->state);
    $this->assertTrue($result->updateAvailable);
    $this->assertSame(WebBlocks::version(), $result->installedVersion);
    $this->assertSame('99.0.0', $result->latestVersion);
    $this->assertSame(ReleaseDefaults::SERVER_URL.'/downloads/webblocks-cms-99.0.0.zip', $result->release['download_url']);
  }

  #[Test]
  public function configured_update_identity_values_do_not_override_release_defaults(): void
  {
    Http::fake([
      ReleaseDefaults::latestUrl().'*' => Http::response([
        'status' => 'ok',
        'data' => [
          'product' => ReleaseDefaults::PRODUCT_KEY,
          'channel' => ReleaseDefaults::CHANNEL,
          'version' => WebBlocks::version(),
          'published_at' => '2026-06-09T10:00:00Z',
          'artifact_url' => ReleaseDefaults::SERVER_URL.'/downloads/webblocks-cms-current.zip',
          'checksum_sha256' => str_repeat('a', 64),
        ],
      ]),
    ]);

    config()->set('webblocks-updates.server_url', 'https://updates-override.example.test');
    config()->set('webblocks-updates.channel', 'beta');
    app(InstalledVersionStore::class)->persist('0.1.0');

    $result = app(UpdateServerClient::class)->check();

    $this->assertSame('up_to_date', $result->state);
    $this->assertSame(ReleaseDefaults::SERVER_URL, $result->serverUrl);
    $this->assertSame(ReleaseDefaults::CHANNEL, $result->channel);

    Http::assertSent(fn ($request): bool => $request->method() === 'GET'
      && str_starts_with($request->url(), ReleaseDefaults::latestUrl())
      && $request['product'] === ReleaseDefaults::PRODUCT_KEY
      && $request['channel'] === ReleaseDefaults::CHANNEL);
  }

  #[Test]
  public function release_default_latest_path_is_package_owned(): void
  {
    config()->set('webblocks-updates.latest_path', 'stale/latest/path');

    Http::fake([
      ReleaseDefaults::latestUrl().'*' => Http::response([
        'status' => 'ok',
        'data' => [
          'product' => ReleaseDefaults::PRODUCT_KEY,
          'channel' => ReleaseDefaults::CHANNEL,
          'version' => WebBlocks::version(),
          'published_at' => '2026-06-09T10:00:00Z',
          'artifact_url' => ReleaseDefaults::SERVER_URL.'/downloads/webblocks-cms-current.zip',
          'checksum_sha256' => str_repeat('a', 64),
        ],
      ]),
    ]);

    app(InstalledVersionStore::class)->persist('0.1.0');

    $this->assertSame('up_to_date', app(UpdateServerClient::class)->check()->state);

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), ReleaseDefaults::latestUrl()));
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
          'version' => '99.0.0',
          'published_at' => '2026-04-19T10:00:00Z',
          'title' => 'Release details for operators',
          'summary' => 'Review these notes before updating.',
          'highlights' => ['Clear release summaries', 'Grouped visible changes'],
          'fixes' => "- Fix update note rendering\n- Keep technical values collapsed",
          'operator_notes' => ['Download the pre-update backup if needed.'],
          'artifact_url' => 'https://updates.example.test/downloads/webblocks-cms-99.0.0.zip',
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
          'version' => '99.0.0',
          'published_at' => '2026-04-19T10:00:00Z',
          'meta' => [
            'release_details' => [
              'title' => 'Nested release metadata',
              'summary' => 'The publisher returned details under meta.',
              'highlights' => ['Nested highlights are visible.'],
            ],
          ],
          'artifact_url' => 'https://updates.example.test/downloads/webblocks-cms-99.0.0.zip',
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
          'version' => WebBlocks::version(),
          'published_at' => '2026-04-19T10:00:00Z',
          'release_notes' => null,
          'artifact_url' => 'https://updates.example.test/downloads/webblocks-cms-current.zip',
          'checksum_sha256' => null,
        ],
      ]),
    ]);

    config()->set('webblocks-updates.server_url', 'https://updates.example.test');
    app(InstalledVersionStore::class)->persist('0.1.0');

    $result = app(UpdateServerClient::class)->check();

    $this->assertSame('up_to_date', $result->state);
    $this->assertFalse($result->updateAvailable);
    $this->assertSame(WebBlocks::version(), $result->installedVersion);
  }

  #[Test]
  public function old_stored_installed_version_does_not_force_update_available_when_current_code_is_current(): void
  {
    Http::fake([
      '*' => Http::response([
        'status' => 'ok',
        'data' => [
          'product' => 'webblocks-cms',
          'channel' => 'stable',
          'version' => WebBlocks::version(),
          'published_at' => '2026-05-29T10:00:00Z',
          'release_notes' => 'Already present in the maintenance checkout.',
          'artifact_url' => 'https://updates.example.test/downloads/webblocks-cms-current.zip',
          'checksum_sha256' => str_repeat('a', 64),
        ],
      ]),
    ]);

    config()->set('webblocks-updates.server_url', 'https://updates.example.test');
    app(InstalledVersionStore::class)->persist('0.1.0');

    $result = app(UpdateServerClient::class)->check();

    $this->assertSame('up_to_date', $result->state);
    $this->assertFalse($result->updateAvailable);
    $this->assertSame(WebBlocks::version(), $result->installedVersion);
    $this->assertSame('This install is already on the latest published release.', $result->message);
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
          'version' => '99.0.0',
          'published_at' => '2026-04-19T10:00:00Z',
          'artifact_url' => 'https://updates.example.test/downloads/webblocks-cms-99.0.0.zip',
          'minimum_client_version' => '99.0.0',
        ],
      ]),
    ]);

    config()->set('webblocks-updates.server_url', 'https://updates.example.test');
    app(InstalledVersionStore::class)->persist('0.1.0');

    $result = app(UpdateServerClient::class)->check();

    $this->assertSame('incompatible', $result->state);
  }

  #[Test]
  public function newer_release_is_compatible_when_current_code_satisfies_minimum_client_version(): void
  {
    Http::fake([
      '*' => Http::response([
        'status' => 'ok',
        'data' => [
          'product' => 'webblocks-cms',
          'channel' => 'stable',
          'version' => '99.0.0',
          'published_at' => '2026-04-21T10:00:00Z',
          'release_notes' => 'Multisite and multilingual upgrade path.',
          'artifact_url' => 'https://updates.example.test/downloads/webblocks-cms-99.0.0.zip',
          'checksum_sha256' => str_repeat('b', 64),
          'source_reference' => 'v99.0.0',
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

  protected function tearDown(): void
  {
    File::deleteDirectory(storage_path('app/testing-package-update-client'));

    parent::tearDown();
  }
}
