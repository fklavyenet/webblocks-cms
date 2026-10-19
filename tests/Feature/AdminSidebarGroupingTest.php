<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginMenuItem;
use WebBlocks\Cms\Support\Plugins\PluginPermission;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Tests\TestCase;

class AdminSidebarGroupingTest extends TestCase
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

  private function user(string $role = 'super_admin'): User
  {
    return User::forceCreate(['name' => 'Synthetic operator', 'email' => uniqid().'@example.test', 'password' => 'unused', 'role' => $role, 'is_active' => true]);
  }

  private function sidebar(User $user, string $activeRoute = 'admin.dashboard'): DOMXPath
  {
    $this->actingAs($user);
    request()->setUserResolver(fn () => $user);
    request()->setRouteResolver(fn () => Route::getRoutes()->getByName($activeRoute));
    view()->share('errors', new ViewErrorBag);
    $html = Blade::render("@extends('webblocks-cms::layouts.admin', ['title' => 'Sidebar QA']) @section('content') @endsection");
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

    return new DOMXPath($document);
  }

  private function sidebarGroups(DOMXPath $xpath): array
  {
    $groups = [];
    foreach ($xpath->query('//aside[@id="admin-sidebar"]/nav/div[@data-wb-nav-group]') as $node) {
      $label = trim($xpath->query('./button/span[@class="wb-nav-group-label"]', $node)->item(0)->textContent);
      $groups[$label] = $node;
    }

    return $groups;
  }

  private function paths(DOMXPath $xpath, DOMElement $node): array
  {
    $paths = [];
    foreach ($xpath->query('.//a', $node) as $link) {
      $paths[] = parse_url($link->getAttribute('href'), PHP_URL_PATH);
    }

    return $paths;
  }

  #[Test]
  public function core_destinations_are_grouped_once_and_primary_links_stay_direct(): void
  {
    $xpath = $this->sidebar($this->user());
    $groups = $this->sidebarGroups($xpath);
    $this->assertSame(['Content tools', 'Contact & analytics', 'Users & access', 'System', 'Maintenance'], array_keys($groups));
    $this->assertSame(['/webadmin/blocks', '/webadmin/dynamic-content', '/webadmin/shared-slots', '/webadmin/navigation'], $this->paths($xpath, $groups['Content tools']));
    $this->assertSame(['/webadmin/contact-messages', '/webadmin/engagement', '/webadmin/reports/visitors'], $this->paths($xpath, $groups['Contact & analytics']));
    $this->assertSame(['/webadmin/users', '/webadmin/access/roles', '/webadmin/system/api-tokens'], $this->paths($xpath, $groups['Users & access']));
    $this->assertNotContains('/webadmin/users', $this->paths($xpath, $groups['System']));
    $this->assertNotContains('/webadmin/system/api-tokens', $this->paths($xpath, $groups['System']));
    $nav = $xpath->query('//aside[@id="admin-sidebar"]/nav')->item(0);
    $primary = $xpath->query('./div[1]', $nav)->item(0);
    $this->assertSame(['/webadmin', '/webadmin/sites', '/webadmin/pages', '/webadmin/media', '/webadmin/assets'], $this->paths($xpath, $primary));
    $allPaths = $this->paths($xpath, $nav);
    $this->assertSame($allPaths, array_values(array_unique($allPaths)));
    $bundledIcons = array_column(json_decode(file_get_contents(dirname(__DIR__, 2).'/public/cms/webblocks-ui/v2.30.0/webblocks-icons.json'), true), 'css_class');
    foreach ($xpath->query('.//a', $nav) as $link) {
      $icons = $xpath->query('./i[@aria-hidden="true"]', $link);
      $this->assertSame(1, $icons->length, $link->textContent);
      preg_match('/\bwb-icon-[a-z0-9-]+\b/', $icons->item(0)->getAttribute('class'), $matches);
      $this->assertContains($matches[0] ?? '', $bundledIcons, $link->textContent);
    }
  }

  #[Test]
  public function active_routes_open_only_their_own_group_including_nested_screens(): void
  {
    $user = $this->user();
    foreach (['admin.dynamic-content.index' => 'Content tools', 'admin.engagement.index' => 'Contact & analytics', 'admin.access.inspect' => 'Users & access', 'admin.system.api-tokens.index' => 'Users & access', 'admin.system.settings.edit' => 'System', 'admin.system.health.index' => 'Maintenance'] as $route => $expectedGroup) {
      $xpath = $this->sidebar($user, $route);
      $openGroups = array_filter($this->sidebarGroups($xpath), fn ($group) => $group->hasAttribute('data-wb-nav-group-open'));
      $this->assertSame([$expectedGroup], array_keys($openGroups), $route);
      $this->assertSame('true', $xpath->query('./button', $openGroups[$expectedGroup])->item(0)->getAttribute('aria-expanded'));
      $this->assertSame(1, $xpath->query('.//a[@aria-current="page"]', $openGroups[$expectedGroup])->length);
    }
    foreach (['admin.media.edit', 'admin.site-assets.index'] as $route) {
      $xpath = $this->sidebar($user, $route);
      $this->assertSame([], array_filter($this->sidebarGroups($xpath), fn ($group) => $group->hasAttribute('data-wb-nav-group-open')));
      $this->assertSame(1, $xpath->query('//aside[@id="admin-sidebar"]/nav/div[1]/a[@aria-current="page"]')->length);
    }
  }

  #[Test]
  public function sql_grants_filter_children_and_remove_empty_groups_without_system_access(): void
  {
    $user = $this->user('editor');
    $site = Site::create(['name' => 'Synthetic site', 'handle' => 'synthetic']);
    $role = CmsRole::create(['name' => 'Media and reports', 'handle' => 'custom-media-reports']);
    foreach (['cms.panel.access', 'cms.media.read', 'cms.navigation.read', 'cms.reports.read'] as $permission) {
      DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $role->id, 'permission' => $permission]);
    }
    $user->sites()->attach($site->id, ['role_id' => $role->id]);
    $xpath = $this->sidebar($user, 'admin.media.index');
    $groups = $this->sidebarGroups($xpath);
    $this->assertSame(['Content tools', 'Contact & analytics'], array_keys($groups));
    $this->assertSame(['/webadmin/navigation'], $this->paths($xpath, $groups['Content tools']));
    $this->assertSame(['/webadmin/reports/visitors'], $this->paths($xpath, $groups['Contact & analytics']));
    $this->assertSame(['/webadmin', '/webadmin/media'], $this->paths($xpath, $xpath->query('//aside[@id="admin-sidebar"]/nav/div[1]')->item(0)));
    DB::table('wbcms_acl_role_permissions')->where('role_id', $role->id)->whereIn('permission', ['cms.media.read', 'cms.navigation.read'])->delete();
    $xpath = $this->sidebar($user);
    $this->assertSame(['Contact & analytics'], array_keys($this->sidebarGroups($xpath)));
    $this->assertSame(['/webadmin'], $this->paths($xpath, $xpath->query('//aside[@id="admin-sidebar"]/nav/div[1]')->item(0)));
  }

  #[Test]
  public function plugin_groups_permissions_and_localized_core_buckets_are_preserved(): void
  {
    IconCatalogItem::create(['source' => 'webblocks-ui', 'slug' => 'message-square', 'label' => 'Chat', 'is_active' => true, 'contexts' => ['navigation']]);
    $registry = new PluginRegistry(['chat' => true]);
    $registry->register(PluginDefinition::make('chat')->label('Synthetic chat')->version('1.0.0')->icon('message-square')
      ->permissions([PluginPermission::make('chat.read')->siteDelegable()])
      ->menu([
        PluginMenuItem::make('inbox')->label('Inbox')->group('Live Chat')->route('plugins.chat.inbox.index')->permission('chat.read')->icon('wb-icon-inbox'),
        PluginMenuItem::make('system')->label('Chat setup')->group('System')->route('plugins.chat.setup.index')->permission('chat.read'),
        PluginMenuItem::make('maintenance')->label('Chat health')->group('Maintenance')->route('plugins.chat.health.index')->permission('chat.read'),
      ]));
    $this->app->instance(PluginRegistry::class, $registry);
    foreach (['inbox', 'setup', 'health'] as $screen) {
      Route::get('/synthetic-chat/'.$screen, fn () => 'Fixture')->name('plugins.chat.'.$screen.'.index');
    }
    Route::getRoutes()->refreshNameLookups();
    $root = $this->user();
    $root->forceFill(['admin_locale' => 'tr'])->save();
    $xpath = $this->sidebar($root, 'plugins.chat.inbox.index');
    $groups = $this->sidebarGroups($xpath);
    $this->assertArrayHasKey('İçerik Araçları', $groups);
    $this->assertArrayHasKey('Kullanıcılar ve Erişim', $groups);
    $this->assertContains('/synthetic-chat/setup', $this->paths($xpath, $groups['Sistem']));
    $this->assertContains('/synthetic-chat/health', $this->paths($xpath, $groups['Bakım']));
    $this->assertSame(['/synthetic-chat/inbox'], $this->paths($xpath, $groups['Live Chat']));
    $this->assertTrue($groups['Live Chat']->hasAttribute('data-wb-nav-group-open'));
    $this->assertSame('wb-icon wb-icon-message-square wb-nav-group-icon', $xpath->query('./button/i', $groups['Live Chat'])->item(0)->getAttribute('class'));
    $this->assertSame('wb-icon wb-icon-inbox', $xpath->query('.//a/i', $groups['Live Chat'])->item(0)->getAttribute('class'));
    $this->assertSame('wb-icon wb-icon-palette', $xpath->query('.//a[contains(@href,"/synthetic-chat/setup")]/i', $groups['Sistem'])->item(0)->getAttribute('class'));
    $this->assertSame('wb-icon wb-icon-file', $xpath->query('.//a[contains(@href,"/synthetic-chat/health")]/i', $groups['Bakım'])->item(0)->getAttribute('class'));
    $editor = $this->user('editor');
    $this->assertArrayNotHasKey('Live Chat', $this->sidebarGroups($this->sidebar($editor)));
    $site = Site::create(['name' => 'Synthetic chat site', 'handle' => 'chat-fixture']);
    $role = CmsRole::create(['name' => 'Chat operator', 'handle' => 'custom-chat']);
    foreach (['cms.panel.access', 'chat.read'] as $permission) {
      DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $role->id, 'permission' => $permission]);
    }
    $editor->sites()->attach($site->id, ['role_id' => $role->id]);
    $xpath = $this->sidebar($editor, 'plugins.chat.inbox.index');
    $groups = $this->sidebarGroups($xpath);
    $this->assertSame(['/synthetic-chat/inbox'], $this->paths($xpath, $groups['Live Chat']));
    $this->assertSame(['/synthetic-chat/setup'], $this->paths($xpath, $groups['System']));
    $this->assertTrue($groups['Live Chat']->hasAttribute('data-wb-nav-group-open'));
  }

  #[Test]
  public function existing_plugin_heading_is_not_absorbed_into_a_new_core_group_with_the_same_label(): void
  {
    $registry = new PluginRegistry(['example' => true]);
    $registry->register(PluginDefinition::make('example')->label('Example')->version('1.0.0')->icon('plug')
      ->menu([PluginMenuItem::make('items')->label('Example items')->group('Content tools')->route('plugins.example.items.index')]));
    $this->app->instance(PluginRegistry::class, $registry);
    Route::get('/synthetic-example/items', fn () => 'Fixture')->name('plugins.example.items.index');
    Route::getRoutes()->refreshNameLookups();
    $xpath = $this->sidebar($this->user(), 'plugins.example.items.index');
    $groups = $xpath->query('//aside[@id="admin-sidebar"]/nav/div[@data-wb-nav-group][button/span[@class="wb-nav-group-label"]="Content tools"]');
    $this->assertSame(2, $groups->length);
    $this->assertNotContains('/synthetic-example/items', $this->paths($xpath, $groups->item(0)));
    $this->assertFalse($groups->item(0)->hasAttribute('data-wb-nav-group-open'));
    $this->assertSame(['/synthetic-example/items'], $this->paths($xpath, $groups->item(1)));
    $this->assertTrue($groups->item(1)->hasAttribute('data-wb-nav-group-open'));
  }
}
