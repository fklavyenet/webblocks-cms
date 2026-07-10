<?php

namespace Tests\Feature\Plugins;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceCart;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Cart\CartException;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Cart\CartService;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\StartCheckout;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\Plugins\PluginApiCapabilityRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginApiRouteRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRouteRegistrar;

class WebBlocksCommerceCartTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    config()->set('webblocks-commerce.gateway', 'fake');

    $root = storage_path('framework/testing/plugins/'.str()->uuid());
    config()->set('webblocks-plugins.install.root', $root);
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);

    File::ensureDirectoryExists($root.'/webblocks-commerce/0.7.2');
    File::copyDirectory(base_path('plugins/webblocks-commerce'), $root.'/webblocks-commerce/0.7.2');

    $this->app->forgetInstance(PluginRegistry::class);
    app(PluginRegistry::class)->get('webblocks-commerce');

    Artisan::call('migrate', [
      '--path' => 'plugins/webblocks-commerce/database/migrations',
      '--realpath' => false,
    ]);

    // Enabling happens after boot in tests, so mount the plugin's API routes now.
    app(PluginApiRouteRegistrar::class)->registerEnabledApiRoutes();
  }

  // ---- Domain: CartService --------------------------------------------------

  #[Test]
  public function adding_the_same_product_twice_merges_quantity(): void
  {
    $product = $this->product();
    $carts = app(CartService::class);
    $cart = $carts->create();

    $carts->addProduct($cart, $product, 1);
    $carts->addProduct($cart, $product, 2);

    $this->assertSame(1, $cart->items()->count());
    $this->assertSame(3, $cart->items()->first()->quantity);
    $this->assertSame('USD', $cart->fresh()->currency);
  }

  #[Test]
  public function it_rejects_mixing_currencies_in_one_cart(): void
  {
    $carts = app(CartService::class);
    $cart = $carts->create();

    $carts->addProduct($cart, $this->product(['currency' => 'USD']), 1);

    $this->expectException(CartException::class);
    $carts->addProduct($cart, $this->product(['currency' => 'EUR']), 1);
  }

  #[Test]
  public function it_rejects_adding_more_than_tracked_stock(): void
  {
    $carts = app(CartService::class);
    $cart = $carts->create();
    $product = $this->product(['inventory_quantity' => 2]);

    $this->expectException(CartException::class);
    $carts->addProduct($cart, $product, 3);
  }

  #[Test]
  public function setting_quantity_to_zero_removes_the_line(): void
  {
    $carts = app(CartService::class);
    $cart = $carts->create();
    $product = $this->product();
    $carts->addProduct($cart, $product, 2);

    $carts->setQuantity($cart, $product, 0);

    $this->assertSame(0, $cart->items()->count());
    $this->assertNull($cart->fresh()->currency, 'Empty cart resets currency so a new-currency product can be added.');
  }

  #[Test]
  public function summary_computes_live_tax_inclusive_totals(): void
  {
    config()->set('webblocks-commerce.tax.store_country', 'DE');
    config()->set('webblocks-commerce.tax.prices_include_tax', true);

    $carts = app(CartService::class);
    $cart = $carts->create();
    $carts->addProduct($cart, $this->product(['price_amount' => 100000]), 2); // 2 x 1000.00

    $summary = $carts->summary($cart);

    // 19% inclusive on gross 200000: net 168067 + tax 31933 = 200000.
    $this->assertSame(200000, $summary['total_amount']);
    $this->assertSame(31933, $summary['tax_amount']);
    $this->assertSame(168067, $summary['subtotal_amount']);
    $this->assertTrue($summary['items'][0]['available']);
    $this->assertSame(2, $summary['items'][0]['quantity']);
  }

  // ---- Domain: cart checkout ------------------------------------------------

  #[Test]
  public function cart_checkout_builds_a_multi_line_order_reserves_stock_and_converts_the_cart(): void
  {
    $carts = app(CartService::class);
    $cart = $carts->create(locale: 'de');
    $a = $this->product(['price_amount' => 100000, 'inventory_quantity' => 5]);
    $b = $this->product(['price_amount' => 50000, 'inventory_quantity' => 5]);
    $carts->addProduct($cart, $a, 2);
    $carts->addProduct($cart, $b, 1);

    app(StartCheckout::class)->forCart($cart);

    $order = CommerceOrder::query()->with('items')->firstOrFail();
    $this->assertSame(CommerceOrder::STATUS_PENDING, $order->status);
    $this->assertSame(2, $order->items->count());
    $this->assertSame(250000, $order->total_amount); // 2*1000 + 1*500 gross
    $this->assertSame('cart', $order->metadata['checkout_source']);
    $this->assertSame('de', $order->metadata['locale']);

    // Stock reserved for both lines.
    $this->assertSame(3, $a->fresh()->inventory_quantity);
    $this->assertSame(4, $b->fresh()->inventory_quantity);

    // Cart converted and linked to the order.
    $this->assertSame(CommerceCart::STATUS_CONVERTED, $cart->fresh()->status);
    $this->assertSame($order->id, $cart->fresh()->converted_order_id);
  }

  // ---- Plugin-owned API (end-to-end through the apiRoutes hook) -------------

  #[Test]
  public function the_cart_api_supports_the_full_flow_with_a_capable_token(): void
  {
    $this->createToken('cart-token', [
      'commerce.cart.read',
      'commerce.cart.write',
    ]);
    $product = $this->product(['price_amount' => 100000, 'inventory_quantity' => 5]);

    $create = $this->bearer('cart-token')->postJson('/webadmin/api/commerce/cart', []);
    $create->assertCreated();
    $token = $create->json('cart.token');
    $this->assertNotEmpty($token);

    $add = $this->bearer('cart-token')->postJson("/webadmin/api/commerce/cart/{$token}/items", [
      'product_id' => $product->id,
      'quantity' => 2,
    ]);
    $add->assertOk();
    $add->assertJsonPath('cart.total_amount', 200000);

    $show = $this->bearer('cart-token')->getJson("/webadmin/api/commerce/cart/{$token}");
    $show->assertOk();
    $show->assertJsonPath('cart.items.0.quantity', 2);

    $checkout = $this->bearer('cart-token')->postJson("/webadmin/api/commerce/cart/{$token}/checkout", [
      'customer_email' => 'buyer@example.test',
    ]);
    $checkout->assertCreated();
    $checkout->assertJsonPath('order.status', 'pending');
    $checkout->assertJsonPath('order.total_amount', 200000);
    $this->assertNotEmpty($checkout->json('redirect_url'));

    $this->assertSame(3, $product->fresh()->inventory_quantity);
  }

  #[Test]
  public function the_cart_api_rejects_a_token_without_the_cart_capability(): void
  {
    $this->createToken('read-only', ['commerce.read']);

    $this->bearer('read-only')
      ->postJson('/webadmin/api/commerce/cart', [])
      ->assertStatus(403)
      ->assertJsonPath('code', 'missing_internal_api_capability');
  }

  #[Test]
  public function the_cart_api_rejects_unauthenticated_requests(): void
  {
    $this->postJson('/webadmin/api/commerce/cart', [])->assertStatus(401);
  }

  // ---- Shared token: plugin-contributed capabilities -----------------------

  #[Test]
  public function commerce_capabilities_are_grantable_only_while_the_plugin_is_enabled(): void
  {
    $caps = app(CmsApiTokenCapabilities::class);
    $this->assertContains('commerce.cart.write', $caps->grantable());
    $this->assertContains('commerce.cart.write', $caps->advancedGrantable());
    $this->assertArrayHasKey('commerce.cart.write', $caps->labelsAll());

    $groupKeys = array_column(app(PluginApiCapabilityRegistrar::class)->groups(), 'key');
    $this->assertContains('commerce', $groupKeys);

    config()->set('webblocks-plugins.enabled.webblocks-commerce', false);
    $this->app->forgetInstance(PluginRegistry::class);

    $this->assertNotContains('commerce.cart.write', app(CmsApiTokenCapabilities::class)->grantable());
  }

  #[Test]
  public function the_token_screen_renders_the_commerce_permission_group_with_plugin_labels(): void
  {
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();

    $response = $this->actingAs(User::factory()->superAdmin()->create())
      ->get(route('admin.system.api-tokens.index'));

    $response->assertOk();
    // Group + capability labels come from the plugin's apiCapabilities(), not CMS lang keys.
    $response->assertSee('Commerce');
    $response->assertSee('Create and modify commerce carts and start checkout');
    $response->assertSee('commerce.cart.write');
    // The raw translation key must not leak through when a plugin label is used.
    $response->assertDontSee('groups.commerce.label');
    $response->assertDontSee('labels.commerce_cart_write');
  }

  #[Test]
  public function a_super_admin_can_mint_a_shared_token_with_a_commerce_capability(): void
  {
    app(PluginRouteRegistrar::class)->registerEnabledAdminRoutes();

    $this->actingAs(User::factory()->superAdmin()->create())
      ->post(route('admin.system.api-tokens.store'), [
        'name' => 'AI Commerce Operator',
        'capabilities' => ['content.read', 'commerce.cart.write'],
      ])
      ->assertRedirect();

    $token = CmsApiToken::query()->where('name', 'AI Commerce Operator')->firstOrFail();
    $this->assertContains('commerce.cart.write', $token->capabilities);
  }

  // ---- Migrated product & order API (now plugin-owned) ---------------------

  #[Test]
  public function the_product_api_exposes_and_accepts_tax_class(): void
  {
    $this->createToken('product-token', [
      'commerce.read',
      'commerce.products.write',
    ]);

    $create = $this->bearer('product-token')->postJson('/webadmin/api/commerce/products', [
      'title' => 'Reduced Item',
      'slug' => 'reduced-item',
      'status' => CommerceProduct::STATUS_ACTIVE,
      'price_amount' => 100000,
      'currency' => 'EUR',
      'tax_class' => 'reduced',
    ]);
    $create->assertCreated();
    $create->assertJsonPath('product.tax_class', 'reduced');

    $this->bearer('product-token')->getJson('/webadmin/api/commerce/products')
      ->assertOk()
      ->assertJsonPath('products.0.tax_class', 'reduced');
  }

  #[Test]
  public function the_order_api_exposes_the_tax_breakdown(): void
  {
    config()->set('webblocks-commerce.tax.store_country', 'DE');
    config()->set('webblocks-commerce.tax.prices_include_tax', true);
    $this->createToken('order-token', ['commerce.orders.read']);

    $carts = app(CartService::class);
    $cart = $carts->create();
    $carts->addProduct($cart, $this->product(['price_amount' => 100000, 'tax_class' => 'standard']), 1);
    app(StartCheckout::class)->forCart($cart);
    $order = CommerceOrder::query()->firstOrFail();

    $this->bearer('order-token')->getJson("/webadmin/api/commerce/orders/{$order->id}")
      ->assertOk()
      ->assertJsonPath('order.tax_rate', 1900)
      ->assertJsonPath('order.tax_country', 'DE')
      ->assertJsonPath('order.prices_include_tax', true)
      ->assertJsonPath('order.items.0.tax_class', 'standard');
  }

  // ---- API discovery hook --------------------------------------------------

  #[Test]
  public function discovery_advertises_commerce_endpoints_while_the_plugin_is_enabled(): void
  {
    $this->createToken('discovery-token', ['commerce.read']);

    $discovery = $this->bearer('discovery-token')->getJson('/webadmin/api');
    $discovery->assertOk();
    $discovery->assertJsonPath('_links.commerce_products', '/webadmin/api/commerce/products');
    $discovery->assertJsonPath('_links.commerce_cart_create', '/webadmin/api/commerce/cart');
    $discovery->assertJsonPath('_links.commerce_cart_checkout', '/webadmin/api/commerce/cart/{cart}/checkout');

    $openapi = $this->bearer('discovery-token')->getJson('/webadmin/api/openapi.json');
    $openapi->assertOk();
    $this->assertArrayHasKey('/commerce/cart/{cart}/checkout', $openapi->json('paths'));
    $this->assertArrayHasKey('/commerce/products/{product}/translations/{locale}', $openapi->json('paths'));
  }

  #[Test]
  public function discovery_drops_commerce_endpoints_when_the_plugin_is_disabled(): void
  {
    $this->createToken('discovery-token', ['commerce.read']);

    config()->set('webblocks-plugins.enabled.webblocks-commerce', false);
    $this->app->forgetInstance(PluginRegistry::class);

    $discovery = $this->bearer('discovery-token')->getJson('/webadmin/api');
    $discovery->assertOk();
    $discovery->assertJsonMissingPath('_links.commerce_products');
    $discovery->assertJsonMissingPath('_links.commerce_cart_create');
  }

  /**
   * @param  array<string, mixed>  $attributes
   */
  private function product(array $attributes = []): CommerceProduct
  {
    return CommerceProduct::query()->create(array_merge([
      'title' => 'Original Painting',
      'slug' => 'original-painting-'.uniqid(),
      'status' => CommerceProduct::STATUS_ACTIVE,
      'price_amount' => 125000,
      'currency' => 'USD',
      'inventory_quantity' => 10,
    ], $attributes));
  }

  /**
   * @param  array<int, string>  $capabilities
   */
  private function createToken(string $token, array $capabilities): void
  {
    CmsApiToken::query()->create([
      'name' => 'Cart test token',
      'token_hash' => app(CmsApiTokenIssuer::class)->hash($token),
      'token_preview' => app(CmsApiTokenIssuer::class)->preview($token),
      'capabilities' => $capabilities,
    ]);
  }

  private function bearer(string $token): self
  {
    return $this->withHeader('Authorization', 'Bearer '.$token);
  }
}
