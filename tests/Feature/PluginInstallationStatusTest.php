<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ViewErrorBag;
use WebBlocks\Cms\Http\Controllers\Admin\SystemPluginController;
use WebBlocks\Cms\Queries\SystemHealthQuery;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Tests\TestCase;

class PluginInstallationStatusTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('webblocks-cms.routes.admin', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
    }

    public function test_plugin_administration_and_system_overview_do_not_execute_reporters(): void
    {
        $calls = 0;
        $registry = new PluginRegistry(['status-example' => true]);
        $registry->register(PluginDefinition::make('status-example')->label('Synthetic plugin')->version('1.0.0')
            ->health(function () use (&$calls) {
                $calls++;
                throw new \RuntimeException('A feature reporter must not run on these screens.');
            }));
        $this->app->instance(PluginRegistry::class, $registry);
        Http::preventStrayRequests();
        $controller = app(SystemPluginController::class);
        $index = $controller->index(Request::create('/webadmin/system/plugins'))->getData();
        $this->assertCount(1, $index['plugins']);
        $this->assertArrayNotHasKey('health', $index['plugins'][0]);
        $details = $controller->show('status-example')->getData()['plugin'];
        $this->assertArrayNotHasKey('health', $details);
        $this->assertTrue($details['enabled']);
        $checks = app(SystemHealthQuery::class)->report()['categories']['plugins']['checks'];
        $this->assertSame('not_applicable', $checks[0]['status']);
        $this->assertSame(0, $calls);
        Http::assertNothingSent();
    }

    public function test_incompatible_installations_remain_actionable_without_a_reporter(): void
    {
        $registry = new PluginRegistry(['status-example' => true]);
        $registry->register(PluginDefinition::make('status-example')->label('Synthetic plugin')->version('1.0.0')->requiresCms('>=99.0.0'));
        $this->app->instance(PluginRegistry::class, $registry);
        $checks = app(SystemHealthQuery::class)->report()['categories']['plugins']['checks'];
        $this->assertSame('critical', $checks[0]['status']);
        $this->assertSame('admin.system.plugins.show', $checks[0]['route']);
    }

    public function test_rendered_plugin_list_and_details_keep_status_and_remove_health_controls(): void
    {
        $registry = new PluginRegistry(['status-example' => true]);
        $registry->register(PluginDefinition::make('status-example')->label('Synthetic plugin')->version('1.0.0')
            ->health(fn () => throw new \RuntimeException('Must not run.')));
        $this->app->instance(PluginRegistry::class, $registry);
        $this->withoutMiddleware();
        view()->share('errors', new ViewErrorBag);
        Http::preventStrayRequests();
        $this->get(route('admin.system.plugins.index'))->assertOk()
            ->assertSee('Synthetic plugin')->assertSee('Enabled')->assertSee('1.0.0')
            ->assertDontSee('wb-plugins-cell-health', false)->assertDontSee('plugin-health-status-example', false);
        $this->get(route('admin.system.plugins.show', 'status-example'))->assertOk()
            ->assertSee('Synthetic plugin')->assertSee('Enabled')->assertDontSee('No health details');
        Http::assertNothingSent();
    }
}
