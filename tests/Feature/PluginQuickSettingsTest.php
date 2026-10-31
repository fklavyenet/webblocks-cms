<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use WebBlocks\Cms\Support\Plugins\PluginDashboardWidget;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginException;
use WebBlocks\Cms\Support\Plugins\PluginPermission;
use WebBlocks\Cms\Support\Plugins\PluginQuickSetting;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Tests\TestCase;

class PluginQuickSettingsTest extends TestCase
{
  public function test_authentication_and_recovery_paths_skip_resolvers_but_normal_panel_requests_keep_them(): void
  {
    $calls = 0;
    $registry = new PluginRegistry(['appointments' => true]);
    $registry->register(PluginDefinition::make('appointments')->quickSettings([
      PluginQuickSetting::make('appointments.availability')->resolveUsing(function () use (&$calls): array {
      $calls++;

      return [];
      }),
    ]));
    $user = new User;

    foreach (['/webadmin/two-factor', '/webadmin/two-factor/confirm', '/webadmin/plugin-recovery', '/webadmin/plugin-recovery/login'] as $path) {
      $this->app->instance('request', Request::create($path));
      $this->assertSame([], $registry->quickSettings($user));
    }
    $this->assertSame(0, $calls);
    $this->app->instance('request', Request::create('/webadmin/pages'));
    $this->assertCount(1, $registry->quickSettings($user));
    $this->assertSame(1, $calls);
  }

  public function test_data_resolvers_only_run_for_authenticated_authorized_users_of_active_plugins(): void
  {
    $calls = 0;
    $definition = PluginDefinition::make('appointments')->permissions([PluginPermission::make('appointments.manage')])
      ->quickSettings([PluginQuickSetting::make('appointments.availability')->permission('appointments.manage')->view('appointments::availability')
        ->resolveUsing(function ($user) use (&$calls): array {
        $calls++;

        return ['actor' => $user->role];
        })]);
    $registry = new PluginRegistry(['appointments' => true]);
    $registry->register($definition);
    $editor = (new User)->forceFill(['role' => 'editor']);
    $admin = (new User)->forceFill(['role' => 'super_admin', 'is_admin' => true]);
    $this->assertSame([], $registry->quickSettings());
    $this->assertSame([], $registry->quickSettings($editor));
    $this->assertSame(0, $calls);
    $settings = $registry->quickSettings($admin);
    $this->assertCount(1, $settings);
    $this->assertSame(['actor' => 'super_admin'], $settings[0]->viewData());
    $this->assertSame([], $definition->quickSettingDefinitions()['appointments.availability']->viewData());
    $registry = new PluginRegistry(['appointments' => false]);
    $registry->register($definition);
    $this->assertSame([], $registry->quickSettings($admin));
    $this->assertSame(1, $calls);
  }

  public function test_empty_site_context_hides_views_and_old_value_widgets_remain_compatible(): void
  {
    $registry = new PluginRegistry(['appointments' => true]);
    $registry->register(PluginDefinition::make('appointments')->dashboardWidgets([
      PluginDashboardWidget::make('appointments.old')->title('Bookings')->value(12),
      PluginDashboardWidget::make('appointments.site')->view('appointments::site')->summary()->resolveUsing(fn () => null),
    ])->quickSettings([PluginQuickSetting::make('appointments.empty')->resolveUsing(fn () => null)]));
    $widgets = $registry->dashboardWidgets(new User);
    $this->assertCount(1, $widgets);
    $this->assertSame('12', $widgets[0]->valueText());
    $this->assertSame([], $registry->quickSettings(new User));
  }

  public function test_quick_settings_cannot_claim_another_plugins_identity(): void
  {
    $this->expectException(PluginException::class);
    PluginDefinition::make('appointments')->quickSettings([PluginQuickSetting::make('foreign.availability')]);
  }
}
