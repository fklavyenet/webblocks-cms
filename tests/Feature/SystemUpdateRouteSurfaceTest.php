<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

class SystemUpdateRouteSurfaceTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);

    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  #[Test]
  public function the_one_click_update_routes_are_registered(): void
  {
    foreach ([
      'admin.system.updates.index',
      'admin.system.updates.indicator',
      'admin.system.updates.check',
      'admin.system.updates.store',
    ] as $routeName) {
      $this->assertTrue(Route::has($routeName), 'Expected route '.$routeName.' to be registered.');
    }
  }

  #[Test]
  public function the_two_phase_and_support_report_routes_are_gone(): void
  {
    foreach ([
      'admin.system.updates.continue',
      'admin.system.updates.cancel',
      'admin.system.updates.support-report',
    ] as $routeName) {
      $this->assertFalse(Route::has($routeName), 'Route '.$routeName.' belongs to the retired two-phase flow and must not be registered.');
    }
  }
}
