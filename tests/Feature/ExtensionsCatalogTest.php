<?php

namespace WebBlocks\Cms\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use WebBlocks\Cms\Support\Plugins\Catalog\PluginCatalogClient;
use WebBlocks\Cms\Support\Theme\ThemeCatalog;
use WebBlocks\Cms\Tests\TestCase;

final class ExtensionsCatalogTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

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

  public function test_plugin_catalog_cards_keep_artwork_and_the_detail_action(): void
  {
    $catalog = 'https://catalog.example.test';
    $cover = $catalog.'/storage/catalog-media/example/cover.jpg';
    config(['webblocks-plugins.catalog.base_url' => $catalog]);
    Http::fake([
      $catalog.'/api/plugins?*' => Http::response(['data' => [[
        'handle' => 'example',
        'label' => 'Example plugin',
        'summary' => 'Support site visitors.',
        'artwork' => ['card_url' => $cover, 'alt' => 'Plugin artwork'],
      ]]]),
    ]);
    $this->withoutMiddleware();
    view()->share('errors', new ViewErrorBag);

    $this->get(route('admin.plugins.catalog.index'))->assertOk()
      ->assertSee('wb-cms-catalog-grid', false)
      ->assertSeeInOrder(['src="'.$cover.'"', 'Example plugin', 'Support site visitors.', 'View plugin'], false)
      ->assertSee('alt="Plugin artwork"', false)
      ->assertSee('href="'.route('admin.plugins.catalog.show', 'example').'"', false);
  }

  public function test_plugin_details_show_installation_information_beside_the_catalog_slider(): void
  {
    $catalog = 'https://catalog.example.test';
    $screenshot = $catalog.'/storage/catalog-media/example/workspace.webp';
    $cover = $catalog.'/storage/catalog-media/example/cover.jpg';
    config(['webblocks-plugins.catalog.base_url' => $catalog]);
    Http::preventStrayRequests();
    Http::fake([
      $catalog.'/api/plugins/example/latest?*' => Http::response(['data' => ['release' => [
        'version' => '1.2.3',
        'status' => 'published',
        'summary' => 'Improves the operator inbox.',
        'artifact' => [
          'download_url' => $catalog.'/downloads/example.zip',
          'checksum_sha256' => str_repeat('a', 64),
          'file_name' => 'example.zip',
        ],
      ]]]),
      $catalog.'/api/plugins/example?*' => Http::response([
        'data' => [
          'handle' => 'example',
          'label' => 'Example plugin',
          'summary' => 'Support site visitors.',
          'description' => 'Manage support conversations from the operator inbox.',
          'artwork' => ['card_url' => $cover, 'alt' => 'Plugin artwork'],
          'documentation_url' => $catalog.'/docs/example',
          'downloads' => ['total' => 144, 'daily' => [['date' => '2026-09-18', 'downloads' => 12]]],
          'media' => [
            ['role' => 'cover', 'url' => $cover],
            ['role' => 'icon', 'url' => $catalog.'/icon.png'],
            ['role' => 'screenshot', 'url' => 'javascript:alert(1)'],
            ['role' => 'screenshot', 'url' => $screenshot, 'alt' => 'Workspace', 'caption' => 'Example workspace', 'width' => 1440, 'height' => 900],
          ],
        ],
      ]),
      $catalog.'/api/plugins/legacy/latest?*' => Http::response([], 404),
      $catalog.'/api/plugins/legacy?*' => Http::response(['data' => [
        'handle' => 'legacy',
        'compatibility' => ['status' => 'unsupported'],
        'summary' => 'Legacy plugin summary.',
        'description' => 'Legacy plugin summary.',
      ]]),
      $catalog.'/api/plugins/cover-only/latest?*' => Http::response([], 404),
      $catalog.'/api/plugins/cover-only?*' => Http::response(['data' => [
        'handle' => 'cover-only',
        'artwork' => ['card_url' => $cover],
      ]]),
    ]);

    $this->withoutMiddleware();
    view()->share('errors', new ViewErrorBag);
    $installUrl = route('admin.plugins.catalog.install', 'example');
    $response = $this->get('/webadmin/plugins/catalog/example')
      ->assertOk()
      ->assertSeeInOrder(['Manage support conversations from the operator inbox.', 'Compatible', '1.2.3', 'Not installed', 'Free', 'Downloads', '144', 'Install from catalog', 'Documentation', 'Release notes'])
      ->assertSee('action="'.$installUrl.'"', false)
      ->assertSee('href="'.$catalog.'/docs/example'.'"', false)
      ->assertSee('Improves the operator inbox.')
      ->assertSeeInOrder(['src="'.$cover.'"', 'src="'.$screenshot.'"'], false)
      ->assertSee('alt="Plugin artwork"', false)
      ->assertSee('alt="Workspace"', false)
      ->assertSee('Example workspace')
      ->assertSee('width="1440" height="900"', false)
      ->assertSee('data-wb-slider-prev', false)
      ->assertSee('data-wb-slider-next', false)
      ->assertDontSee('Download activity')
      ->assertDontSee('data-wb-chart', false)
      ->assertDontSee('src="'.$catalog.'/icon.png"', false)
      ->assertDontSee('javascript:alert(1)', false);

    $dom = new DOMDocument;
    $dom->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $page = new DOMXPath($dom);
    $card = '//section[@aria-labelledby="plugin-catalog-title"]';
    $row = $card.'/div[contains(@class, "wb-row")]';
    $left = $row.'/div[contains(@class, "wb-col-md-5")]';
    $right = $row.'/div[contains(@class, "wb-col-md-7")]';
    $this->assertSame(2.0, $page->evaluate('count('.$row.'/div)'));
    $this->assertSame('144', $page->evaluate('normalize-space('.$left.'//tr[th[normalize-space()="Downloads"]]/td)'));
    $this->assertSame($catalog.'/docs/example', $page->evaluate('string('.$left.'/div[contains(@class, "wb-action-group")][form]/a/@href)'));
    $this->assertSame(0.0, $page->evaluate('count('.$left.'//img)'));
    $this->assertSame($cover, $page->evaluate('string('.$right.'//figure[contains(@class, "is-active")]/a/img/@src)'));

    $this->get('/webadmin/plugins/catalog/legacy')
      ->assertOk()
      ->assertSee('Incompatible')
      ->assertSee('This plugin is currently unavailable for installation.')
      ->assertSee('wb-col-12 wb-promo-copy', false)
      ->assertDontSee('action="'.route('admin.plugins.catalog.install', 'legacy').'"', false)
      ->assertDontSee('data-wb-slider', false);
    $this->get('/webadmin/plugins/catalog/cover-only')
      ->assertOk()
      ->assertSee('src="'.$cover.'"', false)
      ->assertSee('data-wb-slider', false)
      ->assertDontSee('data-wb-slider-prev', false)
      ->assertDontSee('data-wb-slider-next', false);
    Http::assertSentCount(6);
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
