<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginHealthMonitor;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

class AdminPluginListingTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function super_admin_can_view_discoverable_pilot_plugin_listing(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.plugins.index'));

    $response->assertOk();
    $response->assertSeeText('Plugins');
    $response->assertSeeText('WebBlocks UI Manager');
    $response->assertSeeText('Disabled');
  }

  #[Test]
  public function non_system_users_cannot_view_plugins_listing(): void
  {
    $user = User::factory()->editor()->create();

    $this->actingAs($user)
      ->get(route('admin.system.plugins.index'))
      ->assertForbidden();
  }

  #[Test]
  public function registered_disabled_plugin_appears_with_disabled_status(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.plugins.index'));

    $response->assertOk();
    $response->assertSeeText('WebBlocks UI Manager');
    $response->assertSeeText('webblocks-ui-manager');
    $response->assertSeeText('0.1.0');
    $response->assertSeeText('Disabled');
    $response->assertSeeText('2');
    $response->assertSeeText('1');
  }

  #[Test]
  public function registered_enabled_plugin_appears_with_enabled_status(): void
  {
    config()->set('webblocks-plugins.enabled.webblocks-ui-manager', true);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.plugins.index'));

    $response->assertOk();
    $response->assertSeeText('WebBlocks UI Manager');
    $response->assertSeeText('Enabled');
  }

  #[Test]
  public function incompatible_configured_plugin_appears_with_clear_incompatible_status(): void
  {
    $registry = new PluginRegistry(['future-plugin' => true]);
    $registry->register(
      PluginDefinition::make('future-plugin')
        ->label('Future Plugin')
        ->version('9.0.0')
        ->requiresCms('>=99.0.0')
    );
    $this->app->instance(PluginRegistry::class, $registry);
    $this->app->forgetInstance(PluginHealthMonitor::class);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.plugins.index'));

    $response->assertOk();
    $response->assertSeeText('Future Plugin');
    $response->assertSeeText('Incompatible');
    $response->assertSeeText('Requires WebBlocks CMS >=99.0.0');
  }

  #[Test]
  public function plugins_navigation_item_is_visible_only_to_super_admin_users(): void
  {
    $superAdmin = User::factory()->superAdmin()->create();
    $editor = User::factory()->editor()->create();

    $superAdminResponse = $this->actingAs($superAdmin)->get(route('admin.dashboard'));
    $superAdminResponse->assertOk();
    $superAdminResponse->assertSee('href="'.route('admin.system.plugins.index').'"', false);

    $editorResponse = $this->followingRedirects()->actingAs($editor)->get(route('admin.pages.index'));
    $editorResponse->assertOk();
    $editorResponse->assertDontSee('href="'.route('admin.system.plugins.index').'"', false);
  }
}
