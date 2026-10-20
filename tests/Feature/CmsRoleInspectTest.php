<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Access\PermissionCatalog;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginPermission;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Tests\TestCase;

class CmsRoleInspectTest extends TestCase
{
  private User $administrator;

  private User $subject;

  private Site $site;

  private CmsRole $role;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->withoutMiddleware([RedirectIfNotInstalled::class]);
    $this->administrator = User::forceCreate(['name' => 'Synthetic administrator', 'email' => 'admin@example.test', 'password' => 'unused', 'role' => 'super_admin', 'is_active' => true]);
    $this->subject = User::forceCreate(['name' => 'Synthetic publisher', 'email' => 'publisher@example.test', 'password' => 'unused', 'role' => 'editor', 'is_active' => true]);
    $this->site = Site::create(['name' => 'Northstar', 'handle' => 'northstar']);
    $this->role = CmsRole::create(['name' => 'Publisher', 'handle' => 'custom-publisher']);
    foreach (['cms.panel.access', 'cms.pages.read', 'cms.pages.publish'] as $permission) {
      DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $this->role->id, 'permission' => $permission]);
    }
    $this->subject->sites()->attach($this->site->id, ['role_id' => $this->role->id]);
    $this->actingAs($this->administrator);
  }

  private function url(array $filters = []): string
  {
    return route('admin.access.inspect', ['user_id' => $this->subject->id, 'site_id' => $this->site->id, ...$filters]);
  }

  private function rows(TestResponse $response): array
  {
    $rows = [];
    foreach ($response->viewData('permissionGroups') as $items) {
      foreach ($items as $key => $definition) {
        $rows[$key] = $definition['allowed'];
      }
    }

    return $rows;
  }

  private function document(string $html): DOMXPath
  {
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

    return new DOMXPath($document);
  }

  #[Test]
  public function an_unselected_or_partial_context_shows_guidance_instead_of_denials(): void
  {
    foreach ([[], ['user_id' => $this->subject->id], ['site_id' => $this->site->id]] as $context) {
      $this->get(route('admin.access.inspect', $context))->assertOk()
        ->assertSee(__('webblocks-cms::acl.inspect_prompt'))
        ->assertViewHas('totalCount', 0)->assertViewHas('permissionGroups', fn ($groups) => $groups->isEmpty())
        ->assertDontSee('data-acl-permission=', false);
    }
  }

  #[Test]
  public function permissions_are_grouped_with_site_specific_results_and_accessible_controls(): void
  {
    $response = $this->get($this->url())->assertOk()
      ->assertViewHas('grantedCount', 3)
      ->assertViewHas('totalCount', count(app(PermissionCatalog::class)->editable()))
      ->assertSee('data-acl-permission="cms.pages.publish" data-acl-granted="true"', false)
      ->assertSee('data-acl-permission="cms.pages.unpublish" data-acl-granted="false"', false);
    $groups = $response->viewData('permissionGroups');
    $this->assertSame(array_keys(PermissionCatalog::GROUPS), $groups->keys()->all());
    $this->assertSame(PermissionCatalog::GROUPS['pages'], $groups['pages']->keys()->map(fn ($key) => substr($key, strlen('cms.pages.')))->all());
    $xpath = $this->document($response->getContent());
    $this->assertSame(0, $xpath->query('//details[@open]')->length);
    foreach (['q', 'user_id', 'site_id', 'status'] as $name) {
      $field = $xpath->query('//*[@name="'.$name.'"]')->item(0);
      $this->assertNotNull($field);
      $this->assertSame(1, $xpath->query('//label[@for="'.$field->getAttribute('id').'"]')->length);
    }
    foreach ($xpath->query('//*[@aria-labelledby]') as $element) {
      $this->assertSame(1, $xpath->query('//*[@id="'.$element->getAttribute('aria-labelledby').'"]')->length);
    }
    $other = Site::create(['name' => 'Atlas', 'handle' => 'atlas']);
    $this->get($this->url(['site_id' => $other->id, 'status' => 'allowed']))->assertOk()
      ->assertViewHas('grantedCount', 0)->assertViewHas('filteredCount', 0);
    $reader = CmsRole::create(['name' => 'Reader', 'handle' => 'custom-reader']);
    foreach (['cms.panel.access', 'cms.pages.read'] as $permission) {
      DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $reader->id, 'permission' => $permission]);
    }
    $this->subject->sites()->attach($other->id, ['role_id' => $reader->id]);
    $this->get($this->url(['site_id' => $other->id, 'status' => 'allowed']))->assertOk()
      ->assertViewHas('grantedCount', 2)->assertViewHas('filteredCount', 2)
      ->assertDontSee('data-acl-permission="cms.pages.publish"', false);
  }

  #[Test]
  public function search_matches_keys_translated_labels_and_groups_and_combines_with_status(): void
  {
    $allowed = $this->get($this->url(['q' => '  CMS.PAGES.  ', 'status' => 'allowed']))->assertOk()
      ->assertViewHas('filteredCount', 2)->assertViewHas('grantedCount', 3);
    $this->assertSame(['cms.pages.read' => true, 'cms.pages.publish' => true], $this->rows($allowed));
    $denied = $this->get($this->url(['q' => 'cms.pages.', 'status' => 'denied']))->assertOk();
    $this->assertCount(count(PermissionCatalog::GROUPS['pages']) - 2, $this->rows($denied));
    $this->assertNotContains(true, $this->rows($denied));
    $label = $this->get($this->url(['q' => 'Create drafts']))->assertOk();
    $this->assertSame(['cms.pages.create' => false], $this->rows($label));
    $group = $this->get($this->url(['q' => 'Comments and ratings']))->assertOk();
    $this->assertSame(['cms.engagement.read' => false, 'cms.engagement.moderate' => false, 'cms.engagement.delete' => false], $this->rows($group));
    $xpath = $this->document($allowed->getContent());
    $this->assertSame(1, $xpath->query('//details[@open]')->length);
    $this->assertSame(1, $xpath->query('//select[@name="user_id"]/option[@selected][@value="'.$this->subject->id.'"]')->length);
    $this->assertSame(1, $xpath->query('//select[@name="site_id"]/option[@selected][@value="'.$this->site->id.'"]')->length);
    $this->assertSame(1, $xpath->query('//select[@name="status"]/option[@selected][@value="allowed"]')->length);
    $allowed->assertSee('href="'.e($this->url()).'"', false);
    foreach (['0', '🙂', '<script>alert(1)</script>'] as $term) {
      $empty = $this->get($this->url(['q' => $term, 'status' => 'allowed']))->assertOk()
        ->assertViewHas('filteredCount', 0)->assertViewHas('grantedCount', 3)
        ->assertSee(__('webblocks-cms::acl.inspect_empty'))->assertDontSee('<script>alert(1)</script>', false);
      $this->assertSame([], $this->rows($empty));
    }
  }

  #[Test]
  public function inspection_is_read_only_and_rechecks_revoked_permissions(): void
  {
    $events = DB::table('wbcms_acl_events')->count();
    $grants = DB::table('wbcms_acl_role_permissions')->count();
    $this->assertSame(['cms.pages.publish' => true], $this->rows($this->get($this->url(['q' => 'cms.pages.publish', 'status' => 'allowed']))->assertOk()));
    $this->assertSame($events, DB::table('wbcms_acl_events')->count());
    $this->assertSame($grants, DB::table('wbcms_acl_role_permissions')->count());
    DB::table('wbcms_acl_role_permissions')->where('role_id', $this->role->id)->where('permission', 'cms.pages.publish')->delete();
    $this->get($this->url(['q' => 'cms.pages.publish', 'status' => 'allowed']))->assertOk()->assertViewHas('filteredCount', 0);
    $this->assertSame(['cms.pages.publish' => false], $this->rows($this->get($this->url(['q' => 'cms.pages.publish', 'status' => 'denied']))->assertOk()));
    $this->assertSame($events, DB::table('wbcms_acl_events')->count());
    $this->assertSame($grants - 1, DB::table('wbcms_acl_role_permissions')->count());
    $this->subject->sites()->detach($this->site->id);
    $this->get($this->url(['status' => 'allowed']))->assertOk()->assertViewHas('grantedCount', 0);
  }

  #[Test]
  public function inactive_accounts_and_super_administrators_keep_their_actual_results(): void
  {
    $this->subject->forceFill(['is_active' => false])->save();
    $this->get($this->url(['status' => 'allowed']))->assertOk()->assertViewHas('filteredCount', 0)->assertViewHas('grantedCount', 0);
    $denied = $this->get($this->url(['status' => 'denied']))->assertOk();
    $this->assertSame($denied->viewData('totalCount'), $denied->viewData('filteredCount'));
    $this->administrator->sites()->attach($this->site->id, ['role_id' => $this->role->id]);
    $root = $this->get($this->url(['user_id' => $this->administrator->id, 'status' => 'allowed']))->assertOk()
      ->assertSee(__('webblocks-cms::acl.super_admin'));
    $this->assertSame($root->viewData('totalCount'), $root->viewData('grantedCount'));
    $this->get($this->url(['user_id' => $this->administrator->id, 'status' => 'denied']))->assertOk()->assertViewHas('filteredCount', 0);
  }

  #[Test]
  public function active_plugin_permissions_are_grouped_and_disabled_declarations_are_excluded(): void
  {
    $registry = new PluginRegistry(['appointments' => true]);
    $registry->register(PluginDefinition::make('appointments')->permissions([PluginPermission::make('appointments.view')->siteDelegable()->label('Read appointments')]));
    $this->app->instance(PluginRegistry::class, $registry);
    DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $this->role->id, 'permission' => 'appointments.view']);
    $page = $this->get($this->url(['q' => 'Read appointments', 'status' => 'allowed']))->assertOk();
    $this->assertSame(['plugins'], $page->viewData('permissionGroups')->keys()->all());
    $this->assertSame(['appointments.view' => true], $this->rows($page));
    $this->app->instance(PluginRegistry::class, new PluginRegistry(['appointments' => false]));
    $this->get($this->url(['q' => 'appointments']))->assertOk()->assertViewHas('filteredCount', 0);
    $this->assertDatabaseHas('wbcms_acl_role_permissions', ['role_id' => $this->role->id, 'permission' => 'appointments.view']);
  }

  #[Test]
  public function inspection_matches_site_scoped_plugin_defaults_for_protected_roles(): void
  {
    $registry = new PluginRegistry(['appointments' => true]);
    $registry->register(PluginDefinition::make('appointments')->permissions([PluginPermission::make('appointments.view')->siteDelegable()->roles(['site_admin'])]));
    $this->app->instance(PluginRegistry::class, $registry);
    $builtin = CmsRole::where('handle', 'site_admin')->firstOrFail();
    $this->subject->sites()->updateExistingPivot($this->site->id, ['role_id' => $builtin->id]);
    $this->assertSame(['appointments.view' => true], $this->rows($this->get($this->url(['q' => 'appointments', 'status' => 'allowed']))->assertOk()));
    $other = Site::create(['name' => 'Other', 'handle' => 'other']);
    $this->subject->sites()->attach($other->id, ['role_id' => $this->role->id]);
    $this->assertSame(['appointments.view' => false], $this->rows($this->get($this->url(['site_id' => $other->id, 'q' => 'appointments', 'status' => 'denied']))->assertOk()));
    $this->get($this->url(['q' => 'appointments', 'status' => 'denied']))->assertOk()->assertViewHas('filteredCount', 0);
    $this->subject->forceFill(['is_active' => false])->save();
    $this->assertSame(['appointments.view' => false], $this->rows($this->get($this->url(['q' => 'appointments']))->assertOk()));
  }

  #[Test]
  public function filters_are_localized_and_preserve_every_english_key_and_placeholder(): void
  {
    $root = dirname(__DIR__, 2).'/resources/lang';
    $english = Arr::dot(require $root.'/en/acl.php');
    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $catalogue = Arr::dot(require $root.'/'.$locale.'/acl.php');
      $this->assertSame(array_keys($english), array_keys($catalogue));
      foreach ($english as $key => $source) {
        $this->assertIsString($catalogue[$key]);
        $this->assertNotSame('', trim($catalogue[$key]));
        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $source, $before);
        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $catalogue[$key], $after);
        sort($before[0]);
        sort($after[0]);
        $this->assertSame($before[0], $after[0], $locale.'.'.$key);
      }
      config()->set('app.locale', $locale);
      $term = $locale === 'tr' ? 'ICERIK KAYNAKLARI' : $catalogue['groups.sources'];
      $page = $this->get($this->url(['q' => $term, 'status' => 'denied']))->assertOk()
        ->assertSee($catalogue['permission_status'])->assertSee($catalogue['denied_filter']);
      $this->assertSame(['cms.sources.read' => false, 'cms.sources.preview' => false], $this->rows($page));
    }
  }

  #[Test]
  public function malformed_filters_are_rejected_and_only_active_super_admins_can_inspect(): void
  {
    $this->from($this->url())->get($this->url(['q' => str_repeat('x', 161)]))
      ->assertRedirect($this->url())->assertSessionHasErrors('q');
    $message = session('errors')->first('q');
    $this->get($this->url())->assertOk()->assertSee($message)->assertSee('wb-alert-danger', false);
    foreach ([['q' => ['bad']], ['q' => str_repeat('x', 161)], ['status' => 'invalid'], ['user_id' => 0], ['site_id' => ['bad']]] as $filters) {
      $this->getJson($this->url($filters))->assertUnprocessable()->assertJsonValidationErrors(array_key_first($filters));
    }
    $this->get($this->url(['user_id' => 999999]))->assertNotFound();
    $this->get($this->url(['site_id' => 999999]))->assertNotFound();
    $this->actingAs($this->subject)->get($this->url())->assertForbidden();
    $this->administrator->forceFill(['is_active' => false])->save();
    $this->actingAs($this->administrator)->get($this->url())->assertForbidden();
  }
}
