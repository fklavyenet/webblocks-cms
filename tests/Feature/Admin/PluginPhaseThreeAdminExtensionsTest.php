<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\Plugins\PluginDashboardWidget;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginSystemCard;
use WebBlocks\Cms\Support\System\InstalledVersionStore;

class PluginPhaseThreeAdminExtensionsTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    $this->seed(FoundationSiteLocaleSeeder::class);
    app(InstalledVersionStore::class)->persist('1.33.0');
  }

  #[Test]
  public function enabled_permitted_plugin_dashboard_widgets_render_with_plugin_attribution(): void
  {
    Gate::define('analytics-tools.view', fn (User $user): bool => $user->isSuperAdmin());

    $registry = new PluginRegistry(['analytics-tools' => true]);
    $registry->register(
      PluginDefinition::make('analytics-tools')
        ->label('Analytics Tools')
        ->dashboardWidgets([
          PluginDashboardWidget::make('analytics-tools.overview')
            ->title('Analytics Overview')
            ->description('Read-only plugin dashboard card.')
            ->value(12)
            ->permission('analytics-tools.view'),
        ])
    );
    $this->app->instance(PluginRegistry::class, $registry);

    $response = $this->actingAs(User::factory()->superAdmin()->create())
      ->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSee('Analytics Overview');
    $response->assertSee('Read-only plugin dashboard card.');
    $response->assertSee('data-plugin-dashboard-widget="analytics-tools.overview"', false);
    $response->assertSee('data-plugin-handle="analytics-tools"', false);
  }

  #[Test]
  public function disabled_plugin_dashboard_widgets_do_not_render(): void
  {
    $registry = new PluginRegistry(['analytics-tools' => false]);
    $registry->register(
      PluginDefinition::make('analytics-tools')
        ->label('Analytics Tools')
        ->dashboardWidgets([
          PluginDashboardWidget::make('analytics-tools.overview')->title('Analytics Overview'),
        ])
    );
    $this->app->instance(PluginRegistry::class, $registry);

    $response = $this->actingAs(User::factory()->superAdmin()->create())
      ->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertDontSee('Analytics Overview');
    $response->assertDontSee('data-plugin-dashboard-widget="analytics-tools.overview"', false);
  }

  #[Test]
  public function unpermitted_plugin_dashboard_widgets_do_not_render(): void
  {
    $registry = new PluginRegistry(['analytics-tools' => true]);
    $registry->register(
      PluginDefinition::make('analytics-tools')
        ->label('Analytics Tools')
        ->dashboardWidgets([
          PluginDashboardWidget::make('analytics-tools.overview')
            ->title('Analytics Overview')
            ->permission('analytics-tools.view'),
        ])
    );
    $this->app->instance(PluginRegistry::class, $registry);

    $response = $this->actingAs(User::factory()->superAdmin()->create())
      ->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertDontSee('Analytics Overview');
  }

  #[Test]
  public function enabled_permitted_plugin_system_cards_render_with_plugin_attribution(): void
  {
    Gate::define('analytics-tools.view', fn (User $user): bool => $user->isSuperAdmin());

    $registry = new PluginRegistry(['analytics-tools' => true]);
    $registry->register(
      PluginDefinition::make('analytics-tools')
        ->label('Analytics Tools')
        ->systemCards([
          PluginSystemCard::make('analytics-tools.status')
            ->title('Analytics System Status')
            ->description('Read-only plugin system card.')
            ->url('/webadmin/plugins/analytics-tools/status', 'Open Status')
            ->permission('analytics-tools.view'),
        ])
    );
    $this->app->instance(PluginRegistry::class, $registry);

    $response = $this->actingAs(User::factory()->superAdmin()->create())
      ->get(route('admin.system.plugins.index'));

    $response->assertOk();
    $response->assertSee('Analytics System Status');
    $response->assertSee('Read-only plugin system card.');
    $response->assertSee('data-plugin-system-card="analytics-tools.status"', false);
    $response->assertSee('data-plugin-handle="analytics-tools"', false);
  }

  #[Test]
  public function disabled_plugin_system_cards_do_not_render(): void
  {
    $registry = new PluginRegistry(['analytics-tools' => false]);
    $registry->register(
      PluginDefinition::make('analytics-tools')
        ->label('Analytics Tools')
        ->systemCards([
          PluginSystemCard::make('analytics-tools.status')->title('Analytics System Status'),
        ])
    );
    $this->app->instance(PluginRegistry::class, $registry);

    $response = $this->actingAs(User::factory()->superAdmin()->create())
      ->get(route('admin.system.plugins.index'));

    $response->assertOk();
    $response->assertDontSee('Analytics System Status');
    $response->assertDontSee('data-plugin-system-card="analytics-tools.status"', false);
  }
}
