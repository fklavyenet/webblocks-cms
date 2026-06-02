<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;
use WebBlocks\Cms\Support\System\Updates\Publishing\UpdatePublisher;

final class WebBlocksUpdatePublisherCommandTest extends TestCase
{
  public function test_publish_command_reports_missing_token_without_network_publish(): void
  {
    [$artifact, $payload] = $this->preparedPublisherFiles('1.32.90');

    config()->set('webblocks-updates.publisher.token', null);

    $this->artisan('webblocks:publish-update', [
      '--artifact' => $artifact,
      '--payload' => $payload,
    ])
      ->expectsOutputToContain('Token configured')
      ->expectsOutputToContain('WEBBLOCKS_PUBLISHER_TOKEN configured')
      ->expectsOutputToContain('Update publisher token is not configured. Artifact was generated but not published.')
      ->assertFailed();

    Http::assertNothingSent();
  }

  public function test_dry_run_validates_artifact_checksum_and_metadata_without_publish(): void
  {
    [$artifact, $payload] = $this->preparedPublisherFiles('1.32.90');

    config()->set('webblocks-updates.publisher.token', 'secret-test-token');
    config()->set('webblocks-updates.publisher.url', 'https://updates.webblocksui.com/api/updates/publish');

    $this->artisan('webblocks:publish-update', [
      '--artifact' => $artifact,
      '--payload' => $payload,
      '--dry-run' => true,
    ])
      ->expectsOutputToContain('Dry-run passed.')
      ->expectsOutputToContain('WEBBLOCKS_PUBLISHER_TOKEN configured')
      ->expectsOutputToContain('Dry-run did not upload or publish anything.')
      ->doesntExpectOutputToContain('secret-test-token')
      ->assertSuccessful();

    Http::assertNothingSent();
  }

  public function test_publish_sends_publisher_payload_and_verifies_latest_metadata(): void
  {
    [$artifact, $payload, $checksum] = $this->preparedPublisherFiles('1.32.90');

    config()->set('webblocks-updates.publisher.token', 'secret-test-token');
    config()->set('webblocks-updates.publisher.url', 'https://updates.webblocksui.com/api/updates/publish');

    Http::fake([
      'https://updates.webblocksui.com/api/updates/publish' => Http::response([
        'data' => [
          'product' => 'webblocks-cms',
          'version' => '1.32.90',
          'channel' => 'stable',
          'checksum_sha256' => $checksum,
          'artifact_url' => 'https://updates.webblocksui.com/downloads/webblocks-cms-1.32.90.zip',
        ],
      ]),
      'https://updates.webblocksui.com/api/updates/latest*' => Http::response([
        'data' => [
          'product' => 'webblocks-cms',
          'version' => '1.32.90',
          'channel' => 'stable',
          'checksum_sha256' => $checksum,
          'artifact_url' => 'https://updates.webblocksui.com/downloads/webblocks-cms-1.32.90.zip',
        ],
      ]),
    ]);

    $this->artisan('webblocks:publish-update', [
      '--artifact' => $artifact,
      '--payload' => $payload,
    ])
      ->expectsOutputToContain('Update publisher accepted the artifact')
      ->assertSuccessful();

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
      && $request->url() === 'https://updates.webblocksui.com/api/updates/publish'
      && $request->hasHeader('Authorization', 'Bearer secret-test-token')
      && $request->hasFile('package', null, basename($artifact))
      && str_contains($request->body(), 'name="product"')
      && str_contains($request->body(), 'webblocks-cms')
      && str_contains($request->body(), 'name="channel"')
      && str_contains($request->body(), 'stable')
      && str_contains($request->body(), 'name="version"')
      && str_contains($request->body(), '1.32.90')
      && str_contains($request->body(), $checksum)
      && str_contains($request->body(), 'minimum_client_version')
      && str_contains($request->body(), 'source_reference')
      && str_contains($request->body(), 'operator_notes')
      && ! str_contains($request->body(), 'github.com'));

    Http::assertSent(fn ($request): bool => $request->method() === 'GET'
      && str_starts_with($request->url(), 'https://updates.webblocksui.com/api/updates/latest')
      && $request['product'] === 'webblocks-cms'
      && $request['channel'] === 'stable');
  }

  public function test_publish_detects_canonical_project_env_token_when_config_is_cached(): void
  {
    [$artifact, $payload, $checksum] = $this->preparedPublisherFiles('1.32.90');
    $envPath = $this->publisherEnvironmentFile([
      'WEBBLOCKS_PUBLISHER_URL' => 'https://updates.webblocksui.com/api/updates/publish',
      'WEBBLOCKS_PUBLISHER_TOKEN' => 'webblocks-env-token',
      'WEBBLOCKS_PUBLISHER_PRODUCT' => 'webblocks-cms',
      'WEBBLOCKS_PUBLISHER_CHANNEL' => 'stable',
    ]);

    app()->instance('config_loaded_from_cache', true);
    app()->instance('webblocks.publisher.env_path', $envPath);
    config()->set('webblocks-updates.publisher.token', null);
    config()->set('webblocks-updates.publisher.url', 'https://stale.example.invalid/api/updates/publish');

    Http::fake([
      'https://updates.webblocksui.com/api/updates/publish' => Http::response(['data' => ['version' => '1.32.90']]),
      'https://updates.webblocksui.com/api/updates/latest*' => Http::response([
        'data' => [
          'product' => 'webblocks-cms',
          'version' => '1.32.90',
          'channel' => 'stable',
          'checksum_sha256' => $checksum,
          'artifact_url' => 'https://updates.webblocksui.com/downloads/webblocks-cms-1.32.90.zip',
        ],
      ]),
    ]);

    $this->artisan('webblocks:publish-update', [
      '--artifact' => $artifact,
      '--payload' => $payload,
    ])
      ->expectsOutputToContain('WEBBLOCKS_PUBLISHER_URL configured')
      ->expectsOutputToContain('WEBBLOCKS_PUBLISHER_TOKEN configured')
      ->expectsOutputToContain('WEBBLOCKS_PUBLISHER_PRODUCT configured')
      ->expectsOutputToContain('WEBBLOCKS_PUBLISHER_CHANNEL configured')
      ->expectsOutputToContain('Update publisher accepted the artifact')
      ->doesntExpectOutputToContain('webblocks-env-token')
      ->assertSuccessful();

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
      && $request->url() === 'https://updates.webblocksui.com/api/updates/publish'
      && $request->hasHeader('Authorization', 'Bearer webblocks-env-token'));
  }

  public function test_publish_accepts_canonical_configured_token_without_leaking_it(): void
  {
    [$artifact, $payload, $checksum] = $this->preparedPublisherFiles('1.32.90');

    config()->set('webblocks-updates.publisher.token', 'webblocks-env-token');
    config()->set('webblocks-updates.publisher.url', 'https://updates.webblocksui.com/api/updates/publish');

    Http::fake([
      'https://updates.webblocksui.com/api/updates/publish' => Http::response(['data' => ['version' => '1.32.90']]),
      'https://updates.webblocksui.com/api/updates/latest*' => Http::response([
        'data' => [
          'product' => 'webblocks-cms',
          'version' => '1.32.90',
          'channel' => 'stable',
          'checksum_sha256' => $checksum,
          'artifact_url' => 'https://updates.webblocksui.com/downloads/webblocks-cms-1.32.90.zip',
        ],
      ]),
    ]);

    $this->artisan('webblocks:publish-update', [
      '--artifact' => $artifact,
      '--payload' => $payload,
    ])
      ->expectsOutputToContain('Token configured')
      ->expectsOutputToContain('WEBBLOCKS_PUBLISHER_TOKEN configured')
      ->expectsOutputToContain('Update publisher accepted the artifact')
      ->doesntExpectOutputToContain('webblocks-env-token')
      ->assertSuccessful();

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
      && $request->url() === 'https://updates.webblocksui.com/api/updates/publish'
      && $request->hasHeader('Authorization', 'Bearer webblocks-env-token'));
  }

  public function test_publish_does_not_accept_legacy_token_environment_aliases(): void
  {
    [$artifact, $payload] = $this->preparedPublisherFiles('1.32.90');

    putenv('WEBBLOCKS_PUBLISH_TOKEN=legacy-webblocks-env-token');
    putenv('WEBBLOCKS_UPDATE_PUBLISHER_TOKEN=legacy-update-env-token');
    $_ENV['WEBBLOCKS_PUBLISH_TOKEN'] = 'legacy-webblocks-env-token';
    $_ENV['WEBBLOCKS_UPDATE_PUBLISHER_TOKEN'] = 'legacy-update-env-token';
    $_SERVER['WEBBLOCKS_PUBLISH_TOKEN'] = 'legacy-webblocks-env-token';
    $_SERVER['WEBBLOCKS_UPDATE_PUBLISHER_TOKEN'] = 'legacy-update-env-token';
    config()->set('webblocks-updates.publisher.token', null);
    config()->set('webblocks-updates.publisher.url', 'https://updates.webblocksui.com/api/updates/publish');

    $this->artisan('webblocks:publish-update', [
      '--artifact' => $artifact,
      '--payload' => $payload,
    ])
      ->expectsOutputToContain('Token configured')
      ->expectsOutputToContain('WEBBLOCKS_PUBLISHER_TOKEN configured')
      ->expectsOutputToContain('Update publisher token is not configured. Artifact was generated but not published.')
      ->assertFailed();

    Http::assertNothingSent();
    putenv('WEBBLOCKS_PUBLISH_TOKEN');
    putenv('WEBBLOCKS_UPDATE_PUBLISHER_TOKEN');
    unset(
      $_ENV['WEBBLOCKS_PUBLISH_TOKEN'],
      $_ENV['WEBBLOCKS_UPDATE_PUBLISHER_TOKEN'],
      $_SERVER['WEBBLOCKS_PUBLISH_TOKEN'],
      $_SERVER['WEBBLOCKS_UPDATE_PUBLISHER_TOKEN'],
    );
  }

  public function test_publish_reports_unauthorized_response_without_leaking_token(): void
  {
    [$artifact, $payload] = $this->preparedPublisherFiles('1.32.90');

    config()->set('webblocks-updates.publisher.token', 'secret-test-token');

    Http::fake([
      'https://updates.webblocksui.com/api/updates/publish' => Http::response([
        'service' => 'WebBlocks Publisher',
        'status' => 'error',
        'message' => 'Unauthorized publish request. Bearer secret-test-token was rejected.',
        'data' => [],
      ], 401),
    ]);

    try {
      app(UpdatePublisher::class)->publish([
        'artifact' => $artifact,
        'payload' => $payload,
      ]);

      $this->fail('Expected unauthorized publisher response to fail.');
    } catch (RuntimeException $exception) {
      $message = $exception->getMessage();

      $this->assertStringContainsString('Update publisher request failed with HTTP 401.', $message);
      $this->assertStringContainsString('Bearer publish token', $message);
      $this->assertStringContainsString('product [webblocks-cms]', $message);
      $this->assertStringContainsString('channel [stable]', $message);
      $this->assertStringContainsString('Bearer [redacted] was rejected.', $message);
      $this->assertStringNotContainsString('secret-test-token', $message);
    }
  }

  public function test_latest_verification_must_match_published_metadata(): void
  {
    [$artifact, $payload] = $this->preparedPublisherFiles('1.32.90');

    config()->set('webblocks-updates.publisher.token', 'secret-test-token');

    Http::fake([
      'https://updates.webblocksui.com/api/updates/publish' => Http::response(['data' => ['version' => '1.32.90']]),
      'https://updates.webblocksui.com/api/updates/latest*' => Http::response(['data' => ['version' => '1.32.89']]),
    ]);

    $this->artisan('webblocks:publish-update', [
      '--artifact' => $artifact,
      '--payload' => $payload,
    ])
      ->expectsOutputToContain('Update publisher latest verification failed.')
      ->assertFailed();
  }

  public function test_checksum_mismatch_stops_publish(): void
  {
    [$artifact, $payload] = $this->preparedPublisherFiles('1.32.90', checksum: str_repeat('a', 64));

    config()->set('webblocks-updates.publisher.token', 'secret-test-token');

    $this->artisan('webblocks:publish-update', [
      '--artifact' => $artifact,
      '--payload' => $payload,
    ])
      ->expectsOutputToContain('Artifact checksum mismatch. The artifact was not published.')
      ->assertFailed();

    Http::assertNothingSent();
  }

  public function test_release_publish_flow_has_no_github_release_or_live_deploy_operations(): void
  {
    $publishCommand = file_get_contents(base_path('packages/webblocks-cms/src/Console/PublishUpdateCommand.php'));
    $publisher = file_get_contents(base_path('packages/webblocks-cms/src/Support/System/Updates/Publishing/UpdatePublisher.php'));
    $prepareScript = file_get_contents(base_path('scripts/release/prepare.sh'));
    $publishScript = file_get_contents(base_path('scripts/release/publish-update.sh'));
    $composer = file_get_contents(base_path('composer.json'));
    $surface = implode("\n", [$publishCommand, $publisher, $prepareScript, $publishScript]);

    foreach ([
      'gh release',
      'github releases',
      'releases/download',
      'api.github.com',
      'rsync',
      'git pull',
      'composer install',
      'artisan migrate',
      'systemctl',
      'php-fpm',
      'nginx',
      'npm install',
      'npm run',
      'tailwind',
      'vite',
      '.github/workflows',
      'webhook',
    ] as $forbidden) {
      $this->assertStringNotContainsString($forbidden, strtolower($surface));
    }

    $this->assertStringContainsString('webblocks:publish-update', $publishScript);
    $this->assertStringContainsString('release:publish-update', $composer);
    $this->assertStringContainsString('scripts/release/publish-update.sh', $composer);
    $this->assertStringContainsString('/api/updates/publish', $publisher);
    $this->assertStringContainsString('/api/updates/latest', $publisher);
  }

  private function preparedPublisherFiles(string $version, ?string $checksum = null): array
  {
    $directory = storage_path('app/webblocks-release-tests/'.uniqid('publisher-', true));
    File::ensureDirectoryExists($directory);

    $artifact = $directory.'/webblocks-cms-'.$version.'.zip';
    file_put_contents($artifact, 'zip bytes for '.$version);

    $checksum ??= hash_file('sha256', $artifact);
    $payload = $directory.'/webblocks-cms-'.$version.'-update-server-payload.json';

    file_put_contents($payload, json_encode([
      'product' => 'webblocks-cms',
      'channel' => 'stable',
      'version' => $version,
      'minimum_client_version' => '1.32.18',
      'source_reference' => 'v'.$version,
      'artifact_filename' => basename($artifact),
      'artifact_path' => $artifact,
      'checksum_sha256' => $checksum,
      'release_notes' => 'Native update publisher test release.',
      'details' => [
        'title' => 'WebBlocks CMS '.$version,
        'summary' => 'Native update publisher test release.',
        'highlights' => ['Publisher upload'],
        'fixes' => ['Checksum guard'],
        'operator_notes' => ['Apply only from System Updates.'],
        'technical_notes' => ['SHA-256 verified before upload.'],
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return [$artifact, $payload, $checksum];
  }

  private function publisherEnvironmentFile(array $values): string
  {
    $directory = storage_path('app/webblocks-release-tests/'.uniqid('publisher-env-', true));
    File::ensureDirectoryExists($directory);

    $path = $directory.'/.env';
    $lines = [];

    foreach ($values as $key => $value) {
      $lines[] = $key.'="'.$value.'"';
    }

    file_put_contents($path, implode("\n", $lines)."\n");

    return $path;
  }
}
