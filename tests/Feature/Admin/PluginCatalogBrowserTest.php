<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

class PluginCatalogBrowserTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    config()->set('app.version', '1.33.0');
    config()->set('webblocks-plugins.catalog.base_url', 'https://plugins.example.test');
  }

  #[Test]
  public function super_admin_can_access_plugin_catalog_browser_from_plugin_listing(): void
  {
    Http::fake([
      'https://plugins.example.test/api/plugins*' => Http::response(['data' => []]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $listing = $this->actingAs($user)->get(route('admin.system.plugins.index'));

    $listing->assertOk();
    $listing->assertSeeText('Browse Plugin Catalog');
    $listing->assertSee('href="'.route('admin.plugins.catalog.index').'"', false);

    $catalog = $this->actingAs($user)->get(route('admin.plugins.catalog.index'));

    $catalog->assertOk();
    $catalog->assertSeeText('Plugin Catalog');
    $catalog->assertSeeText('No catalog plugins found.');
  }

  #[Test]
  public function non_authorized_users_cannot_access_plugin_catalog_browser(): void
  {
    Http::fake();

    $user = User::factory()->editor()->create();

    $this->actingAs($user)
      ->get(route('admin.plugins.catalog.index'))
      ->assertForbidden();

    Http::assertNothingSent();
  }

  #[Test]
  public function browser_renders_catalog_plugins_and_latest_compatible_release_data(): void
  {
    Http::fake([
      'https://plugins.example.test/api/plugins?*' => Http::response([
        'data' => [
          [
            'handle' => 'analytics-tools',
            'label' => 'Analytics Tools',
            'summary' => 'Calm reporting widgets for editors.',
            'vendor' => ['name' => 'WebBlocks Labs'],
            'compatibility' => [
              'status' => 'compatible',
              'requires_cms' => '^1.33',
            ],
            'channel' => 'stable',
            'status' => 'listed',
            'documentation_url' => 'https://plugins.example.test/plugins/analytics-tools/docs',
            'details_url' => 'https://plugins.example.test/plugins/analytics-tools',
          ],
        ],
      ]),
      'https://plugins.example.test/api/plugins/analytics-tools/latest?*' => Http::response([
        'data' => [
          'version' => '2.4.0',
          'required_cms_version' => '^1.33',
          'channel' => 'stable',
          'status' => 'published',
          'download_url' => 'https://plugins.example.test/downloads/analytics-tools.zip',
        ],
      ]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.plugins.catalog.index'));

    $response->assertOk();
    $response->assertSeeText('Analytics Tools');
    $response->assertSeeText('analytics-tools');
    $response->assertSeeText('Calm reporting widgets for editors.');
    $response->assertSeeText('WebBlocks Labs');
    $response->assertSeeText('2.4.0');
    $response->assertSeeText('Compatible');
    $response->assertSeeText('Requires ^1.33');
    $response->assertSeeText('stable');
    $response->assertSeeText('published');
    $response->assertSee('href="https://plugins.example.test/plugins/analytics-tools"', false);
    $response->assertSee('href="https://plugins.example.test/downloads/analytics-tools.zip"', false);
  }

  #[Test]
  public function browser_requests_public_webblocks_cms_compatible_plugins_and_latest_releases(): void
  {
    Http::fake([
      'https://plugins.example.test/api/plugins?*' => Http::response([
        'data' => [
          ['handle' => 'block-pack', 'label' => 'Block Pack'],
        ],
      ]),
      'https://plugins.example.test/api/plugins/block-pack/latest?*' => Http::response([
        'data' => ['version' => '1.0.0'],
      ]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->get(route('admin.plugins.catalog.index'))->assertOk();

    Http::assertSent(function ($request): bool {
      parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

      return str_starts_with($request->url(), 'https://plugins.example.test/api/plugins?')
        && ($query['host_product'] ?? null) === 'webblocks-cms'
        && ($query['version'] ?? null) === '1.33.0'
        && ($query['cms_version'] ?? null) === '1.33.0'
        && ($query['listed'] ?? null) === '1'
        && ($query['visibility'] ?? null) === 'public';
    });

    Http::assertSent(function ($request): bool {
      parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

      return str_starts_with($request->url(), 'https://plugins.example.test/api/plugins/block-pack/latest?')
        && ($query['host_product'] ?? null) === 'webblocks-cms'
        && ($query['version'] ?? null) === '1.33.0';
    });
  }

  #[Test]
  public function unavailable_catalog_response_shows_controlled_ui(): void
  {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.plugins.catalog.index'));

    $response->assertOk();
    $response->assertSeeText('The Plugin Catalog could not be reached within the configured timeout.');
    $response->assertSeeText('No catalog plugins found.');
  }

  #[Test]
  public function remote_text_is_escaped_and_external_urls_are_sanitized(): void
  {
    Http::fake([
      'https://plugins.example.test/api/plugins?*' => Http::response([
        'data' => [
          [
            'handle' => 'safe-plugin',
            'label' => '<script>alert("name")</script>',
            'summary' => '<img src=x onerror=alert(1)>',
            'documentation_url' => 'javascript:alert(1)',
            'details_url' => 'https://plugins.example.test/plugins/safe-plugin',
          ],
        ],
      ]),
      'https://plugins.example.test/api/plugins/safe-plugin/latest?*' => Http::response([
        'data' => ['version' => '1.0.0'],
      ]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.plugins.catalog.index'));

    $response->assertOk();
    $response->assertSeeText('<script>alert("name")</script>');
    $response->assertSee('&lt;script&gt;alert(&quot;name&quot;)&lt;/script&gt;', false);
    $response->assertDontSee('<script>alert("name")</script>', false);
    $response->assertDontSee('<img src=x onerror=alert(1)>', false);
    $response->assertDontSee('javascript:alert(1)', false);
    $response->assertSee('href="https://plugins.example.test/plugins/safe-plugin"', false);
  }

  #[Test]
  public function remote_catalog_data_does_not_register_runtime_plugin_behavior(): void
  {
    Http::fake([
      'https://plugins.example.test/api/plugins?*' => Http::response([
        'data' => [
          [
            'handle' => 'remote-runtime',
            'label' => 'Remote Runtime',
            'permissions' => ['remote-runtime.manage'],
            'routes' => ['/webadmin/plugins/remote-runtime/tools'],
            'commands' => ['remote-runtime:install'],
            'providers' => ['Vendor\\Remote\\ServiceProvider'],
            'migrations' => ['database/migrations'],
            'enabled' => true,
          ],
        ],
      ]),
      'https://plugins.example.test/api/plugins/remote-runtime/latest?*' => Http::response([
        'data' => ['version' => '9.9.9'],
      ]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->get(route('admin.plugins.catalog.index'))->assertOk();

    $registry = app(PluginRegistry::class);

    $this->assertNull($registry->get('remote-runtime'));
    $this->assertFalse($registry->isConfiguredEnabled('remote-runtime'));
    $this->assertNull(Route::getRoutes()->getByName('webblocks.plugins.remote_runtime.tools.index'));
    $this->assertArrayNotHasKey('remote-runtime', $registry->permissions());
    $this->assertFalse(collect($registry->menuItems())->contains(fn (array $menuItem): bool => $menuItem['plugin']->handle() === 'remote-runtime'));
  }

  #[Test]
  public function existing_manual_plugin_routes_remain_unchanged(): void
  {
    $this->assertSame('webadmin/system/plugins', Route::getRoutes()->getByName('admin.system.plugins.index')?->uri());
    $this->assertSame('webadmin/system/plugins/upload', Route::getRoutes()->getByName('admin.system.plugins.upload')?->uri());
    $this->assertSame('webadmin/system/plugins/{plugin}/enable', Route::getRoutes()->getByName('admin.system.plugins.enable')?->uri());
    $this->assertSame('webadmin/system/plugins/{plugin}/setup', Route::getRoutes()->getByName('admin.system.plugins.setup')?->uri());
    $this->assertSame('webadmin/system/plugins/{plugin}/disable', Route::getRoutes()->getByName('admin.system.plugins.disable')?->uri());
    $this->assertSame('webadmin/system/plugins/{plugin}/uninstall', Route::getRoutes()->getByName('admin.system.plugins.uninstall')?->uri());
    $this->assertSame('webadmin/system/plugins/{plugin}', Route::getRoutes()->getByName('admin.system.plugins.show')?->uri());
  }
}
