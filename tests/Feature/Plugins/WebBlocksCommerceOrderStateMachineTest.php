<?php

namespace Tests\Feature\Plugins;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Console\ExpireStalePendingOrders;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\CheckoutUnavailableException;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\StartCheckout;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Orders\InvalidOrderTransitionException;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Orders\OrderStateMachine;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

class WebBlocksCommerceOrderStateMachineTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    config()->set('webblocks-commerce.gateway', 'fake');

    // Install the plugin into an isolated root and enable it so the CMS plugin
    // loader require_once's the plugin's src/ (its classes are not autoloaded
    // through composer), then run the plugin migrations.
    $root = storage_path('framework/testing/plugins/'.str()->uuid());
    config()->set('webblocks-plugins.install.root', $root);
    config()->set('webblocks-plugins.enabled.webblocks-commerce', true);

    File::ensureDirectoryExists($root.'/webblocks-commerce/0.7.3');
    File::copyDirectory(base_path('plugins/webblocks-commerce'), $root.'/webblocks-commerce/0.7.3');

    $this->app->forgetInstance(PluginRegistry::class);
    app(PluginRegistry::class)->get('webblocks-commerce');

    Artisan::call('migrate', [
      '--path' => 'plugins/webblocks-commerce/database/migrations',
      '--realpath' => false,
    ]);

    // The plugin registers its commands during boot; enabling happens after boot
    // in tests, so register the expiry command explicitly for command coverage.
    Artisan::registerCommand(app(ExpireStalePendingOrders::class));
  }

  #[Test]
  public function checkout_reserves_and_decrements_tracked_inventory(): void
  {
    $product = $this->product(['inventory_quantity' => 3]);

    app(StartCheckout::class)->forProduct($product);

    $this->assertSame(2, $product->fresh()->inventory_quantity);

    $order = CommerceOrder::query()->with('items')->firstOrFail();
    $this->assertSame(CommerceOrder::STATUS_PENDING, $order->status);
    $this->assertTrue((bool) $order->items->first()->metadata['inventory_reserved']);
    $this->assertSame(1, $order->items->first()->metadata['reserved_quantity']);
  }

  #[Test]
  public function checkout_is_rejected_and_rolled_back_when_out_of_stock(): void
  {
    $product = $this->product(['inventory_quantity' => 0]);

    try {
      app(StartCheckout::class)->forProduct($product);
      $this->fail('Expected CheckoutUnavailableException.');
    } catch (CheckoutUnavailableException) {
      // expected
    }

    $this->assertSame(0, $product->fresh()->inventory_quantity);
    $this->assertSame(0, CommerceOrder::query()->count());
  }

  #[Test]
  public function stock_of_one_cannot_be_sold_twice(): void
  {
    $product = $this->product(['inventory_quantity' => 1]);

    app(StartCheckout::class)->forProduct($product);
    $this->assertSame(0, $product->fresh()->inventory_quantity);

    $this->expectException(CheckoutUnavailableException::class);

    try {
      app(StartCheckout::class)->forProduct($product->fresh());
    } finally {
      // Exactly one order was created; the second attempt left nothing behind.
      $this->assertSame(1, CommerceOrder::query()->count());
    }
  }

  #[Test]
  public function untracked_product_checkout_never_touches_inventory(): void
  {
    $product = $this->product(['inventory_quantity' => null]);

    app(StartCheckout::class)->forProduct($product);
    app(StartCheckout::class)->forProduct($product->fresh());

    $this->assertNull($product->fresh()->inventory_quantity);
    $this->assertSame(2, CommerceOrder::query()->count());
  }

  #[Test]
  public function cancelling_a_pending_order_releases_reserved_stock(): void
  {
    $product = $this->product(['inventory_quantity' => 2]);
    app(StartCheckout::class)->forProduct($product);
    $this->assertSame(1, $product->fresh()->inventory_quantity);

    $order = CommerceOrder::query()->firstOrFail();
    app(OrderStateMachine::class)->cancel($order);

    $this->assertSame(CommerceOrder::STATUS_CANCELLED, $order->fresh()->status);
    $this->assertNotNull($order->fresh()->cancelled_at);
    $this->assertSame(2, $product->fresh()->inventory_quantity, 'Cancelled order should restock.');
  }

  #[Test]
  public function releasing_stock_is_idempotent(): void
  {
    $product = $this->product(['inventory_quantity' => 2]);
    app(StartCheckout::class)->forProduct($product);

    $order = CommerceOrder::query()->firstOrFail();
    app(OrderStateMachine::class)->cancel($order);
    // Second cancel is a no-op transition and must not restock again.
    app(OrderStateMachine::class)->cancel($order->fresh());

    $this->assertSame(2, $product->fresh()->inventory_quantity);
  }

  #[Test]
  public function marking_an_order_paid_keeps_stock_consumed(): void
  {
    $product = $this->product(['inventory_quantity' => 2]);
    app(StartCheckout::class)->forProduct($product);
    $order = CommerceOrder::query()->firstOrFail();

    app(OrderStateMachine::class)->markPaid($order, ['customer_email' => 'buyer@example.test']);

    $this->assertSame(CommerceOrder::STATUS_PAID, $order->fresh()->status);
    $this->assertNotNull($order->fresh()->paid_at);
    $this->assertSame('buyer@example.test', $order->fresh()->customer_email);
    $this->assertSame(1, $product->fresh()->inventory_quantity, 'Paid order must not restock.');
  }

  #[Test]
  public function mark_paid_is_idempotent_for_redelivered_webhooks(): void
  {
    $order = $this->pendingOrder();
    $machine = app(OrderStateMachine::class);

    $machine->markPaid($order);
    $paidAt = $order->fresh()->paid_at;

    $machine->markPaid($order->fresh());

    $this->assertSame(CommerceOrder::STATUS_PAID, $order->fresh()->status);
    $this->assertEquals($paidAt, $order->fresh()->paid_at, 'Re-delivery must not move paid_at.');
  }

  #[Test]
  public function illegal_transition_is_rejected(): void
  {
    $order = $this->pendingOrder();
    $machine = app(OrderStateMachine::class);
    $machine->markPaid($order);

    // paid -> failed is not in the transition graph.
    $this->expectException(InvalidOrderTransitionException::class);
    $machine->markFailed($order->fresh());
  }

  #[Test]
  public function paid_order_can_be_refunded_and_restocked(): void
  {
    $product = $this->product(['inventory_quantity' => 2]);
    app(StartCheckout::class)->forProduct($product);
    $order = CommerceOrder::query()->firstOrFail();

    $machine = app(OrderStateMachine::class);
    $machine->markPaid($order);
    $this->assertSame(1, $product->fresh()->inventory_quantity);

    $machine->refund($order->fresh());

    $this->assertSame(CommerceOrder::STATUS_REFUNDED, $order->fresh()->status);
    $this->assertSame(2, $product->fresh()->inventory_quantity, 'Refund should restock.');
  }

  #[Test]
  public function expiry_command_expires_stale_pending_orders_and_releases_stock(): void
  {
    $product = $this->product(['inventory_quantity' => 3]);
    app(StartCheckout::class)->forProduct($product);
    $this->assertSame(2, $product->fresh()->inventory_quantity);

    // Backdate the order so it is older than the expiry window.
    $order = CommerceOrder::query()->firstOrFail();
    $order->forceFill(['created_at' => now()->subHour()])->save();

    Artisan::call('webblocks-commerce:expire-stale-orders', ['--minutes' => 30]);

    $this->assertSame(CommerceOrder::STATUS_EXPIRED, $order->fresh()->status);
    $this->assertSame(3, $product->fresh()->inventory_quantity, 'Expired order should restock.');
  }

  #[Test]
  public function expiry_command_leaves_fresh_pending_orders_untouched(): void
  {
    $product = $this->product(['inventory_quantity' => 3]);
    app(StartCheckout::class)->forProduct($product);

    Artisan::call('webblocks-commerce:expire-stale-orders', ['--minutes' => 30]);

    $order = CommerceOrder::query()->firstOrFail();
    $this->assertSame(CommerceOrder::STATUS_PENDING, $order->status);
    $this->assertSame(2, $product->fresh()->inventory_quantity, 'Fresh order keeps its reservation.');
  }

  #[Test]
  public function checkout_snapshots_inclusive_vat_breakdown(): void
  {
    config()->set('webblocks-commerce.tax.enabled', true);
    config()->set('webblocks-commerce.tax.prices_include_tax', true);
    config()->set('webblocks-commerce.tax.store_country', 'DE');

    $product = $this->product(['price_amount' => 125000]); // standard class by default
    app(StartCheckout::class)->forProduct($product);

    $order = CommerceOrder::query()->with('items')->firstOrFail();
    // 19% VAT extracted from a gross 125000: net 105042 + tax 19958 = 125000.
    $this->assertSame(105042, $order->subtotal_amount);
    $this->assertSame(19958, $order->tax_amount);
    $this->assertSame(125000, $order->total_amount);
    $this->assertSame(1900, $order->tax_rate);
    $this->assertSame('DE', $order->tax_country);
    $this->assertTrue($order->prices_include_tax);

    $item = $order->items->first();
    $this->assertSame(19958, $item->tax_amount);
    $this->assertSame(1900, $item->tax_rate);
    $this->assertSame('standard', $item->tax_class);
  }

  #[Test]
  public function reduced_tax_class_uses_the_reduced_rate(): void
  {
    config()->set('webblocks-commerce.tax.store_country', 'DE');

    $product = $this->product(['price_amount' => 125000, 'tax_class' => 'reduced']);
    app(StartCheckout::class)->forProduct($product);

    $order = CommerceOrder::query()->firstOrFail();
    // 7% VAT extracted from 125000: net 116822 + tax 8178.
    $this->assertSame(700, $order->tax_rate);
    $this->assertSame(116822, $order->subtotal_amount);
    $this->assertSame(8178, $order->tax_amount);
    $this->assertSame(125000, $order->total_amount);
  }

  #[Test]
  public function zero_tax_class_records_no_vat(): void
  {
    $product = $this->product(['price_amount' => 125000, 'tax_class' => 'zero']);
    app(StartCheckout::class)->forProduct($product);

    $order = CommerceOrder::query()->firstOrFail();
    $this->assertSame(0, $order->tax_rate);
    $this->assertSame(0, $order->tax_amount);
    $this->assertSame(125000, $order->subtotal_amount);
    $this->assertSame(125000, $order->total_amount);
  }

  #[Test]
  public function exclusive_pricing_adds_vat_on_top(): void
  {
    config()->set('webblocks-commerce.tax.prices_include_tax', false);
    config()->set('webblocks-commerce.tax.store_country', 'DE');

    $product = $this->product(['price_amount' => 125000]);
    app(StartCheckout::class)->forProduct($product);

    $order = CommerceOrder::query()->firstOrFail();
    // Net 125000 + 19% = gross 148750; the buyer is charged the gross.
    $this->assertSame(125000, $order->subtotal_amount);
    $this->assertSame(23750, $order->tax_amount);
    $this->assertSame(148750, $order->total_amount);
    $this->assertFalse($order->prices_include_tax);
    // The payment is initiated for the gross amount the buyer actually pays.
    $this->assertSame(148750, $order->payments()->first()->amount);
  }

  #[Test]
  public function disabled_tax_leaves_amounts_untouched(): void
  {
    config()->set('webblocks-commerce.tax.enabled', false);

    $product = $this->product(['price_amount' => 125000]);
    app(StartCheckout::class)->forProduct($product);

    $order = CommerceOrder::query()->firstOrFail();
    $this->assertSame(0, $order->tax_amount);
    $this->assertSame(0, $order->tax_rate);
    $this->assertSame(125000, $order->subtotal_amount);
    $this->assertSame(125000, $order->total_amount);
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
      'inventory_quantity' => 1,
    ], $attributes));
  }

  private function pendingOrder(): CommerceOrder
  {
    $order = CommerceOrder::query()->create([
      'order_number' => 'WB-'.uniqid(),
      'status' => CommerceOrder::STATUS_PENDING,
      'subtotal_amount' => 125000,
      'total_amount' => 125000,
      'currency' => 'USD',
      'gateway' => 'fake',
      'placed_at' => now(),
    ]);

    $order->items()->create([
      'title' => 'Original Painting',
      'quantity' => 1,
      'unit_amount' => 125000,
      'total_amount' => 125000,
      'currency' => 'USD',
    ]);

    return $order;
  }
}
