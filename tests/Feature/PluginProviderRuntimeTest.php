<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginProviderRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRouteRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginRuntimeRegistrar;
use WebBlocks\Cms\Tests\TestCase;

class PluginProviderRuntimeTest extends TestCase
{
  public function test_runtime_registers_and_boots_enabled_providers_once_with_cached_routes(): void
  {
    $registry = new PluginRegistry(['runtime-example' => true]);
    $registry->register(PluginDefinition::make('runtime-example')->provider(RuntimeNotificationProvider::class));
    $this->app->instance(PluginRegistry::class, $registry);
    $this->app->instance('runtime-provider-events', []);
    $this->app->instance('routes.cached', true);
    $this->assertTrue($this->app->routesAreCached());
    app(PluginRuntimeRegistrar::class)->register();
    app(PluginRuntimeRegistrar::class)->register();
    $this->assertSame(['register', 'boot'], app('runtime-provider-events'));
    $this->assertSame('ready', app('runtime-notification-channel'));
  }

  public function test_provider_boot_is_deferred_until_the_host_finishes_booting(): void
  {
    $host = new Application(sys_get_temp_dir());
    $host->instance('config', new Repository);
    $host->instance('runtime-provider-events', []);
    $registry = new PluginRegistry(['runtime-example' => true], respectRecoveryMode: false);
    $registry->register(PluginDefinition::make('runtime-example')->provider(RuntimeNotificationProvider::class));
    $host->instance(PluginRegistry::class, $registry);
    $host->instance('routes.cached', true);
    $host->instance('router', $this->app['router']);
    $admin = $this->createMock(PluginRouteRegistrar::class);
    $admin->expects($this->once())->method('finishRegistration')->willReturnCallback(function () use ($host): void {
      $host->instance('runtime-provider-events', [...$host['runtime-provider-events'], 'routes']);
    });
    $host->instance(PluginRouteRegistrar::class, $admin);
    Container::setInstance($host);
    try {
      app(PluginRuntimeRegistrar::class)->register();
      $this->assertNull($host->getProvider(RuntimeNotificationProvider::class));
      $host->boot();
      $this->assertSame(['register', 'boot', 'routes'], $host['runtime-provider-events']);
    } finally {
      Container::setInstance($this->app);
    }
  }

  public function test_recovery_and_incompatible_providers_do_not_start(): void
  {
    $registry = new PluginRegistry(['runtime-example' => true]);
    $registry->register(PluginDefinition::make('runtime-example')->provider(RuntimeInactiveProvider::class)->requiresCms('>=999.0.0'));
    $this->app->instance(PluginRegistry::class, $registry);
    app(PluginProviderRegistrar::class)->register();
    $this->assertNull($this->app->getProvider(RuntimeInactiveProvider::class));
    $registry = new PluginRegistry(['runtime-example' => true]);
    $registry->register(PluginDefinition::make('runtime-example')->provider(RuntimeInactiveProvider::class));
    $this->app->instance(PluginRegistry::class, $registry);
    $this->app->instance('request', Request::create('/webadmin/plugin-recovery'));
    app(PluginProviderRegistrar::class)->register();
    $this->assertNull($this->app->getProvider(RuntimeInactiveProvider::class));
  }

  public function test_inactive_providers_are_not_started_and_a_broken_provider_is_quarantined(): void
  {
    $registry = new PluginRegistry(['runtime-example' => false, 'runtime-broken' => true, 'runtime-healthy' => true]);
    $registry->register(PluginDefinition::make('runtime-example')->provider(RuntimeInactiveProvider::class));
    $registry->register(PluginDefinition::make('runtime-broken')->provider(RuntimeBrokenProvider::class)->publicRoutes(function (): void {
      app()->instance('broken-provider-routes-loaded', true);
    }));
    $registry->register(PluginDefinition::make('runtime-healthy')->provider(RuntimeNotificationProvider::class));
    $this->app->instance(PluginRegistry::class, $registry);
    $this->app->instance('runtime-provider-events', []);
    app(PluginRuntimeRegistrar::class)->register();
    $this->assertFalse($this->app->bound('broken-provider-routes-loaded'));
    $this->assertNull($this->app->getProvider(RuntimeInactiveProvider::class));
    $this->assertFalse($registry->isActive('runtime-broken'));
    $this->assertTrue($registry->isActive('runtime-healthy'));
    $this->assertSame(['register', 'boot'], app('runtime-provider-events'));
  }
}

class RuntimeNotificationProvider extends ServiceProvider
{
  public function register(): void
  {
    $this->app->instance('runtime-provider-events', [...$this->app['runtime-provider-events'], 'register']);
    $this->app->instance('runtime-notification-channel', 'ready');
  }

  public function boot(): void
  {
    $this->app->instance('runtime-provider-events', [...$this->app['runtime-provider-events'], 'boot']);
  }
}

class RuntimeInactiveProvider extends ServiceProvider
{
  public function boot(): void
  {
    throw new RuntimeException('An inactive plugin must not boot.');
  }
}

class RuntimeBrokenProvider extends ServiceProvider
{
  public function boot(): void
  {
    throw new RuntimeException('Synthetic private startup diagnostic');
  }
}
