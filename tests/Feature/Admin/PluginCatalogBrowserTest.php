<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
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
    $response->assertSee('href="'.route('admin.plugins.catalog.show', 'analytics-tools').'"', false);
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
  public function super_admin_can_open_catalog_plugin_detail_page(): void
  {
    Http::fake([
      'https://plugins.example.test/api/plugins/analytics-tools?*' => Http::response([
        'data' => [
          'plugin' => [
            'handle' => 'analytics-tools',
            'label' => 'Analytics Tools',
            'summary' => 'Calm reporting widgets for editors.',
            'description' => 'Adds read-only analytics dashboards for WebBlocks CMS operators.',
            'vendor' => ['name' => 'WebBlocks Labs'],
            'author' => ['name' => 'Catalog Team'],
            'compatibility' => [
              'status' => 'compatible',
              'requires_cms' => '^1.33',
            ],
            'status' => 'listed',
            'website_url' => 'https://plugins.example.test/vendors/webblocks-labs',
            'documentation_url' => 'https://plugins.example.test/plugins/analytics-tools/docs',
            'support_url' => 'https://plugins.example.test/plugins/analytics-tools/support',
            'details_url' => 'https://plugins.example.test/plugins/analytics-tools',
            'permissions' => ['analytics-tools.view'],
            'routes' => ['/webadmin/plugins/analytics-tools'],
            'migrations' => ['create_analytics_tools_tables'],
            'providers' => ['Vendor\\AnalyticsTools\\ServiceProvider'],
            'commands' => ['analytics-tools:sync'],
          ],
        ],
      ]),
      'https://plugins.example.test/api/plugins/analytics-tools/latest?*' => Http::response([
        'data' => [
          'release' => [
            'version' => '2.4.0',
            'required_cms_version' => '^1.33',
            'channel' => 'stable',
            'status' => 'published',
            'summary' => 'Adds a stable analytics dashboard release.',
            'highlights' => ['Dashboard widgets', 'CSV export'],
            'download_url' => 'https://plugins.example.test/downloads/analytics-tools.zip',
            'checksum_sha256' => str_repeat('a', 64),
            'artifact_filename' => 'analytics-tools-2.4.0.zip',
            'artifact_size' => '42 KB',
          ],
        ],
      ]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.plugins.catalog.show', 'analytics-tools'));

    $response->assertOk();
    $response->assertSeeText('Analytics Tools');
    $response->assertSeeText('analytics-tools');
    $response->assertSeeText('Calm reporting widgets for editors.');
    $response->assertSeeText('Adds read-only analytics dashboards for WebBlocks CMS operators.');
    $response->assertSeeText('WebBlocks Labs');
    $response->assertSeeText('Catalog Team');
    $response->assertSeeText('Compatible');
    $response->assertSeeText('^1.33');
    $response->assertSeeText('2.4.0');
    $response->assertSeeText('stable');
    $response->assertSeeText('published');
    $response->assertSeeText('Adds a stable analytics dashboard release.');
    $response->assertSeeText('Dashboard widgets');
    $response->assertSeeText('CSV export');
    $response->assertSeeText('analytics-tools-2.4.0.zip');
    $response->assertSeeText('42 KB');
    $response->assertSeeText(str_repeat('a', 64));
    $response->assertSeeText('Catalog data is informational only.');
    $response->assertSeeText('Manual ZIP Install');
    $response->assertSeeText('Review compatibility and release metadata.');
    $response->assertSeeText('Compare the SHA-256 checksum when provided.');
    $response->assertSeeText('Review CMS ZIP validation results.');
    $response->assertSeeText('Enable and run setup only after explicit admin review.');
    $response->assertSeeText('Upload plugin ZIP');
    $response->assertSeeText('Open/download ZIP');
    $response->assertSeeText('Copy download URL');
    $response->assertSeeText('Copy checksum');
    $response->assertSeeText('Not installed');
    $response->assertSee('href="'.route('admin.system.plugins.index').'"', false);
    $response->assertSee('href="https://plugins.example.test/downloads/analytics-tools.zip"', false);
    $response->assertSee('data-wb-copy-value="https://plugins.example.test/downloads/analytics-tools.zip"', false);
    $response->assertSee('data-wb-copy-value="'.str_repeat('a', 64).'"', false);
    $response->assertSee('cms/js/admin/plugin-catalog-copy.js', false);
    $response->assertSeeText('analytics-tools.view');
    $response->assertSeeText('/webadmin/plugins/analytics-tools');
    $response->assertSeeText('create_analytics_tools_tables');
    $response->assertSeeText('Vendor\\AnalyticsTools\\ServiceProvider');
    $response->assertSeeText('analytics-tools:sync');
  }

  #[Test]
  public function non_authorized_users_cannot_open_catalog_plugin_detail_page(): void
  {
    Http::fake();

    $user = User::factory()->editor()->create();

    $this->actingAs($user)
      ->get(route('admin.plugins.catalog.show', 'analytics-tools'))
      ->assertForbidden();

    Http::assertNothingSent();
  }

  #[Test]
  public function catalog_detail_requests_plugin_and_latest_release_with_cms_context(): void
  {
    Http::fake([
      'https://plugins.example.test/api/plugins/block-pack?*' => Http::response([
        'data' => [
          'plugin' => ['handle' => 'block-pack', 'label' => 'Block Pack'],
        ],
      ]),
      'https://plugins.example.test/api/plugins/block-pack/latest?*' => Http::response([
        'data' => ['release' => ['version' => '1.0.0']],
      ]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->get(route('admin.plugins.catalog.show', 'block-pack'))->assertOk();

    Http::assertSent(function ($request): bool {
      parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

      return str_starts_with($request->url(), 'https://plugins.example.test/api/plugins/block-pack?')
        && ($query['host_product'] ?? null) === 'webblocks-cms'
        && ($query['version'] ?? null) === '1.33.0'
        && ($query['cms_version'] ?? null) === '1.33.0';
    });

    Http::assertSent(function ($request): bool {
      parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

      return str_starts_with($request->url(), 'https://plugins.example.test/api/plugins/block-pack/latest?')
        && ($query['host_product'] ?? null) === 'webblocks-cms'
        && ($query['version'] ?? null) === '1.33.0'
        && ($query['cms_version'] ?? null) === '1.33.0';
    });
  }

  #[Test]
  public function catalog_detail_missing_optional_fields_render_safely(): void
  {
    Http::fake([
      'https://plugins.example.test/api/plugins/minimal-plugin?*' => Http::response([
        'data' => [
          'plugin' => [
            'handle' => 'minimal-plugin',
            'label' => 'Minimal Plugin',
          ],
        ],
      ]),
      'https://plugins.example.test/api/plugins/minimal-plugin/latest?*' => Http::response([
        'data' => [],
      ]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.plugins.catalog.show', 'minimal-plugin'));

    $response->assertOk();
    $response->assertSeeText('Minimal Plugin');
    $response->assertSeeText('Not provided');
    $response->assertDontSeeText('Open/download ZIP');
    $response->assertDontSeeText('Copy download URL');
    $response->assertDontSeeText('Copy checksum');
    $response->assertDontSee('data-wb-copy-value=', false);
  }

  #[Test]
  public function catalog_detail_installed_state_comes_from_local_registry_not_remote_claims(): void
  {
    $registry = new PluginRegistry(['local-tools' => true]);
    $registry->register(
      PluginDefinition::make('local-tools')
        ->label('Local Tools')
        ->version('3.2.1')
    );
    $this->app->instance(PluginRegistry::class, $registry);

    Http::fake([
      'https://plugins.example.test/api/plugins/local-tools?*' => Http::response([
        'data' => [
          'plugin' => [
            'handle' => 'local-tools',
            'label' => 'Local Tools',
            'enabled' => false,
          ],
        ],
      ]),
      'https://plugins.example.test/api/plugins/local-tools/latest?*' => Http::response([
        'data' => ['release' => ['version' => '9.9.9']],
      ]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.plugins.catalog.show', 'local-tools'));

    $response->assertOk();
    $response->assertSeeText('Local State');
    $response->assertSeeText('Installed');
    $response->assertSeeText('Local Version');
    $response->assertSeeText('3.2.1');
    $response->assertSeeText('Local Lifecycle');
    $response->assertSeeText('Enabled');
  }

  #[Test]
  public function unavailable_catalog_detail_response_shows_controlled_ui(): void
  {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.plugins.catalog.show', 'offline-plugin'));

    $response->assertOk();
    $response->assertSeeText('The Plugin Catalog detail could not be reached within the configured timeout.');
    $response->assertSeeText('Catalog plugin unavailable.');
  }

  #[Test]
  public function unknown_catalog_detail_handle_shows_controlled_not_found_ui(): void
  {
    Http::fake([
      'https://plugins.example.test/api/plugins/missing-plugin?*' => Http::response(['message' => 'Not found'], 404),
    ]);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.plugins.catalog.show', 'missing-plugin'));

    $response->assertOk();
    $response->assertSeeText('The requested catalog plugin was not found.');
    $response->assertSeeText('Catalog plugin unavailable.');
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
  public function catalog_detail_remote_text_is_escaped_and_external_urls_are_sanitized(): void
  {
    Http::fake([
      'https://plugins.example.test/api/plugins/safe-plugin?*' => Http::response([
        'data' => [
          'plugin' => [
            'handle' => 'safe-plugin',
            'label' => '<script>alert("name")</script>',
            'summary' => '<img src=x onerror=alert(1)>',
            'description' => '<strong onclick=alert(1)>Remote description</strong>',
            'website_url' => 'javascript:alert(1)',
            'documentation_url' => 'https://plugins.example.test/plugins/safe-plugin/docs',
            'support_url' => 'data:text/html,unsafe',
            'details_url' => 'https://plugins.example.test/plugins/safe-plugin',
          ],
        ],
      ]),
      'https://plugins.example.test/api/plugins/safe-plugin/latest?*' => Http::response([
        'data' => [
          'release' => [
            'version' => '1.0.0',
            'summary' => '<script>alert("release")</script>',
            'download_url' => 'ftp://plugins.example.test/unsafe.zip',
          ],
        ],
      ]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.plugins.catalog.show', 'safe-plugin'));

    $response->assertOk();
    $response->assertSeeText('<script>alert("name")</script>');
    $response->assertSee('&lt;script&gt;alert(&quot;name&quot;)&lt;/script&gt;', false);
    $response->assertSee('&lt;strong onclick=alert(1)&gt;Remote description&lt;/strong&gt;', false);
    $response->assertDontSee('<script>alert("name")</script>', false);
    $response->assertDontSee('<img src=x onerror=alert(1)>', false);
    $response->assertDontSee('<strong onclick=alert(1)>Remote description</strong>', false);
    $response->assertDontSee('javascript:alert(1)', false);
    $response->assertDontSee('data:text/html,unsafe', false);
    $response->assertDontSee('ftp://plugins.example.test/unsafe.zip', false);
    $response->assertDontSee('data-wb-copy-value="ftp://plugins.example.test/unsafe.zip"', false);
    $response->assertSee('href="https://plugins.example.test/plugins/safe-plugin/docs"', false);
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
  public function remote_catalog_detail_data_does_not_register_runtime_plugin_behavior(): void
  {
    Http::fake([
      'https://plugins.example.test/api/plugins/remote-runtime?*' => Http::response([
        'data' => [
          'plugin' => [
            'handle' => 'remote-runtime',
            'label' => 'Remote Runtime',
            'permissions' => ['remote-runtime.manage'],
            'routes' => ['/webadmin/plugins/remote-runtime/tools'],
            'commands' => ['remote-runtime:install'],
            'providers' => ['Vendor\\Remote\\ServiceProvider'],
            'migrations' => ['database/migrations'],
            'enabled' => true,
            'download_url' => 'https://plugins.example.test/downloads/remote-runtime.zip',
          ],
        ],
      ]),
      'https://plugins.example.test/api/plugins/remote-runtime/latest?*' => Http::response([
        'data' => ['release' => ['version' => '9.9.9']],
      ]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->get(route('admin.plugins.catalog.show', 'remote-runtime'))->assertOk();

    $registry = app(PluginRegistry::class);

    $this->assertNull($registry->get('remote-runtime'));
    $this->assertFalse($registry->isConfiguredEnabled('remote-runtime'));
    $this->assertNull(Route::getRoutes()->getByName('webblocks.plugins.remote_runtime.tools.index'));
    $this->assertArrayNotHasKey('remote-runtime', $registry->permissions());
    $this->assertFalse(collect($registry->menuItems())->contains(fn (array $menuItem): bool => $menuItem['plugin']->handle() === 'remote-runtime'));

    $response = $this->actingAs($user)->get(route('admin.plugins.catalog.show', 'remote-runtime'));
    $response->assertOk();
    $response->assertSeeText('Not installed');
    $response->assertDontSeeText('Installed version');
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
    $this->assertSame('POST', implode('|', Route::getRoutes()->getByName('admin.system.plugins.upload')?->methods() ?? []));
  }
}
