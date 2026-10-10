<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use RuntimeException;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\Plugins\InstalledPluginDefinitionFactory;
use WebBlocks\Cms\Support\Plugins\PluginAppearance;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginIconDeclaration;
use WebBlocks\Cms\Support\Plugins\PluginMenuItem;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginSettingsDefinition;
use WebBlocks\Cms\Support\Plugins\PluginSidebarGroups;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Tests\TestCase;

class PluginAppearanceTest extends TestCase
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

  protected function setUp(): void
  {
    parent::setUp();
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    foreach (['calendar', 'message-square', 'rocket', 'plug'] as $slug) {
      IconCatalogItem::query()->create(['source' => 'webblocks-ui', 'slug' => $slug, 'label' => ucfirst($slug), 'is_active' => true, 'contexts' => ['navigation']]);
    }
    Gate::define('access-system', fn ($user) => $user->name === 'Administrator');
    Gate::define('alpha.view', fn () => true);
    Gate::define('beta.view', fn () => true);
    $registry = new PluginRegistry(['alpha' => true, 'beta' => true]);
    $registry->register($this->plugin('alpha', 'Calendar', 'calendar'));
    $registry->register($this->plugin('beta', 'Chat', 'message-square'));
    $this->app->instance(PluginRegistry::class, $registry);
    $this->withoutMiddleware();
    view()->share('errors', new ViewErrorBag);
    Route::get('/webadmin/plugins/alpha/items', fn () => 'Items')->name('webblocks.plugins.alpha.items.index');
    Route::get('/webadmin/plugins/beta/items', fn () => 'Items')->name('webblocks.plugins.beta.items.index');
    Route::get('/webadmin/plugins/alpha/settings', fn () => Blade::render("@extends('webblocks-cms::layouts.admin', ['title' => 'Plugin Settings']) @section('content') <form id=\"plugin-own-form\"></form> @endsection"))->name('webblocks.plugins.alpha.settings.edit');
    Route::get('/webadmin/plugins/beta/settings', fn () => 'Settings')->name('webblocks.plugins.beta.settings.edit');
    Route::getRoutes()->refreshNameLookups();
  }

  public function test_declared_defaults_and_legacy_menu_icons_are_resolved(): void
  {
    $legacy = $this->plugin('legacy', 'Legacy', 'calendar');
    $this->assertSame('calendar', $legacy->defaultIconSlug());
    $legacy->icon('wb-icon-message-square');
    $this->assertSame('message-square', $legacy->defaultIconSlug());
    $this->assertSame('message-square', $legacy->toArray()['icon']);
    $this->assertSame('wb-icon-message-square', app(PluginAppearance::class)->iconClass($legacy));
    $this->assertNull(PluginDefinition::iconSlug('wb-icon wb-icon-calendar" onclick="bad'));
  }

  public function test_manifest_icon_is_loaded_without_enabling_the_plugin(): void
  {
    $plugin = app(InstalledPluginDefinitionFactory::class)->make([
      'handle' => 'manifest-example', 'label' => 'Manifest Example', 'version' => '1.0.0', 'icon' => 'rocket', 'provider' => 'MissingExampleProvider',
    ], sys_get_temp_dir().'/not-installed', false);
    $this->assertSame('rocket', $plugin->defaultIconSlug());
    $this->assertSame('wb-icon-rocket', app(PluginAppearance::class)->iconClass($plugin));
  }

  public function test_save_reset_and_version_changes_preserve_other_plugin_settings(): void
  {
    $this->actingAs((new User)->forceFill(['name' => 'Administrator']));
    SystemSetting::query()->create(['key' => 'plugins.sidebar_icon.beta', 'value' => 'message-square']);
    SystemSetting::query()->create(['key' => 'other.plugin.setting', 'value' => 'unchanged']);
    $this->put('/webadmin/system/plugins/alpha/appearance', ['sidebar_icon' => 'rocket', 'origin' => 'settings', 'site_id' => 12])
      ->assertRedirect('/webadmin/plugins/alpha/settings?site_id=12#plugin-menu-appearance');
    $this->assertDatabaseHas('wbcms_system_settings', ['key' => 'plugins.sidebar_icon.alpha', 'value' => 'rocket']);
    $updated = $this->plugin('alpha', 'Calendar', 'calendar')->version('2.0.0');
    $this->assertSame('wb-icon-rocket', (new PluginAppearance)->iconClass($updated));
    $this->assertDatabaseHas('wbcms_system_settings', ['key' => 'other.plugin.setting', 'value' => 'unchanged']);
    $this->put('/webadmin/system/plugins/alpha/appearance', ['sidebar_icon' => '', 'origin' => 'details'])->assertRedirect();
    $this->assertDatabaseMissing('wbcms_system_settings', ['key' => 'plugins.sidebar_icon.alpha']);
    $this->assertDatabaseHas('wbcms_system_settings', ['key' => 'plugins.sidebar_icon.beta', 'value' => 'message-square']);
    $this->assertSame('wb-icon-calendar', (new PluginAppearance)->iconClass($updated));
  }

  public function test_invalid_or_inactive_choices_and_unauthorized_changes_are_rejected(): void
  {
    $this->actingAs((new User)->forceFill(['name' => 'Administrator']));
    IconCatalogItem::query()->where('slug', 'rocket')->update(['is_active' => false]);
    foreach (['rocket', 'missing-icon', '<svg onload=alert(1)>', ['calendar']] as $value) {
      $this->put('/webadmin/system/plugins/alpha/appearance', ['sidebar_icon' => $value, 'origin' => 'details'])->assertSessionHasErrors(['sidebar_icon'], errorBag: 'pluginAppearance');
    }
    $this->assertDatabaseMissing('wbcms_system_settings', ['key' => 'plugins.sidebar_icon.alpha']);
    $this->actingAs((new User)->forceFill(['name' => 'Editor']));
    $this->put('/webadmin/system/plugins/alpha/appearance', ['sidebar_icon' => 'calendar', 'origin' => 'details'])->assertForbidden();
  }

  public function test_inactive_override_falls_back_to_plugin_default(): void
  {
    SystemSetting::query()->create(['key' => 'plugins.sidebar_icon.alpha', 'value' => 'rocket']);
    IconCatalogItem::query()->where('slug', 'rocket')->update(['is_active' => false]);
    $plugin = app(PluginRegistry::class)->get('alpha');
    $this->assertSame('wb-icon-calendar', app(PluginAppearance::class)->iconClass($plugin));
    $this->assertSame('rocket', app(PluginAppearance::class)->selectedSlug($plugin));
  }

  public function test_own_groups_have_distinct_icons_and_shared_core_groups_keep_their_icon(): void
  {
    SystemSetting::query()->create(['key' => 'plugins.sidebar_icon.alpha', 'value' => 'rocket']);
    $core = [['key' => 'system', 'label' => 'System', 'icon' => 'wb-icon-palette', 'items' => []]];
    $groups = app(PluginSidebarGroups::class)->appendTo($core, null, 'en');
    $this->assertSame(['wb-icon-palette', 'wb-icon-rocket', 'wb-icon-message-square'], array_column($groups, 'icon'));
    $this->assertSame(['webblocks.plugins.alpha.items.*'], $groups[1]['items'][0]['active']);
    $shared = new PluginRegistry(['alpha' => true, 'beta' => true]);
    $shared->register($this->plugin('alpha', 'System', 'calendar'));
    $shared->register($this->plugin('beta', 'System', 'message-square'));
    $service = new PluginSidebarGroups($shared, new PluginAppearance, app(CmsTranslator::class));
    $groups = $service->appendTo($core, null, 'en');
    $this->assertCount(1, $groups);
    $this->assertSame('wb-icon-palette', $groups[0]['icon']);
    $this->assertCount(4, $groups[0]['items']);
  }

  public function test_help_contributions_remain_reachable_and_maintenance_merges_by_stable_key(): void
  {
    $registry = new PluginRegistry(['alpha' => true, 'beta' => true]);
    $registry->register($this->plugin('alpha', 'Help', 'calendar'));
    $registry->register($this->plugin('beta', 'Maintenance', 'message-square'));
    $core = [
      ['key' => 'system', 'label' => 'Sistem', 'icon' => 'wb-icon-palette', 'items' => []],
      ['key' => 'maintenance', 'label' => 'Bakım ve Sağlık', 'icon' => 'wb-icon-heart', 'items' => []],
    ];
    $groups = (new PluginSidebarGroups($registry, new PluginAppearance, app(CmsTranslator::class)))->appendTo($core, null, 'en');
    $this->assertSame(['system', 'maintenance'], array_column($groups, 'key'));
    $this->assertSame(['Sistem', 'Bakım ve Sağlık'], array_column($groups, 'label'));
    $this->assertSame('webblocks.plugins.alpha.items.index', $groups[0]['items'][0]['route']);
    $this->assertSame('webblocks.plugins.beta.items.index', $groups[1]['items'][0]['route']);
    $this->assertCount(2, $groups[0]['items']);
    $this->assertCount(2, $groups[1]['items']);
  }

  public function test_two_plugins_sharing_a_custom_group_use_a_neutral_group_icon(): void
  {
    $shared = new PluginRegistry(['alpha' => true, 'beta' => true]);
    $shared->register($this->plugin('alpha', 'Shared Tools', 'calendar'));
    $shared->register($this->plugin('beta', 'Shared Tools', 'message-square'));
    $service = new PluginSidebarGroups($shared, new PluginAppearance, app(CmsTranslator::class));
    $groups = $service->appendTo([], null, 'en');
    $this->assertCount(1, $groups);
    $this->assertSame('wb-icon-plug', $groups[0]['icon']);
  }

  public function test_settings_page_gets_the_existing_picker_in_a_separate_form_only_for_system_managers(): void
  {
    $this->actingAs((new User)->forceFill(['name' => 'Administrator']));
    $html = $this->get('/webadmin/plugins/alpha/settings')->assertOk()->getContent();
    $this->assertStringContainsString('id="plugin-own-form"', $html);
    $this->assertStringContainsString('id="plugin-menu-appearance"', $html);
    $this->assertStringContainsString('data-wb-icon-picker-open', $html);
    $this->assertStringContainsString('data-slug="rocket"', $html);
    $this->assertStringContainsString('name="sidebar_icon"', $html);
    $this->assertStringNotContainsString('name="icon_tone"', $html);
    $this->actingAs((new User)->forceFill(['name' => 'Editor']));
    $this->get('/webadmin/plugins/alpha/settings')->assertOk()->assertDontSee('id="plugin-menu-appearance"', escape: false);
  }

  public function test_new_explicit_defaults_must_be_bundled_and_menu_packages_must_declare_an_icon(): void
  {
    $declaration = new PluginIconDeclaration;
    $declaration->validate(['icon' => 'calendar']);
    $declaration->validate(['menu' => [['icon' => 'wb-icon-calendar']]]);
    // Keep installed 1.0 manifests compatible even if their old icon no longer
    // exists. Rendering safely falls back until the operator chooses a new one.
    $declaration->validate(['menu' => [['icon' => 'wb-icon-legacy-symbol']]]);
    foreach ([['icon' => 'missing-icon'], ['icon' => ['calendar']], ['menu' => [['label' => 'No icon']]]] as $manifest) {
      try {
        $declaration->validate($manifest);
        $this->fail('Invalid declaration was accepted.');
      } catch (RuntimeException $exception) {
        $this->assertStringContainsString('WebBlocks UI icon', $exception->getMessage());
      }
    }
  }

  private function plugin(string $handle, string $group, string $icon): PluginDefinition
  {
    return PluginDefinition::make($handle)->label(ucfirst($handle))->version('1.0.0')
      ->settings(PluginSettingsDefinition::make('webblocks.plugins.'.$handle.'.settings.edit'))
      ->menu([PluginMenuItem::make('items')->label('Items')->group($group)->route('webblocks.plugins.'.$handle.'.items.index')->icon('wb-icon-'.$icon)->permission($handle.'.view')]);
  }
}
