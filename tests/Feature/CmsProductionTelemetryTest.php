<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Mockery;
use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\System\Updates\InstallationTelemetry;
use WebBlocks\Cms\Support\System\Updates\UpdateServerClient;
use WebBlocks\Cms\Support\Updates\Client\Contracts\TelemetryProvider;
use WebBlocks\Cms\Support\Updates\Client\Support\Version\VersionResolver;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateServerClient as EmbeddedClient;
use WebBlocks\Cms\Tests\TestCase;

class CmsProductionTelemetryTest extends TestCase
{
  private string $workspace;

  protected function setUp(): void
  {
    parent::setUp();
    $this->workspace = 'app/cms-production-telemetry-test-'.bin2hex(random_bytes(8));
    $this->app['env'] = 'production';
    config([
      'app.key' => 'base64:'.base64_encode(random_bytes(32)),
      'webblocks-updates.enabled' => true,
      'webblocks-updates.telemetry.enabled' => true,
      'publisher-client.enabled' => true,
      'publisher-client.telemetry.enabled' => true,
      'publisher-client.apply.workspace_root' => $this->workspace,
    ]);
  }

  protected function tearDown(): void
  {
    File::deleteDirectory(storage_path($this->workspace));
    parent::tearDown();
  }

  public function test_panel_and_embedded_checks_report_the_same_production_identity_only_in_headers(): void
  {
    $redirects = [];
    Http::fake(function ($request, array $options) use (&$redirects) {
      $redirects[] = $options['allow_redirects'] ?? null;

      return Http::response(['status' => 'ok', 'data' => ['product' => 'webblocks-cms', 'channel' => 'stable', 'version' => '1.0.0', 'artifact_url' => 'https://publisher.example.test/cms.zip']]);
    });
    $this->panelClient()->check();
    $this->embeddedClient()->check();
    Http::assertSentCount(2);
    $ids = [];
    foreach (Http::recorded() as [$request]) {
      $this->assertStringNotContainsString('installation_id', $request->url());
      $this->assertStringNotContainsString('telemetry_schema_version', $request->url());
      $data = json_decode($request->header('X-WebBlocks-Telemetry')[0], true, flags: JSON_THROW_ON_ERROR);
      $this->assertSame('2', $data['telemetry_schema_version']);
      $this->assertSame('production', $data['environment']);
      $this->assertSame('webblocks-cms', $data['product_key']);
      $this->assertSame('1.0.0', $data['installed_version']);
      $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $data['installation_id']);
      $this->assertStringNotContainsString(config('app.key'), json_encode($data));
      $ids[] = $data['installation_id'];
    }
    $this->assertSame($ids[0], $ids[1]);
    $this->assertSame([false, false], $redirects);
  }

  public function test_cms_and_shared_client_opt_out_both_suppress_telemetry_on_both_update_paths(): void
  {
    $this->assertInstanceOf(InstallationTelemetry::class, app(TelemetryProvider::class));
    foreach (['webblocks-updates.telemetry.enabled', 'publisher-client.telemetry.enabled'] as $key) {
      config([$key => false]);
      Http::fake(['*' => Http::response([], 200)]);
      $this->panelClient()->check();
      $this->embeddedClient()->check();
      Http::assertSentCount(2);
      foreach (Http::recorded() as [$request]) {
        $this->assertFalse($request->hasHeader('X-WebBlocks-Telemetry'));
        $this->assertStringNotContainsString('installation_id', $request->url());
      }
      config([$key => true]);
    }
    $this->assertDirectoryDoesNotExist(storage_path($this->workspace));
  }

  public function test_non_production_environments_never_generate_an_installation_identity(): void
  {
    foreach (['local', 'testing', 'test', 'staging'] as $environment) {
      $this->app['env'] = $environment;
      $this->assertNull(app(InstallationTelemetry::class)->installationId());
      $this->assertSame([], app(TelemetryProvider::class)->updateCheckPayload('webblocks-cms', '1.0.0', 'stable'));
    }
    $this->assertDirectoryDoesNotExist(storage_path($this->workspace));
  }

  public function test_production_identity_survives_legacy_id_changes_and_storage_loss(): void
  {
    $telemetry = app(InstallationTelemetry::class);
    $id = $telemetry->installationId();
    File::put(storage_path($this->workspace.'/installation-id'), 'replacement-legacy-uuid');
    $this->assertSame($id, $telemetry->installationId());
    File::deleteDirectory(storage_path($this->workspace));
    $this->assertSame($id, $telemetry->installationId());
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    $this->assertSame($id, $telemetry->installationId());
  }

  public function test_embedded_http_transport_suppresses_identity_and_redirects_are_not_followed(): void
  {
    config(['publisher-client.server_url' => 'http://publisher.example.test']);
    Http::fake(['*' => Http::response([], 302, ['Location' => 'https://other.example.test'])]);
    $this->embeddedClient()->check();
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => ! $request->hasHeader('X-WebBlocks-Telemetry') && ! str_contains($request->url(), 'installation_id'));
  }

  private function panelClient(): UpdateServerClient
  {
    $store = Mockery::mock(InstalledVersionStore::class);
    $store->shouldReceive('currentVersion')->andReturn('1.0.0');

    return new UpdateServerClient($store, app(InstallationTelemetry::class));
  }

  private function embeddedClient(): EmbeddedClient
  {
    $versions = Mockery::mock(VersionResolver::class);
    $versions->shouldReceive('current')->andReturn('1.0.0');

    return new EmbeddedClient($versions, app(TelemetryProvider::class));
  }
}
