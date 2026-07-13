<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

class CommercePublicBridgeTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);

    $app['config']->set('webblocks-cms.public.load_routes', true);
  }

  #[Test]
  public function package_registers_the_public_cart_and_sumup_bridge_routes(): void
  {
    $expectations = [
      'webblocks.commerce.cart.show' => ['GET', 'commerce/cart'],
      'webblocks.commerce.cart.items.add' => ['POST', 'commerce/cart/items/{product}'],
      'webblocks.commerce.cart.items.update' => ['PATCH', 'commerce/cart/items/{product}'],
      'webblocks.commerce.cart.items.remove' => ['DELETE', 'commerce/cart/items/{product}'],
      'webblocks.commerce.cart.checkout' => ['POST', 'commerce/cart/checkout'],
      'webblocks.commerce.webhooks.sumup' => ['POST', 'commerce/webhooks/sumup'],
    ];

    foreach ($expectations as $name => [$method, $uri]) {
      $route = $this->app['router']->getRoutes()->getByName($name);

      $this->assertInstanceOf(Route::class, $route, "Missing route [{$name}].");
      $this->assertSame($uri, $route->uri());
      $this->assertContains($method, $route->methods());
    }
  }

  #[Test]
  public function sumup_webhook_is_csrf_exempt_and_inert_without_the_plugin(): void
  {
    $this->post('/commerce/webhooks/sumup')->assertNotFound();
  }
}
