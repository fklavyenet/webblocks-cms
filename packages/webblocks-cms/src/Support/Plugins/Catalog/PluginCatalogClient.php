<?php

namespace WebBlocks\Cms\Support\Plugins\Catalog;

use Illuminate\Foundation\Application;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PluginCatalogClient
{
  public function browse(): PluginCatalogResult
  {
    $baseUrl = rtrim((string) config('webblocks-plugins.catalog.base_url', ''), '/');
    $cmsVersion = (string) config('app.version', 'dev');

    if ($baseUrl === '') {
      return new PluginCatalogResult(false, [], $baseUrl, $cmsVersion, 'Configure a Plugin Catalog base URL before browsing.');
    }

    $request = Http::acceptJson()
      ->timeout((int) config('webblocks-plugins.catalog.timeout_seconds', 5))
      ->connectTimeout((int) config('webblocks-plugins.catalog.connect_timeout_seconds', 3))
      ->withHeaders([
        'User-Agent' => 'WebBlocks-CMS-Plugin-Catalog/'.$cmsVersion,
      ]);

    try {
      $response = $request->get($baseUrl.'/api/plugins', [
        'host_product' => 'webblocks-cms',
        'version' => $cmsVersion,
        'cms_version' => $cmsVersion,
        'listed' => '1',
        'visibility' => 'public',
        'include' => 'latest_compatible_release',
      ]);
    } catch (ConnectionException $exception) {
      Log::warning('Plugin Catalog unavailable.', [
        'base_url' => $baseUrl,
        'host_product' => 'webblocks-cms',
        'error' => $exception->getMessage(),
      ]);

      return new PluginCatalogResult(false, [], $baseUrl, $cmsVersion, 'The Plugin Catalog could not be reached within the configured timeout.');
    }

    $payload = $response->json();

    if (! $response->successful() || ! is_array($payload)) {
      Log::warning('Plugin Catalog returned an invalid response.', [
        'base_url' => $baseUrl,
        'host_product' => 'webblocks-cms',
        'status' => $response->status(),
      ]);

      return new PluginCatalogResult(false, [], $baseUrl, $cmsVersion, 'The Plugin Catalog is unavailable or returned an unexpected response.');
    }

    $plugins = $this->pluginsFromPayload($payload, $baseUrl, $cmsVersion);

    return new PluginCatalogResult(true, $plugins, $baseUrl, $cmsVersion);
  }

  public function show(string $handle): PluginCatalogDetailResult
  {
    $baseUrl = rtrim((string) config('webblocks-plugins.catalog.base_url', ''), '/');
    $cmsVersion = (string) config('app.version', 'dev');
    $handle = trim($handle);

    if ($baseUrl === '') {
      return new PluginCatalogDetailResult(false, null, $baseUrl, $cmsVersion, 'Configure a Plugin Catalog base URL before browsing.');
    }

    if ($handle === '') {
      return new PluginCatalogDetailResult(false, null, $baseUrl, $cmsVersion, 'Select a catalog plugin before opening details.');
    }

    $request = Http::acceptJson()
      ->timeout((int) config('webblocks-plugins.catalog.timeout_seconds', 5))
      ->connectTimeout((int) config('webblocks-plugins.catalog.connect_timeout_seconds', 3))
      ->withHeaders([
        'User-Agent' => 'WebBlocks-CMS-Plugin-Catalog/'.$cmsVersion,
      ]);

    try {
      $response = $request->get($baseUrl.'/api/plugins/'.rawurlencode($handle), [
        'host_product' => 'webblocks-cms',
        'version' => $cmsVersion,
        'cms_version' => $cmsVersion,
        'include' => 'latest_compatible_release',
      ]);
    } catch (ConnectionException $exception) {
      Log::warning('Plugin Catalog detail unavailable.', [
        'base_url' => $baseUrl,
        'host_product' => 'webblocks-cms',
        'handle' => $handle,
        'error' => $exception->getMessage(),
      ]);

      return new PluginCatalogDetailResult(false, null, $baseUrl, $cmsVersion, 'The Plugin Catalog detail could not be reached within the configured timeout.');
    }

    $payload = $response->json();

    if ($response->status() === 404) {
      return new PluginCatalogDetailResult(false, null, $baseUrl, $cmsVersion, 'The requested catalog plugin was not found.');
    }

    if (! $response->successful() || ! is_array($payload)) {
      Log::warning('Plugin Catalog detail returned an invalid response.', [
        'base_url' => $baseUrl,
        'host_product' => 'webblocks-cms',
        'handle' => $handle,
        'status' => $response->status(),
      ]);

      return new PluginCatalogDetailResult(false, null, $baseUrl, $cmsVersion, 'The Plugin Catalog detail is unavailable or returned an unexpected response.');
    }

    $pluginPayload = Arr::get($payload, 'data.plugin', Arr::get($payload, 'data', Arr::get($payload, 'plugin')));

    if (! is_array($pluginPayload)) {
      return new PluginCatalogDetailResult(false, null, $baseUrl, $cmsVersion, 'The Plugin Catalog detail returned no plugin metadata.');
    }

    $latestRelease = $this->latestCompatibleRelease($baseUrl, $handle, $cmsVersion);
    $plugin = CatalogPlugin::fromArray($pluginPayload, $latestRelease);

    if ($plugin === null) {
      return new PluginCatalogDetailResult(false, null, $baseUrl, $cmsVersion, 'The Plugin Catalog detail returned incomplete plugin metadata.');
    }

    return new PluginCatalogDetailResult(true, $plugin, $baseUrl, $cmsVersion);
  }

  /**
   * @param  array<string, mixed>  $payload
   * @return array<int, CatalogPlugin>
   */
  private function pluginsFromPayload(array $payload, string $baseUrl, string $cmsVersion): array
  {
    $items = Arr::get($payload, 'data.plugins', Arr::get($payload, 'data', Arr::get($payload, 'plugins', [])));

    if (! is_array($items)) {
      return [];
    }

    $plugins = [];

    foreach ($items as $item) {
      if (! is_array($item)) {
        continue;
      }

      $handle = Arr::get($item, 'handle');
      $latestRelease = is_string($handle) && $handle !== ''
        ? $this->latestCompatibleRelease($baseUrl, $handle, $cmsVersion)
        : null;
      $plugin = CatalogPlugin::fromArray($item, $latestRelease);

      if ($plugin !== null) {
        $plugins[] = $plugin;
      }
    }

    return $plugins;
  }

  private function latestCompatibleRelease(string $baseUrl, string $handle, string $cmsVersion): ?CatalogRelease
  {
    try {
      $response = Http::acceptJson()
        ->timeout((int) config('webblocks-plugins.catalog.timeout_seconds', 5))
        ->connectTimeout((int) config('webblocks-plugins.catalog.connect_timeout_seconds', 3))
        ->withHeaders([
          'User-Agent' => 'WebBlocks-CMS-Plugin-Catalog/'.$cmsVersion,
        ])
        ->get($baseUrl.'/api/plugins/'.rawurlencode($handle).'/latest', [
          'host_product' => 'webblocks-cms',
          'version' => $cmsVersion,
          'cms_version' => $cmsVersion,
          'php_version' => PHP_VERSION,
          'laravel_version' => Application::VERSION,
        ]);
    } catch (ConnectionException $exception) {
      Log::info('Plugin Catalog latest compatible release lookup failed.', [
        'base_url' => $baseUrl,
        'host_product' => 'webblocks-cms',
        'handle' => $handle,
        'error' => $exception->getMessage(),
      ]);

      return null;
    }

    if (! $response->successful()) {
      return null;
    }

    $payload = $response->json();
    $release = is_array($payload) ? Arr::get($payload, 'data.release', Arr::get($payload, 'data', Arr::get($payload, 'release'))) : null;

    return is_array($release) ? CatalogRelease::fromArray($release) : null;
  }
}
