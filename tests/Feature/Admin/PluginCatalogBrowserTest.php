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
use WebBlocks\Cms\Support\WebBlocks;
use ZipArchive;

class PluginCatalogBrowserTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    config()->set('app.version', 'dev');
    config()->set('webblocks-plugins.catalog.base_url', 'https://plugins.example.test');
    config()->set('webblocks-plugins.install.root', storage_path('framework/testing/catalog-plugin-installs/'.str()->uuid()));
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
    $catalog->assertSeeText('Compatibility is checked against this CMS installation.');
    $catalog->assertSeeText('No catalog plugins found.');
    $catalog->assertDontSeeText('Catalog:');
    $catalog->assertDontSeeText('CMS:');
    $catalog->assertDontSeeText('plugins.example.test');
    $catalog->assertDontSeeText('CMS: dev');
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
    $response->assertDontSeeText('Catalog:');
    $response->assertDontSeeText('CMS:');
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
            'artifact_status' => 'ready',
          ],
        ],
      ]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.plugins.catalog.show', 'analytics-tools'));

    $response->assertOk();
    $response->assertSeeText('Analytics Tools');
    $response->assertSeeText('Compatibility is checked against this CMS installation.');
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
    $response->assertSeeText('ready');
    $response->assertSeeText(str_repeat('a', 64));
    $response->assertSeeText('Catalog ZIP Install');
    $response->assertSeeText('Catalog installs download the public ZIP on the server');
    $response->assertSeeText('Review compatibility and release metadata.');
    $response->assertSeeText('Review CMS ZIP validation results.');
    $response->assertSeeText('Enable and run setup only as separate explicit admin actions.');
    $response->assertSeeText('Install from Catalog');
    $response->assertSeeText('Upload plugin ZIP');
    $response->assertSeeText('Download ZIP');
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
    $response->assertDontSeeText('Catalog:');
    $response->assertDontSeeText('CMS:');
    $response->assertDontSeeText('Running CMS');
    $response->assertDontSeeText('CMS: dev');
  }

  #[Test]
  public function catalog_detail_maps_current_latest_release_artifact_payload(): void
  {
    Http::fake([
      'https://plugins.example.test/api/plugins/webblocks-redirect-manager?*' => Http::response([
        'data' => [
          'handle' => 'webblocks-redirect-manager',
          'latest_release' => [
            'version' => '0.1.0',
            'channel' => 'stable',
            'status' => 'published',
            'artifact' => [
              'file_name' => 'webblocks-redirect-manager-0.1.0.zip',
              'size_bytes' => 8383,
              'checksum_sha256' => 'f0c395d2e53b801fa89f024d4778d820ba7d8c36ed37609a3758ca6b780b8e64',
              'validation_status' => 'passed',
              'scan_status' => 'not_scanned',
              'download_url' => 'https://plugins.example.test/plugins/webblocks-redirect-manager/releases/1/artifact/download',
            ],
            'compatibility' => [
              [
                'product' => [
                  'key' => 'webblocks-cms',
                  'name' => 'WebBlocks CMS',
                ],
                'version_constraint' => '^1.32',
                'is_supported' => true,
              ],
            ],
          ],
        ],
      ]),
      'https://plugins.example.test/api/plugins/webblocks-redirect-manager/latest?*' => Http::response(['data' => []]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.plugins.catalog.show', 'webblocks-redirect-manager'));

    $response->assertOk();
    $response->assertSeeText('webblocks-redirect-manager');
    $response->assertSeeText('published');
    $response->assertSeeText('webblocks-redirect-manager-0.1.0.zip');
    $response->assertSeeText('8383');
    $response->assertSeeText('f0c395d2e53b801fa89f024d4778d820ba7d8c36ed37609a3758ca6b780b8e64');
    $response->assertSeeText('passed');
    $response->assertSeeText('not_scanned');
    $response->assertSeeText('Artifact Status / Validation Status');
    $response->assertSeeText('Scan Status');
    $response->assertSee('href="https://plugins.example.test/plugins/webblocks-redirect-manager/releases/1/artifact/download"', false);
    $response->assertSee('data-wb-copy-value="https://plugins.example.test/plugins/webblocks-redirect-manager/releases/1/artifact/download"', false);
    $response->assertSee('data-wb-copy-value="f0c395d2e53b801fa89f024d4778d820ba7d8c36ed37609a3758ca6b780b8e64"', false);
    $response->assertSee('action="'.route('admin.plugins.catalog.install', 'webblocks-redirect-manager').'"', false);
  }

  #[Test]
  public function catalog_detail_maps_latest_endpoint_sibling_artifact_payload(): void
  {
    Http::fake([
      'https://plugins.example.test/api/plugins/webblocks-redirect-manager?*' => Http::response([
        'data' => [
          'plugin' => [
            'handle' => 'webblocks-redirect-manager',
            'label' => 'WebBlocks Redirect Manager',
            'compatibility' => ['status' => 'compatible'],
          ],
        ],
      ]),
      'https://plugins.example.test/api/plugins/webblocks-redirect-manager/latest?*' => Http::response([
        'data' => [
          'release' => [
            'version' => '0.1.1',
            'channel' => 'stable',
            'status' => 'published',
          ],
          'artifact' => [
            'file_name' => 'webblocks-redirect-manager-0.1.1.zip',
            'size_bytes' => 12000,
            'checksum_sha256' => str_repeat('c', 64),
            'validation_status' => 'passed',
            'scan_status' => 'not_scanned',
            'download_url' => 'https://plugins.example.test/plugins/webblocks-redirect-manager/releases/2/artifact/download',
          ],
        ],
      ]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.plugins.catalog.show', 'webblocks-redirect-manager'));

    $response->assertOk();
    $response->assertSeeText('0.1.1');
    $response->assertSeeText('webblocks-redirect-manager-0.1.1.zip');
    $response->assertSeeText('12000');
    $response->assertSeeText(str_repeat('c', 64));
    $response->assertSeeText('passed');
    $response->assertSeeText('not_scanned');
    $response->assertSee('href="https://plugins.example.test/plugins/webblocks-redirect-manager/releases/2/artifact/download"', false);
    $response->assertSee('action="'.route('admin.plugins.catalog.install', 'webblocks-redirect-manager').'"', false);
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
        && ($query['version'] ?? null) === WebBlocks::version()
        && ($query['cms_version'] ?? null) === WebBlocks::version();
    });

    Http::assertSent(function ($request): bool {
      parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

      return str_starts_with($request->url(), 'https://plugins.example.test/api/plugins/block-pack/latest?')
        && ($query['host_product'] ?? null) === 'webblocks-cms'
        && ($query['version'] ?? null) === WebBlocks::version()
        && ($query['cms_version'] ?? null) === WebBlocks::version();
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
    $response->assertSeeText('No downloadable artifact is available for this release.');
    $response->assertSeeText('Install from Catalog');
    $response->assertSee('disabled', false);
    $response->assertDontSeeText('Download ZIP');
    $response->assertDontSeeText('Copy download URL');
    $response->assertDontSeeText('Copy checksum');
    $response->assertDontSee('data-wb-copy-value=', false);
  }

  #[Test]
  public function catalog_detail_keeps_install_unavailable_when_artifact_metadata_is_missing(): void
  {
    Http::fake([
      'https://plugins.example.test/api/plugins/no-artifact-plugin?*' => Http::response([
        'data' => [
          'plugin' => [
            'handle' => 'no-artifact-plugin',
            'label' => 'No Artifact Plugin',
            'compatibility' => ['status' => 'compatible'],
          ],
          'latest_release' => [
            'version' => '1.0.0',
            'status' => 'published',
          ],
        ],
      ]),
      'https://plugins.example.test/api/plugins/no-artifact-plugin/latest?*' => Http::response(['data' => []]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.plugins.catalog.show', 'no-artifact-plugin'));

    $response->assertOk();
    $response->assertSeeText('No downloadable artifact is available for this release.');
    $response->assertSeeText('Install from Catalog');
    $response->assertSee('disabled', false);
    $response->assertDontSee('action="'.route('admin.plugins.catalog.install', 'no-artifact-plugin').'"', false);
  }

  #[Test]
  public function catalog_detail_keeps_install_unavailable_when_latest_release_is_not_published(): void
  {
    Http::fake([
      'https://plugins.example.test/api/plugins/draft-plugin?*' => Http::response([
        'data' => [
          'plugin' => [
            'handle' => 'draft-plugin',
            'label' => 'Draft Plugin',
            'compatibility' => ['status' => 'compatible'],
          ],
          'latest_release' => [
            'version' => '1.0.0',
            'status' => 'draft',
            'artifact' => [
              'file_name' => 'draft-plugin-1.0.0.zip',
              'size_bytes' => 2048,
              'checksum_sha256' => str_repeat('b', 64),
              'download_url' => 'https://plugins.example.test/downloads/draft-plugin.zip',
              'validation_status' => 'passed',
            ],
          ],
        ],
      ]),
      'https://plugins.example.test/api/plugins/draft-plugin/latest?*' => Http::response(['data' => []]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.plugins.catalog.show', 'draft-plugin'));

    $response->assertOk();
    $response->assertSeeText('draft');
    $response->assertSeeText('draft-plugin-1.0.0.zip');
    $response->assertSee('disabled', false);
    $response->assertDontSee('action="'.route('admin.plugins.catalog.install', 'draft-plugin').'"', false);
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
    $response->assertSeeText('Plugin Catalog is not available right now. Please try again later.');
    $response->assertSeeText('Catalog plugin unavailable.');
    $response->assertDontSeeText('plugins.example.test');
    $response->assertDontSeeText('CMS: dev');
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
        && ($query['version'] ?? null) === WebBlocks::version()
        && ($query['cms_version'] ?? null) === WebBlocks::version()
        && ($query['listed'] ?? null) === '1'
        && ($query['visibility'] ?? null) === 'public';
    });

    Http::assertSent(function ($request): bool {
      parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

      return str_starts_with($request->url(), 'https://plugins.example.test/api/plugins/block-pack/latest?')
        && ($query['host_product'] ?? null) === 'webblocks-cms'
        && ($query['version'] ?? null) === WebBlocks::version()
        && ($query['cms_version'] ?? null) === WebBlocks::version();
    });
  }

  #[Test]
  public function catalog_uses_default_public_base_url_when_config_key_is_missing(): void
  {
    config()->set('webblocks-plugins', ['enabled' => []]);

    Http::fake([
      'https://plugins.webblocksui.com/api/plugins?*' => Http::response(['data' => []]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->get(route('admin.plugins.catalog.index'))->assertOk();

    Http::assertSent(function ($request): bool {
      return str_starts_with($request->url(), 'https://plugins.webblocksui.com/api/plugins?');
    });
  }

  #[Test]
  public function catalog_uses_default_public_base_url_when_config_is_empty(): void
  {
    config()->set('webblocks-plugins.catalog.base_url', '   ');

    Http::fake([
      'https://plugins.webblocksui.com/api/plugins?*' => Http::response(['data' => []]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->get(route('admin.plugins.catalog.index'))->assertOk();

    Http::assertSent(function ($request): bool {
      return str_starts_with($request->url(), 'https://plugins.webblocksui.com/api/plugins?');
    });
  }

  #[Test]
  public function explicit_catalog_base_url_override_wins_internally(): void
  {
    config()->set('webblocks-plugins.catalog.base_url', ' https://custom-plugins.example.test/ ');

    Http::fake([
      'https://custom-plugins.example.test/api/plugins?*' => Http::response(['data' => []]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->get(route('admin.plugins.catalog.index'))->assertOk();

    Http::assertSent(function ($request): bool {
      return str_starts_with($request->url(), 'https://custom-plugins.example.test/api/plugins?');
    });
  }

  #[Test]
  public function unavailable_catalog_response_shows_controlled_ui(): void
  {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.plugins.catalog.index'));

    $response->assertOk();
    $response->assertSeeText('Plugin Catalog is not available right now. Please try again later.');
    $response->assertSeeText('No catalog plugins found.');
    $response->assertDontSeeText('plugins.example.test');
    $response->assertDontSeeText('CMS: dev');
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
    $this->assertSame('webadmin/plugins/catalog/{handle}/install', Route::getRoutes()->getByName('admin.plugins.catalog.install')?->uri());
    $this->assertSame('POST', implode('|', Route::getRoutes()->getByName('admin.system.plugins.upload')?->methods() ?? []));
  }

  #[Test]
  public function catalog_install_downloads_checksum_verifies_and_installs_disabled(): void
  {
    $zip = $this->pluginZipBody();

    Http::fake($this->installFakeResponses([
      'zip' => $zip,
      'checksum' => hash('sha256', $zip),
    ]));

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->post(route('admin.plugins.catalog.install', 'sample-tools'));

    $response->assertRedirect(route('admin.system.plugins.index'));
    $response->assertSessionHas('status', 'Plugin sample-tools 1.0.0 was installed disabled from the Plugin Catalog. Review it before enabling.');
    $this->assertFileExists(config('webblocks-plugins.install.root').'/sample-tools/1.0.0/webblocks-plugin.json');
    $this->assertFileDoesNotExist(config('webblocks-plugins.install.root').'/sample-tools/enabled.json');
  }

  #[Test]
  public function catalog_install_checksum_mismatch_blocks_install(): void
  {
    $zip = $this->pluginZipBody();

    Http::fake($this->installFakeResponses([
      'zip' => $zip,
      'checksum' => str_repeat('0', 64),
    ]));

    $user = User::factory()->superAdmin()->create();

    $response = $this->from(route('admin.plugins.catalog.show', 'sample-tools'))
      ->actingAs($user)
      ->post(route('admin.plugins.catalog.install', 'sample-tools'));

    $response->assertRedirect(route('admin.plugins.catalog.show', 'sample-tools'));
    $response->assertSessionHasErrors(['catalog_install' => 'The downloaded catalog artifact failed SHA-256 verification.']);
    $this->assertFileDoesNotExist(config('webblocks-plugins.install.root').'/sample-tools/1.0.0/webblocks-plugin.json');
  }

  #[Test]
  public function catalog_install_invalid_zip_shows_controlled_error(): void
  {
    $user = User::factory()->superAdmin()->create();

    Http::fake($this->installFakeResponses([
      'zip' => 'not a zip',
      'checksum' => hash('sha256', 'not a zip'),
    ]));

    $this->from(route('admin.plugins.catalog.show', 'sample-tools'))
      ->actingAs($user)
      ->post(route('admin.plugins.catalog.install', 'sample-tools'))
      ->assertRedirect(route('admin.plugins.catalog.show', 'sample-tools'))
      ->assertSessionHasErrors(['catalog_install' => 'The catalog artifact is not a valid plugin ZIP archive.']);

  }

  #[Test]
  public function catalog_install_download_failure_shows_controlled_error(): void
  {
    Http::fake(function ($request) {
      if (str_starts_with($request->url(), 'https://plugins.example.test/downloads/sample-tools.zip')) {
        throw new ConnectionException('download failed');
      }

      if (str_starts_with($request->url(), 'https://plugins.example.test/api/plugins/sample-tools/latest?')) {
        return Http::response([
          'data' => [
            'release' => [
              'version' => '1.0.0',
              'status' => 'published',
              'download_url' => 'https://plugins.example.test/downloads/sample-tools.zip',
              'checksum_sha256' => hash('sha256', $this->pluginZipBody()),
              'artifact_filename' => 'sample-tools-1.0.0.zip',
            ],
          ],
        ]);
      }

      return Http::response([
        'data' => [
          'plugin' => [
            'handle' => 'sample-tools',
            'label' => 'Sample Tools',
            'compatibility' => ['status' => 'compatible'],
          ],
        ],
      ]);
    });

    $user = User::factory()->superAdmin()->create();

    $this->from(route('admin.plugins.catalog.show', 'sample-tools'))
      ->actingAs($user)
      ->post(route('admin.plugins.catalog.install', 'sample-tools'))
      ->assertRedirect(route('admin.plugins.catalog.show', 'sample-tools'))
      ->assertSessionHasErrors(['catalog_install' => 'The catalog artifact could not be downloaded. Try again later.']);
  }

  #[Test]
  public function catalog_install_requires_compatible_release_before_downloading(): void
  {
    Http::fake([
      'https://plugins.example.test/api/plugins/sample-tools?*' => Http::response([
        'data' => [
          'plugin' => [
            'handle' => 'sample-tools',
            'label' => 'Sample Tools',
            'compatibility' => ['status' => 'incompatible'],
          ],
        ],
      ]),
      'https://plugins.example.test/api/plugins/sample-tools/latest?*' => Http::response([
        'data' => [
          'release' => [
            'version' => '1.0.0',
            'download_url' => 'https://plugins.example.test/downloads/sample-tools.zip',
            'checksum_sha256' => str_repeat('a', 64),
            'artifact_filename' => 'sample-tools-1.0.0.zip',
          ],
        ],
      ]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $this->from(route('admin.plugins.catalog.show', 'sample-tools'))
      ->actingAs($user)
      ->post(route('admin.plugins.catalog.install', 'sample-tools'))
      ->assertRedirect(route('admin.plugins.catalog.show', 'sample-tools'))
      ->assertSessionHasErrors(['catalog_install' => 'This catalog plugin is not compatible with this CMS installation.']);

    Http::assertNotSent(fn ($request): bool => $request->url() === 'https://plugins.example.test/downloads/sample-tools.zip');
  }

  #[Test]
  public function catalog_install_requires_complete_artifact_metadata(): void
  {
    Http::fake([
      'https://plugins.example.test/api/plugins/sample-tools?*' => Http::response([
        'data' => [
          'plugin' => [
            'handle' => 'sample-tools',
            'label' => 'Sample Tools',
            'compatibility' => ['status' => 'compatible'],
          ],
        ],
      ]),
      'https://plugins.example.test/api/plugins/sample-tools/latest?*' => Http::response([
        'data' => [
          'release' => [
            'version' => '1.0.0',
            'download_url' => 'https://plugins.example.test/downloads/sample-tools.zip',
          ],
        ],
      ]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $this->from(route('admin.plugins.catalog.show', 'sample-tools'))
      ->actingAs($user)
      ->post(route('admin.plugins.catalog.install', 'sample-tools'))
      ->assertRedirect(route('admin.plugins.catalog.show', 'sample-tools'))
      ->assertSessionHasErrors(['catalog_install' => 'This catalog release is missing downloadable artifact metadata.']);
  }

  #[Test]
  public function catalog_install_does_not_enable_plugin_or_run_setup(): void
  {
    $zip = $this->pluginZipBody([
      'migrations' => ['database/migrations'],
    ]);

    Http::fake($this->installFakeResponses([
      'zip' => $zip,
      'checksum' => hash('sha256', $zip),
    ]));

    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->post(route('admin.plugins.catalog.install', 'sample-tools'));

    $this->assertFileExists(config('webblocks-plugins.install.root').'/sample-tools/1.0.0/webblocks-plugin.json');
    $this->assertFileDoesNotExist(config('webblocks-plugins.install.root').'/sample-tools/enabled.json');
  }

  #[Test]
  public function catalog_listing_uses_table_action_group_without_wrapping_no_links_text(): void
  {
    Http::fake([
      'https://plugins.example.test/api/plugins?*' => Http::response([
        'data' => [
          ['handle' => 'bare-plugin', 'label' => 'Bare Plugin'],
        ],
      ]),
      'https://plugins.example.test/api/plugins/bare-plugin/latest?*' => Http::response(['data' => []]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.plugins.catalog.index'));

    $response->assertOk();
    $response->assertSeeText('Actions / Links');
    $response->assertSee('<td class="wb-table-actions">', false);
    $response->assertSee('<div class="wb-action-group"', false);
    $response->assertDontSeeText('No links');
  }

  /**
   * @param  array<string, mixed>  $options
   * @return array<string, mixed>
   */
  private function installFakeResponses(array $options): array
  {
    $zip = (string) ($options['zip'] ?? $this->pluginZipBody());
    $checksum = (string) ($options['checksum'] ?? hash('sha256', $zip));
    $status = (int) ($options['status'] ?? 200);

    return [
      'https://plugins.example.test/api/plugins/sample-tools?*' => Http::response([
        'data' => [
          'plugin' => [
            'handle' => 'sample-tools',
            'label' => 'Sample Tools',
            'compatibility' => ['status' => 'compatible'],
          ],
        ],
      ]),
      'https://plugins.example.test/api/plugins/sample-tools/latest?*' => Http::response([
        'data' => [
          'release' => [
            'version' => '1.0.0',
            'channel' => 'stable',
            'status' => 'published',
            'artifact' => [
              'download_url' => 'https://plugins.example.test/downloads/sample-tools.zip',
              'checksum_sha256' => $checksum,
              'file_name' => 'sample-tools-1.0.0.zip',
              'size_bytes' => strlen($zip),
              'validation_status' => 'passed',
              'scan_status' => 'not_scanned',
            ],
          ],
        ],
      ]),
      'https://plugins.example.test/downloads/sample-tools.zip*' => Http::response($zip, $status, [
        'Content-Length' => (string) strlen($zip),
        'Content-Type' => 'application/zip',
      ]),
    ];
  }

  /**
   * @param  array<string, mixed>  $override
   */
  private function pluginZipBody(array $override = []): string
  {
    $path = storage_path('framework/testing/catalog-plugin-'.str()->uuid().'.zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('webblocks-plugin.json', json_encode(array_merge([
      'handle' => 'sample-tools',
      'label' => 'Sample Tools',
      'version' => '1.0.0',
      'provider' => 'Vendor\\SampleTools\\SampleToolsPlugin',
      'required_cms_version' => '^1.32',
      'permissions' => [],
      'commands' => [],
      'routes' => [],
      'settings' => [],
      'migrations' => [],
      'assets' => [],
      'health' => null,
    ], $override), JSON_PRETTY_PRINT));
    $zip->addFromString('src/SampleToolsPlugin.php', '<?php');
    $zip->close();

    return (string) file_get_contents($path);
  }
}
