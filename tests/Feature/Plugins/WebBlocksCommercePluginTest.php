<?php

namespace Tests\Feature\Plugins;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrderItem;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommercePayment;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceWebhookEvent;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\WebBlocksCommerceSchema;
use WebBlocks\Cms\Support\Database\CmsTable;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\Plugins\PluginApiRouteRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;
use WebBlocks\Cms\Support\Plugins\PluginHealthMonitor;
use WebBlocks\Cms\Support\Plugins\PluginMigrationRunner;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRouteRegistrar;
use ZipArchive;

class WebBlocksCommercePluginTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    config()->set('webblocks-plugins.enabled.webblocks-commerce', false);
    $this->installWebBlocksCommercePluginForTest();
  }

  #[Test]
  public function commerce_plugin_is_registered_disabled_by_default_with_owned_metadata(): void
  {
    $registry = app(PluginRegistry::class);
    $plugin = $registry->get('webblocks-commerce');

    $this->assertNotNull($plugin);
    $this->assertSame('WebBlocks Commerce', $plugin->labelText());
    $this->assertSame('0.8.0', $plugin->versionText());
    $this->assertSame('^1.37.3', $plugin->requiredCmsVersion());
    $this->assertSame('webblocks_commerce', $plugin->settingsNamespaceValue());
    $this->assertSame('webblocks_commerce_', $plugin->databasePrefixValue());
    $this->assertSame(['database/migrations'], $plugin->migrationPaths());
    $this->assertArrayHasKey('webblocks-commerce::buy-button', $plugin->blockTypeDefinitions());
    $this->assertFalse($registry->isEnabled('webblocks-commerce'));
    $this->assertSame([], $registry->menuItems());
    $this->assertContains('webblocks-commerce-buy-button', app(PluginBlockCatalog::class)->allCatalogSlugs());
    $this->assertNotContains('webblocks-commerce-buy-button', app(PluginBlockCatalog::class)->enabledCatalogSlugs());
    $this->assertSame('unavailable', app(PluginHealthMonitor::class)->healthFor($plugin)->status);
  }

  #[Test]
  public function enabled_plugin_exposes_permissions_settings_and_setup_required_health(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);

    $registry = app(PluginRegistry::class);
    $plugin = $registry->get('webblocks-commerce');
    $health = app(PluginHealthMonitor::class)->healthFor($plugin);

    $this->assertTrue($registry->isEnabled('webblocks-commerce'));
    $this->assertArrayHasKey('webblocks-commerce.view', $plugin->permissionsList());
    $this->assertArrayHasKey('webblocks-commerce.manage', $plugin->permissionsList());
    $this->assertArrayHasKey('webblocks-commerce.manage-products', $plugin->permissionsList());
    $this->assertArrayHasKey('webblocks-commerce.manage-orders', $plugin->permissionsList());
    $this->assertArrayHasKey('webblocks-commerce.manage-settings', $plugin->permissionsList());
    $this->assertNotNull($plugin->settingsDefinition());
    $this->assertSame('webblocks-commerce', $registry->menuItems()[0]['plugin']->handle());
    $this->assertSame('commerce-products', $registry->menuItems()[0]['item']->key());
    $this->assertSame('commerce-orders', $registry->menuItems()[1]['item']->key());
    $this->assertSame('commerce-settings', $registry->menuItems()[2]['item']->key());
    $this->assertSame('warning', $health->status);
    $this->assertStringContainsString('Setup required. Plugin migrations pending.', $health->message);
  }

  #[Test]
  public function enabled_plugin_admin_routes_register_under_plugin_namespace_only(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);

    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    Route::getRoutes()->refreshNameLookups();

    $productRoute = Route::getRoutes()->getByName('webblocks.plugins.webblocks_commerce.products.index');
    $orderRoute = Route::getRoutes()->getByName('webblocks.plugins.webblocks_commerce.orders.index');
    $settingsRoute = Route::getRoutes()->getByName('webblocks.plugins.webblocks_commerce.settings.edit');

    $this->assertNotNull($productRoute);
    $this->assertSame('webadmin/plugins/webblocks-commerce/products', $productRoute?->uri());
    $this->assertNotNull($orderRoute);
    $this->assertSame('webadmin/plugins/webblocks-commerce/orders', $orderRoute?->uri());
    $this->assertNotNull($settingsRoute);
    $this->assertSame('webadmin/plugins/webblocks-commerce/settings', $settingsRoute?->uri());
    $this->assertNull(Route::getRoutes()->getByName('admin.commerce.products.index'));
    $this->assertNull(Route::getRoutes()->getByName('admin.commerce.orders.index'));
    $this->assertNull(Route::getRoutes()->getByName('admin.commerce.settings.edit'));
  }

  #[Test]
  public function product_index_renders_setup_required_before_plugin_migrations(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get('/webadmin/plugins/webblocks-commerce/products');

    $response->assertOk();
    $response->assertSee('Plugin Setup Required');
    $response->assertSee('Run Plugin Migrations');
  }

  #[Test]
  public function commerce_migration_creates_plugin_owned_tables(): void
  {
    $this->migrateWebBlocksCommercePlugin();

    $this->assertTrue(Schema::hasTable('webblocks_commerce_products'));
    $this->assertTrue(Schema::hasTable('webblocks_commerce_orders'));
    $this->assertTrue(Schema::hasTable('webblocks_commerce_order_items'));
    $this->assertTrue(Schema::hasTable('webblocks_commerce_payments'));
    $this->assertTrue(Schema::hasTable('webblocks_commerce_webhook_events'));

    $this->assertTrue(Schema::hasColumns('webblocks_commerce_products', [
      'site_id',
      'image_media_id',
      'title',
      'slug',
      'status',
      'price_amount',
      'currency',
      'inventory_quantity',
      'metadata',
    ]));
    $this->assertTrue(Schema::hasColumns('webblocks_commerce_orders', [
      'order_number',
      'customer_email',
      'status',
      'subtotal_amount',
      'total_amount',
      'currency',
      'gateway',
      'gateway_checkout_id',
      'gateway_payment_id',
      'paid_at',
    ]));
    $this->assertTrue(Schema::hasColumns('webblocks_commerce_webhook_events', [
      'gateway',
      'event_id',
      'event_type',
      'processed_at',
      'payload_digest',
      'status',
    ]));
    $this->assertDatabaseHas(CmsTable::name('block_types'), [
      'slug' => 'webblocks-commerce-buy-button',
      'name' => 'Commerce Buy Button',
      'category' => 'commerce',
      'status' => 'published',
    ]);
  }

  #[Test]
  public function commerce_buy_button_block_type_is_discoverable_only_when_plugin_is_enabled(): void
  {
    $this->migrateWebBlocksCommercePlugin();
    $blockType = BlockType::query()->where('slug', 'webblocks-commerce-buy-button')->firstOrFail();

    $disabledCatalog = app(PluginBlockCatalog::class);

    $this->assertTrue($disabledCatalog->isPluginCatalogSlug('webblocks-commerce-buy-button'));
    $this->assertFalse($disabledCatalog->isEnabledCatalogSlug('webblocks-commerce-buy-button'));
    $this->assertCount(0, $disabledCatalog->filterDiscoverableBlockTypes(collect([$blockType])));

    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    $this->app->forgetInstance(PluginRegistry::class);
    $this->app->forgetInstance(PluginBlockCatalog::class);

    $enabledCatalog = app(PluginBlockCatalog::class);

    $this->assertTrue($enabledCatalog->isEnabledCatalogSlug('webblocks-commerce-buy-button'));
    $this->assertCount(1, $enabledCatalog->filterDiscoverableBlockTypes(collect([$blockType])));
  }

  #[Test]
  public function installed_commerce_plugin_setup_runner_creates_buy_button_catalog_row(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    $this->app->forgetInstance(PluginRegistry::class);

    $plugin = app(PluginRegistry::class)->get('webblocks-commerce');
    $result = app(PluginMigrationRunner::class)->run($plugin);

    $this->assertTrue($result['ran']);
    $this->assertDatabaseHas(CmsTable::name('block_types'), [
      'slug' => 'webblocks-commerce-buy-button',
      'name' => 'Commerce Buy Button',
    ]);
    $this->assertTrue(Schema::hasTable('webblocks_commerce_products'));
  }

  #[Test]
  public function internal_api_can_install_enable_and_setup_commerce_plugin_from_zip(): void
  {
    $root = storage_path('framework/testing/plugin-api/'.str()->uuid());
    config()->set('webblocks-plugins.install.root', $root);
    config()->set('webblocks-plugins.enabled.webblocks-commerce', false);
    $this->app->forgetInstance(PluginRegistry::class);
    $this->app->forgetInstance(PluginBlockCatalog::class);

    $this->createInternalApiToken('secret-token', [
      CmsApiTokenCapabilities::PLUGINS_READ,
      CmsApiTokenCapabilities::PLUGINS_INSTALL,
      CmsApiTokenCapabilities::PLUGINS_MANAGE,
      CmsApiTokenCapabilities::PLUGINS_SETUP,
    ]);

    $install = $this->withInternalToken()
      ->post('/webadmin/api/plugins/install', [
        'plugin_zip' => new UploadedFile(
          $this->commercePluginZipPath(),
          'webblocks-commerce.zip',
          'application/zip',
          null,
          true
        ),
      ], ['Accept' => 'application/json']);

    $install->assertCreated();
    $install->assertJsonPath('installed.handle', 'webblocks-commerce');
    $install->assertJsonPath('plugin.enabled', false);

    $enable = $this->withInternalToken()
      ->postJson('/webadmin/api/plugins/webblocks-commerce/enable');

    $enable->assertOk();
    $enable->assertJsonPath('plugin.configured_enabled', true);

    $setup = $this->withInternalToken()
      ->postJson('/webadmin/api/plugins/webblocks-commerce/setup');

    $setup->assertOk();
    $setup->assertJsonPath('setup.ran', true);
    $this->assertTrue(Schema::hasTable('webblocks_commerce_products'));
    $this->assertDatabaseHas(CmsTable::name('block_types'), [
      'slug' => 'webblocks-commerce-buy-button',
      'name' => 'Commerce Buy Button',
    ]);
  }

  #[Test]
  public function internal_api_can_create_product_and_place_commerce_buy_button_block(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    $this->app->forgetInstance(PluginRegistry::class);
    $this->migrateWebBlocksCommercePlugin();
    app(PluginApiRouteRegistrar::class)->registerEnabledApiRoutes();
    $this->createInternalApiToken('secret-token', [
      'commerce.read',
      'commerce.products.write',
      CmsApiTokenCapabilities::CONTENT_VALIDATE,
      CmsApiTokenCapabilities::CONTENT_APPLY,
    ]);

    $site = Site::query()->firstOrFail();
    $productResponse = $this->withInternalToken()
      ->postJson('/webadmin/api/commerce/products', [
        'site_id' => $site->id,
        'title' => 'Original Painting',
        'slug' => 'original-painting',
        'description' => 'Acrylic on canvas.',
        'status' => CommerceProduct::STATUS_ACTIVE,
        'price_amount' => 125000,
        'currency' => 'usd',
        'inventory_quantity' => 1,
        'sku' => 'ART-API-001',
      ]);

    $productResponse->assertCreated();
    $productResponse->assertJsonPath('product.currency', 'USD');
    $productResponse->assertJsonPath('product.buy_url', '/commerce/products/original-painting/buy');

    $productId = (int) $productResponse->json('product.id');

    $blockTypes = $this->withInternalToken()->getJson('/webadmin/api/block-types');
    $blockTypes->assertOk();
    $commerceBlock = collect($blockTypes->json('block_types'))->firstWhere('slug', 'webblocks-commerce-buy-button');

    $this->assertIsArray($commerceBlock);
    $this->assertSame('/webadmin/api/commerce/products', $commerceBlock['commerce_products_url']);
    $this->assertArrayHasKey('commerce_product_id', $commerceBlock['settings_schema']);

    $apply = $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', $this->commerceBuyButtonPlanPayload($productId));

    $apply->assertCreated();
    $this->assertDatabaseHas(CmsTable::name('blocks'), [
      'type' => 'webblocks-commerce-buy-button',
      'status' => 'draft',
    ]);

    $block = Block::query()->where('type', 'webblocks-commerce-buy-button')->firstOrFail();
    $settings = json_decode((string) $block->settings, true);
    $this->assertSame($productId, (int) ($settings['commerce_product_id'] ?? 0));
  }

  #[Test]
  public function internal_commerce_product_write_requires_explicit_capability(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    $this->app->forgetInstance(PluginRegistry::class);
    $this->migrateWebBlocksCommercePlugin();
    app(PluginApiRouteRegistrar::class)->registerEnabledApiRoutes();
    $this->createInternalApiToken('secret-token', ['commerce.read']);

    $this->withInternalToken()
      ->postJson('/webadmin/api/commerce/products', [
        'title' => 'Original Painting',
        'slug' => 'original-painting',
        'status' => CommerceProduct::STATUS_ACTIVE,
        'price_amount' => 125000,
        'currency' => 'USD',
      ])
      ->assertForbidden();
  }

  #[Test]
  public function commerce_buy_button_public_view_links_to_selected_product_buy_page(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    app(PluginRegistry::class);
    $this->migrateWebBlocksCommercePlugin();

    $product = CommerceProduct::query()->create([
      'title' => 'Original Painting',
      'slug' => 'original-painting',
      'status' => CommerceProduct::STATUS_ACTIVE,
      'price_amount' => 125000,
      'currency' => 'USD',
      'inventory_quantity' => 1,
    ]);
    $block = new Block([
      'settings' => json_encode([
        'commerce_product_id' => (string) $product->id,
        'label' => 'Buy This Work',
        'show_price' => '1',
        'alignment' => 'center',
      ], JSON_UNESCAPED_SLASHES),
    ]);

    $html = view('webblocks-cms::pages.partials.blocks.webblocks-commerce-buy-button', [
      'block' => $block,
    ])->render();

    $this->assertStringContainsString(route('webblocks.commerce.cart.items.add', $product->id), $html);
    $this->assertStringContainsString('method="POST"', $html);
    $this->assertStringContainsString('Buy This Work', $html);
    $this->assertStringContainsString('1,250.00 USD', $html);
    $this->assertStringContainsString('wb-justify-center', $html);
  }

  #[Test]
  public function schema_and_health_report_ready_after_plugin_migrations(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    $this->migrateWebBlocksCommercePlugin();

    $schema = app(WebBlocksCommerceSchema::class);
    $plugin = app(PluginRegistry::class)->get('webblocks-commerce');
    $health = app(PluginHealthMonitor::class)->healthFor($plugin);

    $this->assertTrue($schema->isReady());
    $this->assertSame([], $schema->missingTables());
    $this->assertSame('healthy', $health->status);
    $this->assertStringContainsString('Commerce tables are ready.', $health->message);
  }

  #[Test]
  public function commerce_models_can_store_foundation_records(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    app(PluginRegistry::class);
    $this->migrateWebBlocksCommercePlugin();

    $site = Site::query()->firstOrFail();
    $product = CommerceProduct::query()->create([
      'site_id' => $site->id,
      'title' => 'Original Painting',
      'slug' => 'original-painting',
      'description' => 'Acrylic on canvas.',
      'status' => CommerceProduct::STATUS_ACTIVE,
      'price_amount' => 125000,
      'currency' => 'USD',
      'inventory_quantity' => 1,
      'sku' => 'ART-001',
      'metadata' => ['medium' => 'acrylic'],
    ]);
    $order = CommerceOrder::query()->create([
      'site_id' => $site->id,
      'order_number' => 'WB-1001',
      'customer_email' => 'collector@example.test',
      'status' => CommerceOrder::STATUS_PENDING,
      'subtotal_amount' => 125000,
      'total_amount' => 125000,
      'currency' => 'USD',
      'gateway' => 'stripe',
      'gateway_checkout_id' => 'cs_test_123',
    ]);
    $item = CommerceOrderItem::query()->create([
      'order_id' => $order->id,
      'product_id' => $product->id,
      'title' => $product->title,
      'sku' => $product->sku,
      'quantity' => 1,
      'unit_amount' => 125000,
      'total_amount' => 125000,
      'currency' => 'USD',
    ]);
    $payment = CommercePayment::query()->create([
      'order_id' => $order->id,
      'gateway' => 'stripe',
      'gateway_payment_id' => 'pi_test_123',
      'gateway_checkout_id' => 'cs_test_123',
      'status' => CommercePayment::STATUS_PENDING,
      'amount' => 125000,
      'currency' => 'USD',
      'raw_event_id' => 'evt_test_123',
    ]);
    $event = CommerceWebhookEvent::query()->create([
      'gateway' => 'stripe',
      'event_id' => 'evt_test_123',
      'event_type' => 'checkout.session.completed',
      'payload_digest' => hash('sha256', 'payload'),
      'status' => CommerceWebhookEvent::STATUS_RECEIVED,
    ]);

    $this->assertTrue($product->isAvailableForCheckout());
    $this->assertTrue($order->isPending());
    $this->assertSame($product->id, $item->product()->first()?->id);
    $this->assertSame($order->id, $payment->order()->first()?->id);
    $this->assertSame('evt_test_123', $event->event_id);
    $this->assertSame(1, $order->items()->count());
    $this->assertSame(1, $order->payments()->count());
  }

  #[Test]
  public function permitted_super_admin_can_manage_commerce_products(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    $this->migrateWebBlocksCommercePlugin();

    $site = Site::query()->firstOrFail();
    $user = User::factory()->superAdmin()->create();

    $index = $this->actingAs($user)->get(route('webblocks.plugins.webblocks_commerce.products.index'));
    $index->assertOk();
    $index->assertSee('Commerce Products');
    $index->assertSee('New Product');

    $create = $this->actingAs($user)->get(route('webblocks.plugins.webblocks_commerce.products.create'));
    $create->assertOk();
    $create->assertSee('New Commerce Product');

    $store = $this->actingAs($user)->post(route('webblocks.plugins.webblocks_commerce.products.store'), [
      'site_id' => $site->id,
      'title' => 'Original Painting',
      'slug' => '',
      'description' => 'Acrylic on canvas.',
      'status' => CommerceProduct::STATUS_ACTIVE,
      'price_amount' => 125000,
      'currency' => 'usd',
      'inventory_quantity' => 1,
      'sku' => 'ART-001',
    ]);

    $product = CommerceProduct::query()->firstOrFail();
    $store->assertRedirect(route('webblocks.plugins.webblocks_commerce.products.show', $product));
    $this->assertSame('original-painting', $product->slug);
    $this->assertSame('USD', $product->currency);

    $show = $this->actingAs($user)->get(route('webblocks.plugins.webblocks_commerce.products.show', $product));
    $show->assertOk();
    $show->assertSee('Original Painting');
    $show->assertSee('1,250.00 USD');

    $update = $this->actingAs($user)->put(route('webblocks.plugins.webblocks_commerce.products.update', $product), [
      'site_id' => '',
      'title' => 'Updated Painting',
      'slug' => 'updated-painting',
      'description' => 'Updated description.',
      'status' => CommerceProduct::STATUS_DRAFT,
      'price_amount' => 99000,
      'currency' => 'EUR',
      'inventory_quantity' => '',
      'sku' => '',
    ]);

    $update->assertRedirect(route('webblocks.plugins.webblocks_commerce.products.show', $product));
    $this->assertDatabaseHas('webblocks_commerce_products', [
      'id' => $product->id,
      'site_id' => null,
      'title' => 'Updated Painting',
      'slug' => 'updated-painting',
      'status' => CommerceProduct::STATUS_DRAFT,
      'price_amount' => 99000,
      'currency' => 'EUR',
      'inventory_quantity' => null,
      'sku' => null,
    ]);

    $archive = $this->actingAs($user)->post(route('webblocks.plugins.webblocks_commerce.products.archive', $product));
    $archive->assertRedirect(route('webblocks.plugins.webblocks_commerce.products.show', $product));
    $this->assertSame(CommerceProduct::STATUS_ARCHIVED, $product->fresh()->status);
  }

  #[Test]
  public function product_admin_routes_require_plugin_permissions(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    $this->migrateWebBlocksCommercePlugin();

    $user = User::factory()->siteAdmin()->create();

    $this->actingAs($user)
      ->get(route('webblocks.plugins.webblocks_commerce.products.index'))
      ->assertForbidden();
  }

  #[Test]
  public function permitted_super_admin_can_review_commerce_orders(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    $this->migrateWebBlocksCommercePlugin();

    $site = Site::query()->firstOrFail();
    $product = CommerceProduct::query()->create([
      'site_id' => $site->id,
      'title' => 'Original Painting',
      'slug' => 'original-painting',
      'status' => CommerceProduct::STATUS_ACTIVE,
      'price_amount' => 125000,
      'currency' => 'USD',
      'inventory_quantity' => 1,
      'sku' => 'ART-001',
    ]);
    $order = CommerceOrder::query()->create([
      'site_id' => $site->id,
      'order_number' => 'WB-1002',
      'customer_email' => 'collector@example.test',
      'status' => CommerceOrder::STATUS_PENDING,
      'subtotal_amount' => 125000,
      'total_amount' => 125000,
      'currency' => 'USD',
      'gateway' => 'fake',
      'gateway_checkout_id' => 'fake_checkout_reference_123456',
    ]);
    $order->items()->create([
      'product_id' => $product->id,
      'title' => $product->title,
      'sku' => $product->sku,
      'quantity' => 1,
      'unit_amount' => 125000,
      'total_amount' => 125000,
      'currency' => 'USD',
    ]);
    $order->payments()->create([
      'gateway' => 'fake',
      'gateway_checkout_id' => 'fake_checkout_reference_123456',
      'status' => CommercePayment::STATUS_PENDING,
      'amount' => 125000,
      'currency' => 'USD',
    ]);

    $user = User::factory()->superAdmin()->create();

    $index = $this->actingAs($user)->get(route('webblocks.plugins.webblocks_commerce.orders.index'));
    $index->assertOk();
    $index->assertSee('Commerce Orders');
    $index->assertSee('WB-1002');
    $index->assertSee('collector@example.test');
    $index->assertSee('1,250.00 USD');

    $show = $this->actingAs($user)->get(route('webblocks.plugins.webblocks_commerce.orders.show', $order));
    $show->assertOk();
    $show->assertSee('WB-1002');
    $show->assertSee('Payment Attempts');
    $show->assertSee('Original Painting');
    $show->assertSee('fake_che...123456');
    $show->assertSee('Read-only order');
  }

  #[Test]
  public function order_admin_routes_require_order_permission(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    $this->migrateWebBlocksCommercePlugin();

    $user = User::factory()->siteAdmin()->create();

    $this->actingAs($user)
      ->get(route('webblocks.plugins.webblocks_commerce.orders.index'))
      ->assertForbidden();
  }

  #[Test]
  public function permitted_super_admin_can_review_commerce_settings_diagnostics(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    config()->set('webblocks-commerce.gateway', 'paypal');
    config()->set('webblocks-commerce.paypal.mode', 'sandbox');
    config()->set('webblocks-commerce.paypal.client_id', 'paypal-client-id');
    config()->set('webblocks-commerce.paypal.client_secret', 'paypal-client-secret');
    config()->set('webblocks-commerce.paypal.webhook_id', 'paypal-webhook-id');
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('webblocks.plugins.webblocks_commerce.settings.edit'));

    $response->assertOk();
    $response->assertSee('Commerce Settings');
    $response->assertSee('Checkout Readiness');
    $response->assertSee('PayPal Configuration');
    $response->assertSee('Webhook ready');
    $response->assertSee('WEBBLOCKS_COMMERCE_PAYPAL_CLIENT_ID');
    $response->assertSee('/commerce/webhooks/paypal');
    $response->assertDontSee('paypal-client-secret');
  }

  #[Test]
  public function commerce_settings_route_requires_settings_permission(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();

    $user = User::factory()->siteAdmin()->create();

    $this->actingAs($user)
      ->get(route('webblocks.plugins.webblocks_commerce.settings.edit'))
      ->assertForbidden();
  }

  #[Test]
  public function commerce_settings_reports_sumup_readiness_without_rendering_the_api_key(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    config()->set('webblocks-commerce.gateway', 'sumup');
    config()->set('webblocks-commerce.sumup.mode', 'sandbox');
    config()->set('webblocks-commerce.sumup.api_key', 'sk_test_private-sumup-key');
    config()->set('webblocks-commerce.sumup.merchant_code', 'MTEST123');
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();

    $user = User::factory()->superAdmin()->create();
    $response = $this->actingAs($user)->get(route('webblocks.plugins.webblocks_commerce.settings.edit'));

    $response->assertOk();
    $response->assertSee('SumUp Configuration');
    $response->assertSee('WEBBLOCKS_COMMERCE_SUMUP_API_KEY');
    $response->assertSee('WEBBLOCKS_COMMERCE_SUMUP_MERCHANT_CODE');
    $response->assertSee('/commerce/webhooks/sumup');
    $response->assertDontSee('sk_test_private-sumup-key');
  }

  #[Test]
  public function public_commerce_routes_are_inert_when_plugin_is_disabled(): void
  {
    $this->get('/commerce/products/original-painting/buy')
      ->assertNotFound();
  }

  #[Test]
  public function public_buy_page_starts_fake_hosted_checkout_without_marking_order_paid(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    config()->set('webblocks-commerce.gateway', 'fake');
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    $this->migrateWebBlocksCommercePlugin();

    $site = Site::query()->firstOrFail();
    $product = CommerceProduct::query()->create([
      'site_id' => $site->id,
      'title' => 'Original Painting',
      'slug' => 'original-painting',
      'description' => 'Acrylic on canvas.',
      'status' => CommerceProduct::STATUS_ACTIVE,
      'price_amount' => 125000,
      'currency' => 'USD',
      'inventory_quantity' => 1,
    ]);

    $buy = $this->get(route('webblocks.commerce.products.buy', $product->slug));
    $buy->assertOk();
    $buy->assertSee('Original Painting');
    $buy->assertSee('Add to cart');
    $buy->assertSee('Buy now');

    $checkout = $this->post(route('webblocks.commerce.products.checkout', $product->slug));
    $checkout->assertRedirect();

    $order = CommerceOrder::query()->with(['items', 'payments'])->firstOrFail();
    $this->assertSame(CommerceOrder::STATUS_PENDING, $order->status);
    $this->assertSame('fake', $order->gateway);
    $this->assertNotNull($order->gateway_checkout_id);
    $this->assertSame(1, $order->items()->count());
    $this->assertSame(1, $order->payments()->count());
    $this->assertSame(CommercePayment::STATUS_PENDING, $order->payments->first()->status);

    $success = $this->get($checkout->headers->get('Location'));
    $success->assertOk();
    $success->assertSee('Payment Processing');
    $success->assertSee($order->order_number);
    $this->assertSame(CommerceOrder::STATUS_PENDING, $order->fresh()->status);
  }

  #[Test]
  public function public_buy_page_starts_paypal_hosted_checkout_when_configured(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    config()->set('webblocks-commerce.gateway', 'paypal');
    config()->set('webblocks-commerce.paypal.mode', 'sandbox');
    config()->set('webblocks-commerce.paypal.client_id', 'paypal-client-id');
    config()->set('webblocks-commerce.paypal.client_secret', 'paypal-client-secret');
    config()->set('webblocks-commerce.paypal.webhook_id', 'paypal-webhook-id');
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    $this->migrateWebBlocksCommercePlugin();

    Http::fake([
      'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
        'access_token' => 'paypal-access-token',
      ]),
      'https://api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
        'id' => 'PAYPAL-ORDER-123',
        'links' => [[
          'rel' => 'approve',
          'href' => 'https://www.paypal.com/checkoutnow?token=PAYPAL-ORDER-123',
        ]],
      ], 201),
    ]);

    $site = Site::query()->firstOrFail();
    $product = CommerceProduct::query()->create([
      'site_id' => $site->id,
      'title' => 'Original Painting',
      'slug' => 'original-painting',
      'status' => CommerceProduct::STATUS_ACTIVE,
      'price_amount' => 125000,
      'currency' => 'USD',
      'inventory_quantity' => 1,
    ]);

    $buy = $this->get(route('webblocks.commerce.products.buy', $product->slug));
    $buy->assertOk();
    $buy->assertSee('Add to cart');
    $buy->assertSee('Buy now');

    $checkout = $this->post(route('webblocks.commerce.products.checkout', $product->slug));
    $checkout->assertRedirect('https://www.paypal.com/checkoutnow?token=PAYPAL-ORDER-123');

    $order = CommerceOrder::query()->with(['items', 'payments'])->firstOrFail();
    $this->assertSame(CommerceOrder::STATUS_PENDING, $order->status);
    $this->assertSame('paypal', $order->gateway);
    $this->assertSame('PAYPAL-ORDER-123', $order->gateway_checkout_id);
    $this->assertSame(1, $order->items()->count());
    $this->assertSame(1, $order->payments()->count());
    $this->assertSame(CommercePayment::STATUS_PENDING, $order->payments->first()->status);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api-m.sandbox.paypal.com/v1/oauth2/token');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
      && $request['intent'] === 'CAPTURE'
      && $request['purchase_units'][0]['amount']['value'] === '1250.00');
  }

  #[Test]
  public function public_buy_page_starts_sumup_hosted_checkout_when_configured(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    config()->set('webblocks-commerce.gateway', 'sumup');
    config()->set('webblocks-commerce.sumup.mode', 'sandbox');
    config()->set('webblocks-commerce.sumup.api_key', 'sk_test_sumup-secret');
    config()->set('webblocks-commerce.sumup.merchant_code', 'MTEST123');
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    $this->migrateWebBlocksCommercePlugin();

    Http::fake([
      'https://api.sumup.com/v0.1/checkouts' => Http::response([
        'id' => 'SUMUP-CHECKOUT-123',
        'status' => 'PENDING',
        'hosted_checkout_url' => 'https://checkout.sumup.com/pay/SUMUP-CHECKOUT-123',
      ], 201),
    ]);

    $site = Site::query()->firstOrFail();
    $product = CommerceProduct::query()->create([
      'site_id' => $site->id,
      'title' => 'Paracord',
      'slug' => 'paracord',
      'status' => CommerceProduct::STATUS_ACTIVE,
      'price_amount' => 500,
      'currency' => 'EUR',
      'inventory_quantity' => 10,
    ]);

    $checkout = $this->post(route('webblocks.commerce.products.checkout', $product->slug));
    $checkout->assertRedirect('https://checkout.sumup.com/pay/SUMUP-CHECKOUT-123');

    $order = CommerceOrder::query()->with('payments')->firstOrFail();
    $this->assertSame('sumup', $order->gateway);
    $this->assertSame('SUMUP-CHECKOUT-123', $order->gateway_checkout_id);
    $this->assertSame(500, $order->total_amount);
    $this->assertSame(CommercePayment::STATUS_PENDING, $order->payments->first()->status);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.sumup.com/v0.1/checkouts'
      && $request->hasHeader('Authorization', 'Bearer sk_test_sumup-secret')
      && $request['amount'] === 5
      && $request['currency'] === 'EUR'
      && $request['merchant_code'] === 'MTEST123'
      && $request['checkout_reference'] === $order->order_number
      && $request['hosted_checkout']['enabled'] === true
      && $request['return_url'] === route('webblocks.commerce.webhooks.sumup'));
  }

  #[Test]
  public function sumup_webhook_retrieves_checkout_and_marks_matching_order_paid_idempotently(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    config()->set('webblocks-commerce.gateway', 'sumup');
    config()->set('webblocks-commerce.sumup.mode', 'sandbox');
    config()->set('webblocks-commerce.sumup.api_key', 'sk_test_sumup-secret');
    config()->set('webblocks-commerce.sumup.merchant_code', 'MTEST123');
    $this->migrateWebBlocksCommercePlugin();

    Http::fake([
      'https://api.sumup.com/v0.1/checkouts/SUMUP-CHECKOUT-PAID' => Http::response([
        'id' => 'SUMUP-CHECKOUT-PAID',
        'checkout_reference' => 'WB-SUMUP-1001',
        'amount' => 5,
        'currency' => 'EUR',
        'merchant_code' => 'MTEST123',
        'status' => 'PAID',
        'transaction_id' => 'SUMUP-TRANSACTION-123',
        'transaction_code' => 'TESTRX123',
        'transactions' => [[
          'id' => 'SUMUP-TRANSACTION-123',
          'transaction_code' => 'TESTRX123',
          'status' => 'SUCCESSFUL',
          'amount' => 5,
          'currency' => 'EUR',
        ]],
      ]),
    ]);

    $order = CommerceOrder::query()->create([
      'order_number' => 'WB-SUMUP-1001',
      'status' => CommerceOrder::STATUS_PENDING,
      'subtotal_amount' => 420,
      'tax_amount' => 80,
      'total_amount' => 500,
      'currency' => 'EUR',
      'gateway' => 'sumup',
      'gateway_checkout_id' => 'SUMUP-CHECKOUT-PAID',
    ]);
    $order->payments()->create([
      'gateway' => 'sumup',
      'gateway_checkout_id' => 'SUMUP-CHECKOUT-PAID',
      'status' => CommercePayment::STATUS_PENDING,
      'amount' => 500,
      'currency' => 'EUR',
    ]);

    $payload = [
      'event_type' => 'CHECKOUT_STATUS_CHANGED',
      'id' => 'SUMUP-CHECKOUT-PAID',
    ];

    $first = $this->postJson('/commerce/webhooks/sumup', $payload);
    $first->assertOk()->assertJson([
      'status' => CommerceWebhookEvent::STATUS_PROCESSED,
      'event_id' => 'SUMUP-CHECKOUT-PAID:paid',
    ]);

    $freshOrder = $order->fresh();
    $this->assertSame(CommerceOrder::STATUS_PAID, $freshOrder->status);
    $this->assertSame('SUMUP-TRANSACTION-123', $freshOrder->gateway_payment_id);
    $this->assertNotNull($freshOrder->paid_at);
    $this->assertDatabaseHas('webblocks_commerce_payments', [
      'order_id' => $order->id,
      'gateway' => 'sumup',
      'gateway_payment_id' => 'SUMUP-TRANSACTION-123',
      'status' => CommercePayment::STATUS_SUCCEEDED,
      'raw_event_id' => 'SUMUP-CHECKOUT-PAID:paid',
    ]);

    $second = $this->postJson('/commerce/webhooks/sumup', $payload);
    $second->assertOk()->assertJson([
      'status' => CommerceWebhookEvent::STATUS_PROCESSED,
      'event_id' => 'SUMUP-CHECKOUT-PAID:paid',
    ]);

    $this->assertSame(1, CommerceWebhookEvent::query()->where('gateway', 'sumup')->count());
    $this->assertSame(1, CommercePayment::query()->where('order_id', $order->id)->count());
  }

  #[Test]
  public function sumup_webhook_does_not_trust_a_checkout_with_mismatched_totals(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    config()->set('webblocks-commerce.gateway', 'sumup');
    config()->set('webblocks-commerce.sumup.api_key', 'sk_test_sumup-secret');
    config()->set('webblocks-commerce.sumup.merchant_code', 'MTEST123');
    $this->migrateWebBlocksCommercePlugin();

    Http::fake([
      'https://api.sumup.com/v0.1/checkouts/SUMUP-CHECKOUT-MISMATCH' => Http::response([
        'id' => 'SUMUP-CHECKOUT-MISMATCH',
        'checkout_reference' => 'WB-SUMUP-1002',
        'amount' => 6,
        'currency' => 'EUR',
        'merchant_code' => 'MTEST123',
        'status' => 'PAID',
        'transactions' => [[
          'id' => 'SUMUP-TRANSACTION-MISMATCH',
          'status' => 'SUCCESSFUL',
          'amount' => 6,
          'currency' => 'EUR',
        ]],
      ]),
    ]);

    $order = CommerceOrder::query()->create([
      'order_number' => 'WB-SUMUP-1002',
      'status' => CommerceOrder::STATUS_PENDING,
      'subtotal_amount' => 420,
      'tax_amount' => 80,
      'total_amount' => 500,
      'currency' => 'EUR',
      'gateway' => 'sumup',
      'gateway_checkout_id' => 'SUMUP-CHECKOUT-MISMATCH',
    ]);
    $order->payments()->create([
      'gateway' => 'sumup',
      'gateway_checkout_id' => 'SUMUP-CHECKOUT-MISMATCH',
      'status' => CommercePayment::STATUS_PENDING,
      'amount' => 500,
      'currency' => 'EUR',
    ]);

    $response = $this->postJson('/commerce/webhooks/sumup', [
      'event_type' => 'CHECKOUT_STATUS_CHANGED',
      'id' => 'SUMUP-CHECKOUT-MISMATCH',
    ]);

    $response->assertOk()->assertJson(['status' => CommerceWebhookEvent::STATUS_FAILED]);
    $this->assertSame(CommerceOrder::STATUS_PENDING, $order->fresh()->status);
    $this->assertSame(CommercePayment::STATUS_PENDING, $order->payments()->firstOrFail()->status);
    $this->assertDatabaseHas('webblocks_commerce_webhook_events', [
      'gateway' => 'sumup',
      'event_id' => 'SUMUP-CHECKOUT-MISMATCH:paid',
      'status' => CommerceWebhookEvent::STATUS_FAILED,
    ]);
  }

  #[Test]
  public function paypal_approved_order_webhook_captures_payment_and_marks_order_paid_idempotently(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    config()->set('webblocks-commerce.gateway', 'paypal');
    config()->set('webblocks-commerce.paypal.mode', 'sandbox');
    config()->set('webblocks-commerce.paypal.client_id', 'paypal-client-id');
    config()->set('webblocks-commerce.paypal.client_secret', 'paypal-client-secret');
    config()->set('webblocks-commerce.paypal.webhook_id', 'paypal-webhook-id');
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    $this->migrateWebBlocksCommercePlugin();

    Http::fake([
      'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
        'access_token' => 'paypal-access-token',
      ]),
      'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
        'verification_status' => 'SUCCESS',
      ]),
      'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-ORDER-123/capture' => Http::response([
        'status' => 'COMPLETED',
        'payer' => [
          'email_address' => 'collector@example.test',
          'payer_id' => 'PAYPAL-PAYER-123',
        ],
        'purchase_units' => [[
          'payments' => [
            'captures' => [[
              'id' => 'PAYPAL-CAPTURE-123',
              'status' => 'COMPLETED',
            ]],
          ],
        ]],
      ], 201),
    ]);

    $site = Site::query()->firstOrFail();
    $product = CommerceProduct::query()->create([
      'site_id' => $site->id,
      'title' => 'Original Painting',
      'slug' => 'original-painting',
      'status' => CommerceProduct::STATUS_ACTIVE,
      'price_amount' => 125000,
      'currency' => 'USD',
      'inventory_quantity' => 1,
    ]);
    $order = CommerceOrder::query()->create([
      'site_id' => $site->id,
      'order_number' => 'WB-1003',
      'status' => CommerceOrder::STATUS_PENDING,
      'subtotal_amount' => 125000,
      'total_amount' => 125000,
      'currency' => 'USD',
      'gateway' => 'paypal',
      'gateway_checkout_id' => 'PAYPAL-ORDER-123',
    ]);
    $order->items()->create([
      'product_id' => $product->id,
      'title' => $product->title,
      'quantity' => 1,
      'unit_amount' => 125000,
      'total_amount' => 125000,
      'currency' => 'USD',
    ]);
    $order->payments()->create([
      'gateway' => 'paypal',
      'gateway_checkout_id' => 'PAYPAL-ORDER-123',
      'status' => CommercePayment::STATUS_PENDING,
      'amount' => 125000,
      'currency' => 'USD',
    ]);

    $payload = [
      'id' => 'WH-EVENT-123',
      'event_type' => 'CHECKOUT.ORDER.APPROVED',
      'resource' => [
        'id' => 'PAYPAL-ORDER-123',
      ],
    ];

    $first = $this->withHeaders($this->paypalWebhookHeaders())->postJson('/commerce/webhooks/paypal', $payload);
    $first->assertOk();
    $first->assertJson([
      'status' => CommerceWebhookEvent::STATUS_PROCESSED,
      'event_id' => 'WH-EVENT-123',
    ]);

    $freshOrder = $order->fresh();
    $this->assertSame(CommerceOrder::STATUS_PAID, $freshOrder->status);
    $this->assertSame('collector@example.test', $freshOrder->customer_email);
    $this->assertSame('PAYPAL-CAPTURE-123', $freshOrder->gateway_payment_id);
    $this->assertNotNull($freshOrder->paid_at);
    $this->assertDatabaseHas('webblocks_commerce_payments', [
      'order_id' => $order->id,
      'gateway' => 'paypal',
      'gateway_payment_id' => 'PAYPAL-CAPTURE-123',
      'status' => CommercePayment::STATUS_SUCCEEDED,
      'raw_event_id' => 'WH-EVENT-123',
    ]);
    $this->assertDatabaseHas('webblocks_commerce_webhook_events', [
      'gateway' => 'paypal',
      'event_id' => 'WH-EVENT-123',
      'event_type' => 'CHECKOUT.ORDER.APPROVED',
      'status' => CommerceWebhookEvent::STATUS_PROCESSED,
    ]);

    $second = $this->withHeaders($this->paypalWebhookHeaders())->postJson('/commerce/webhooks/paypal', $payload);
    $second->assertOk();
    $second->assertJson([
      'status' => CommerceWebhookEvent::STATUS_PROCESSED,
      'event_id' => 'WH-EVENT-123',
    ]);

    $this->assertSame(1, CommerceWebhookEvent::query()->where('event_id', 'WH-EVENT-123')->count());
    $this->assertSame(1, CommercePayment::query()->where('order_id', $order->id)->count());
  }

  #[Test]
  public function paypal_webhook_rejects_invalid_signature_without_marking_order_paid(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    config()->set('webblocks-commerce.gateway', 'paypal');
    config()->set('webblocks-commerce.paypal.mode', 'sandbox');
    config()->set('webblocks-commerce.paypal.client_id', 'paypal-client-id');
    config()->set('webblocks-commerce.paypal.client_secret', 'paypal-client-secret');
    config()->set('webblocks-commerce.paypal.webhook_id', 'paypal-webhook-id');
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    $this->migrateWebBlocksCommercePlugin();

    Http::fake([
      'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
        'access_token' => 'paypal-access-token',
      ]),
      'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
        'verification_status' => 'FAILURE',
      ]),
    ]);

    $order = CommerceOrder::query()->create([
      'order_number' => 'WB-1004',
      'status' => CommerceOrder::STATUS_PENDING,
      'subtotal_amount' => 125000,
      'total_amount' => 125000,
      'currency' => 'USD',
      'gateway' => 'paypal',
      'gateway_checkout_id' => 'PAYPAL-ORDER-INVALID',
    ]);

    $response = $this->withHeaders($this->paypalWebhookHeaders())->postJson('/commerce/webhooks/paypal', [
      'id' => 'WH-EVENT-INVALID',
      'event_type' => 'CHECKOUT.ORDER.APPROVED',
      'resource' => [
        'id' => 'PAYPAL-ORDER-INVALID',
      ],
    ]);

    $response->assertStatus(400);
    $response->assertJson(['status' => 'invalid_signature']);
    $this->assertSame(CommerceOrder::STATUS_PENDING, $order->fresh()->status);
    $this->assertSame(0, CommerceWebhookEvent::query()->count());
  }

  #[Test]
  public function public_buy_page_does_not_start_checkout_without_supported_gateway(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    config()->set('webblocks-commerce.gateway', 'paypal');
    config()->set('webblocks-commerce.paypal.client_id', null);
    config()->set('webblocks-commerce.paypal.client_secret', null);
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    $this->migrateWebBlocksCommercePlugin();

    $product = CommerceProduct::query()->create([
      'title' => 'Original Painting',
      'slug' => 'original-painting',
      'status' => CommerceProduct::STATUS_ACTIVE,
      'price_amount' => 125000,
      'currency' => 'USD',
    ]);

    $buy = $this->get(route('webblocks.commerce.products.buy', $product->slug));
    $buy->assertOk();
    $buy->assertSee('Checkout not ready');

    $checkout = $this->post(route('webblocks.commerce.products.checkout', $product->slug));
    $checkout->assertRedirect(route('webblocks.commerce.products.buy', $product->slug));
    $this->assertSame(0, CommerceOrder::query()->count());
  }

  /**
   * @return array<string, string>
   */
  #[Test]
  public function plugin_menu_labels_and_group_are_localized_in_the_admin_sidebar(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);
    config()->set('app.locale', 'tr');
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
    $this->migrateWebBlocksCommercePlugin();

    $response = $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.dashboard'));

    $response->assertOk();
    // Plugin menu item + group labels resolve through the plugin's Turkish catalog.
    $response->assertSee('Ürünler', false);
    $response->assertSee('Siparişler', false);
    $response->assertSee('Ticaret', false);
    // The English literals must not leak into a Turkish admin panel.
    $response->assertDontSee('Commerce Products');
    $response->assertDontSee('Commerce Orders');
  }

  private function paypalWebhookHeaders(): array
  {
    return [
      'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
      'PAYPAL-CERT-URL' => 'https://api-m.sandbox.paypal.com/certs/test',
      'PAYPAL-TRANSMISSION-ID' => 'transmission-id',
      'PAYPAL-TRANSMISSION-SIG' => 'signature',
      'PAYPAL-TRANSMISSION-TIME' => '2026-07-02T12:00:00Z',
    ];
  }

  private function migrateWebBlocksCommercePlugin(): void
  {
    Artisan::call('migrate', [
      '--path' => 'plugins/webblocks-commerce/database/migrations',
      '--realpath' => false,
    ]);
  }

  private function createInternalApiToken(string $token, ?array $capabilities = null): void
  {
    CmsApiToken::query()->create([
      'name' => 'Test token',
      'token_hash' => app(CmsApiTokenIssuer::class)->hash($token),
      'token_preview' => app(CmsApiTokenIssuer::class)->preview($token),
      'capabilities' => $capabilities,
    ]);
  }

  private function withInternalToken(): self
  {
    return $this->withHeader('Authorization', 'Bearer secret-token');
  }

  private function commercePluginZipPath(): string
  {
    $source = base_path('plugins/webblocks-commerce');
    $target = storage_path('framework/testing/plugin-zips/webblocks-commerce-'.str()->uuid().'.zip');
    File::ensureDirectoryExists(dirname($target));

    $zip = new ZipArchive;
    $this->assertTrue($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE));

    $files = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($files as $file) {
      $path = $file->getPathname();
      $relative = str_replace('\\', '/', substr($path, strlen($source) + 1));

      if ($file->isDir()) {
        $zip->addEmptyDir($relative);

        continue;
      }

      $zip->addFile($path, $relative);
    }

    $zip->close();

    return $target;
  }

  private function commerceBuyButtonPlanPayload(int $productId): array
  {
    return [
      'plan' => [
        'site' => Site::query()->where('is_primary', true)->firstOrFail()->handle,
        'locale' => Locale::query()->where('is_default', true)->firstOrFail()->code,
        'layout' => 'default',
        'page' => [
          'title' => 'Works',
          'path' => '/works',
          'status' => 'draft',
        ],
        'slots' => [
          'main' => [
            [
              'type' => 'webblocks-commerce-buy-button',
              'settings' => [
                'commerce_product_id' => $productId,
                'label' => 'Buy This Work',
                'show_price' => true,
                'alignment' => 'center',
              ],
            ],
          ],
        ],
      ],
    ];
  }

  private function installWebBlocksCommercePluginForTest(): void
  {
    $root = storage_path('framework/testing/plugins/'.str()->uuid());
    config()->set('webblocks-plugins.install.root', $root);

    File::ensureDirectoryExists($root.'/webblocks-commerce/0.8.0');
    File::copyDirectory(base_path('plugins/webblocks-commerce'), $root.'/webblocks-commerce/0.8.0');

    $this->app->forgetInstance(PluginRegistry::class);
  }
}
