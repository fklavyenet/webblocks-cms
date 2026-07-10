<?php

namespace Tests\Feature\Plugins;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceCart;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProductTranslation;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Cart\CartService;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\StartCheckout;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\I18n\ProductLocalizer;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\Plugins\PluginApiRouteRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

class WebBlocksCommerceI18nTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    config()->set('webblocks-commerce.gateway', 'fake');

    $root = storage_path('framework/testing/plugins/'.str()->uuid());
    config()->set('webblocks-plugins.install.root', $root);
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);

    File::ensureDirectoryExists($root.'/webblocks-commerce/0.7.0');
    File::copyDirectory(base_path('plugins/webblocks-commerce'), $root.'/webblocks-commerce/0.7.0');

    $this->app->forgetInstance(PluginRegistry::class);
    app(PluginRegistry::class)->get('webblocks-commerce');

    Artisan::call('migrate', [
      '--path' => 'plugins/webblocks-commerce/database/migrations',
      '--realpath' => false,
    ]);

    app(PluginApiRouteRegistrar::class)->registerEnabledApiRoutes();
  }

  #[Test]
  public function localizer_returns_the_translation_and_falls_back_to_base(): void
  {
    $product = $this->product(['title' => 'Painting', 'description' => 'Base description']);
    $this->translate($product, 'de', 'Gemälde', 'Deutsche Beschreibung');

    $localizer = app(ProductLocalizer::class);

    $this->assertSame('Gemälde', $localizer->title($product->fresh(), 'de'));
    $this->assertSame('Painting', $localizer->title($product->fresh(), 'fr'), 'Missing locale falls back to base.');
    $this->assertSame('Painting', $localizer->title($product->fresh(), null), 'Null locale is the base.');
  }

  #[Test]
  public function empty_translation_fields_fall_back_to_base(): void
  {
    $product = $this->product(['title' => 'Painting', 'description' => 'Base description']);
    // Title provided, description left blank -> description falls back.
    $this->translate($product, 'de', 'Gemälde', null);

    $localized = app(ProductLocalizer::class)->localize($product->fresh(), 'de');

    $this->assertSame('Gemälde', $localized['title']);
    $this->assertSame('Base description', $localized['description']);
  }

  #[Test]
  public function cart_summary_shows_the_localized_title(): void
  {
    $product = $this->product(['title' => 'Painting']);
    $this->translate($product, 'de', 'Gemälde');

    $carts = app(CartService::class);
    $cart = $carts->create(locale: 'de');
    $carts->addProduct($cart, $product, 1);

    $this->assertSame('Gemälde', $carts->summary($cart->fresh())['items'][0]['title']);
  }

  #[Test]
  public function checkout_snapshots_the_localized_title_on_the_order_line(): void
  {
    $product = $this->product(['title' => 'Painting']);
    $this->translate($product, 'de', 'Gemälde');

    $carts = app(CartService::class);
    $cart = $carts->create(locale: 'de');
    $carts->addProduct($cart, $product, 1);

    app(StartCheckout::class)->forCart($cart);

    $order = CommerceOrder::query()->with('items')->firstOrFail();
    $this->assertSame('Gemälde', $order->items->first()->title, 'Order line freezes the localized title.');
  }

  #[Test]
  public function the_buy_page_localizes_via_the_locale_query(): void
  {
    $this->migratePluginRoutes();
    $product = $this->product(['title' => 'Painting', 'status' => CommerceProduct::STATUS_ACTIVE]);
    $this->translate($product, 'de', 'Gemälde');

    $this->get(route('webblocks.commerce.products.buy', ['product' => $product->slug, 'locale' => 'de']))
      ->assertOk()
      ->assertSee('Gemälde');
  }

  #[Test]
  public function the_translation_api_upserts_lists_and_deletes(): void
  {
    $this->createToken('i18n-token', [
      'commerce.read',
      'commerce.products.write',
    ]);
    Locale::query()->create(['code' => 'de', 'name' => 'German']);
    $product = $this->product(['title' => 'Painting']);

    $upsert = $this->bearer('i18n-token')->putJson(
      "/webadmin/api/commerce/products/{$product->id}/translations/de",
      ['title' => 'Gemälde', 'description' => 'Deutsch']
    );
    $upsert->assertOk();
    $upsert->assertJsonPath('translation.title', 'Gemälde');
    $upsert->assertJsonPath('translation.locale', 'de');

    $index = $this->bearer('i18n-token')->getJson("/webadmin/api/commerce/products/{$product->id}/translations");
    $index->assertOk();
    $index->assertJsonPath('base.title', 'Painting');
    $index->assertJsonPath('translations.0.title', 'Gemälde');

    // The storefront now reflects the API-managed translation.
    $this->assertSame('Gemälde', app(ProductLocalizer::class)->title($product->fresh(), 'de'));

    $this->bearer('i18n-token')
      ->deleteJson("/webadmin/api/commerce/products/{$product->id}/translations/de")
      ->assertOk();

    $this->assertSame(0, CommerceProductTranslation::query()->count());
  }

  #[Test]
  public function the_translation_api_requires_the_product_write_capability(): void
  {
    $this->createToken('read-only', ['commerce.read']);
    Locale::query()->create(['code' => 'de', 'name' => 'German']);
    $product = $this->product();

    $this->bearer('read-only')
      ->putJson("/webadmin/api/commerce/products/{$product->id}/translations/de", ['title' => 'Nope'])
      ->assertStatus(403);
  }

  #[Test]
  public function admin_can_save_translations_via_the_product_form(): void
  {
    $this->migratePluginRoutes();
    $de = Locale::query()->create(['code' => 'de', 'name' => 'German']);

    $this->actingAs(User::factory()->superAdmin()->create())
      ->post(route('webblocks.plugins.webblocks_commerce.products.store'), [
        'title' => 'Painting',
        'slug' => 'painting-x',
        'status' => CommerceProduct::STATUS_ACTIVE,
        'price_amount' => 125000,
        'currency' => 'USD',
        'tax_class' => 'standard',
        'translations' => [$de->id => ['title' => 'Gemälde', 'description' => 'Deutsch']],
      ])
      ->assertRedirect();

    $product = CommerceProduct::query()->firstOrFail();
    $this->assertSame('Gemälde', app(ProductLocalizer::class)->title($product, 'de'));
  }

  #[Test]
  public function admin_editing_removes_a_translation_left_blank(): void
  {
    $this->migratePluginRoutes();
    $de = Locale::query()->create(['code' => 'de', 'name' => 'German']);
    $product = $this->product(['title' => 'Painting']);
    $this->translate($product, 'de', 'Gemälde');

    $this->actingAs(User::factory()->superAdmin()->create())
      ->put(route('webblocks.plugins.webblocks_commerce.products.update', $product), [
        'title' => 'Painting',
        'slug' => $product->slug,
        'status' => CommerceProduct::STATUS_ACTIVE,
        'price_amount' => 125000,
        'currency' => 'USD',
        'tax_class' => 'standard',
        'translations' => [$de->id => ['title' => '', 'description' => '']],
      ])
      ->assertRedirect();

    $this->assertSame(0, CommerceProductTranslation::query()->count());
  }

  #[Test]
  public function the_edit_form_shows_existing_translations(): void
  {
    $this->migratePluginRoutes();
    Locale::query()->create(['code' => 'de', 'name' => 'German']);
    $product = $this->product();
    $this->translate($product, 'de', 'Gemälde');

    $this->actingAs(User::factory()->superAdmin()->create())
      ->get(route('webblocks.plugins.webblocks_commerce.products.edit', $product))
      ->assertOk()
      ->assertSee('Gemälde')
      ->assertSee('German');
  }

  private function migratePluginRoutes(): void
  {
    app(\WebBlocks\Cms\Support\Plugins\PluginRouteRegistrar::class)->registerEnabledAdminRoutes();
  }

  private function translate(CommerceProduct $product, string $code, ?string $title, ?string $description = null): void
  {
    $locale = Locale::query()->firstOrCreate(['code' => $code], ['name' => strtoupper($code)]);

    CommerceProductTranslation::query()->create([
      'product_id' => $product->id,
      'locale_id' => $locale->id,
      'title' => $title,
      'description' => $description,
    ]);
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
      'name' => 'i18n test token',
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
