<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use WebBlocks\Cms\Support\Plugins\Catalog\PluginCatalogClient;
use WebBlocks\Cms\Support\Theme\ThemeCatalog;
use WebBlocks\Cms\Tests\TestCase;

final class ExtensionsCatalogTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[DataProvider('catalogAddresses')]
  public function test_clients_use_the_configured_catalogs(?string $plugin, ?string $extensions, string $pluginUrl, ?string $themeUrl): void
  {
    config([
      'webblocks-plugins.catalog.base_url' => $plugin,
      'webblocks-plugins.catalog.extensions_base_url' => $extensions,
    ]);

    Http::preventStrayRequests();
    $responses = [
      $pluginUrl.'/api/plugins/example/latest?*' => Http::response([
        'data' => ['release' => ['version' => '1.2.3']],
      ]),
    ];
    if ($themeUrl !== null) {
      $responses[$themeUrl.'/api/themes?*'] = Http::response(['data' => []]);
    }
    Http::fake($responses);

    $releases = app(PluginCatalogClient::class)->installedReleases(['example']);
    $this->assertSame('1.2.3', $releases['example']->version ?? null);

    $themes = app(ThemeCatalog::class);
    $this->assertSame($themeUrl !== null, $themes->enabled());
    if ($themeUrl !== null) {
      $this->assertSame(['themes' => [], 'hasMore' => false], $themes->browse(1));
    }
    Http::assertSentCount($themeUrl === null ? 1 : 2);
  }

  public static function catalogAddresses(): array
  {
    $default = 'https://extensions.webblocksui.com';
    $legacy = 'https://plugins.webblocksui.com';
    $custom = 'https://catalog.example.test';
    $pluginOnly = 'http://catalog.example.test/plugins';
    $invalidTheme = "$custom/path";

    return [
      'package default' => [null, null, $default, $default],
      'legacy override' => [$legacy, null, $legacy, $legacy],
      'Extensions override' => [null, " $custom/ ", $custom, $custom],
      'separate overrides' => [$legacy, $custom, $legacy, $custom],
      'blank legacy override' => [' ', $custom, $custom, $custom],
      'explicit theme opt-out' => [null, '', $default, null],
      'legacy plugin-only URL' => [$pluginOnly, null, $pluginOnly, null],
      'invalid theme override' => [$legacy, $invalidTheme, $legacy, null],
    ];
  }
}
