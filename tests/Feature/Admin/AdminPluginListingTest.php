<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginMenuItem;
use WebBlocks\Cms\Support\Plugins\PluginPermission;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

class AdminPluginListingTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function super_admin_can_view_empty_plugins_listing(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.plugins.index'));

    $response->assertOk();
    $response->assertSeeText('Plugins');
    $response->assertSeeText('No plugins registered yet.');
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
    app(PluginRegistry::class)->register($this->fakePlugin());

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

    app(PluginRegistry::class)->register($this->fakePlugin());

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.plugins.index'));

    $response->assertOk();
    $response->assertSeeText('WebBlocks UI Manager');
    $response->assertSeeText('Enabled');
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

  private function fakePlugin(): PluginDefinition
  {
    return PluginDefinition::make('webblocks-ui-manager')
      ->label('WebBlocks UI Manager')
      ->version('0.1.0')
      ->provider('Vendor\\WebBlocksUiManager\\ServiceProvider')
      ->description('Publishes WebBlocks UI release artifacts to first-party CDN.')
      ->menu([
        PluginMenuItem::make('releases')
          ->label('Releases')
          ->route('webblocks.plugins.webblocks_ui_manager.releases.index')
          ->icon('wb-icon-box')
          ->permission('webblocks-ui-manager.view'),
      ])
      ->permissions([
        PluginPermission::make('webblocks-ui-manager.view')->label('View WebBlocks UI releases'),
        PluginPermission::make('webblocks-ui-manager.publish')->label('Publish WebBlocks UI releases'),
      ]);
  }
}
