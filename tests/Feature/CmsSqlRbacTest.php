<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use WebBlocks\Cms\Actions\Access\WriteCmsAccess;
use WebBlocks\Cms\Http\Controllers\Admin\CmsRoleController;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Http\Middleware\RequireAdminAccess;
use WebBlocks\Cms\Http\Middleware\RequireInternalApiToken;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Policies\CmsResourcePolicy;
use WebBlocks\Cms\Queries\Access\CmsRoleQuery;
use WebBlocks\Cms\Support\Access\AclSchema;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Access\PermissionCatalog;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\InternalApiTokens\PersonalApiTokenPolicy;
use WebBlocks\Cms\Support\Pages\PageWorkflowManager;
use WebBlocks\Cms\Support\Plugins\PluginAccessResolver;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginPermission;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use WebBlocks\Cms\Tests\TestCase;

class CmsSqlRbacTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  private function user(string $role = 'editor'): User
  {
    return User::forceCreate(['name' => 'Synthetic operator', 'email' => 'operator-'.uniqid().'@example.test', 'password' => 'unused', 'role' => $role, 'is_active' => true]);
  }

  private function site(string $handle = 'alpha'): Site
  {
    return Site::query()->create(['name' => $handle, 'handle' => $handle, 'is_primary' => false]);
  }

  private function role(array $permissions): CmsRole
  {
    return app(WriteCmsAccess::class)->role($this->user('super_admin'), ['name' => 'Custom role', 'permissions' => ['cms.panel.access', ...$permissions]]);
  }

  private function assign(User $user, Site $site, CmsRole $role): void
  {
    $user->sites()->syncWithoutDetaching([$site->id => ['role_id' => $role->id]]);
  }

  #[Test]
  public function upgrade_imports_existing_rights_once_and_never_resurrects_revoked_access(): void
  {
    $root = $this->user('super_admin');
    $editor = $this->user();
    $site = $this->site();
    $editor->sites()->attach($site->id);
    // Model an installation immediately before adding the role column.
    Schema::drop('wbcms_site_user');
    Schema::create('wbcms_site_user', function ($table): void {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->unsignedBigInteger('site_id');
    $table->timestamps();
    $table->unique(['user_id', 'site_id']);
    });
    DB::table('wbcms_site_user')->insert(['user_id' => $editor->id, 'site_id' => $site->id]);
    DB::table('wbcms_acl_system_administrators')->delete();
    DB::table('wbcms_acl_locks')->where('id', 2)->delete();
    app(AclSchema::class)->migrate();
    $acl = app(CmsAuthorization::class);
    $this->assertTrue($acl->isSuperAdmin($root));
    $this->assertTrue($acl->allows($editor, 'cms.pages.delete-published', $site->id));
    $this->assertTrue($acl->allows($editor, 'cms.pages.unpublish', $site->id));
    $this->assertFalse($acl->allows($editor, 'cms.pages.publish', $site->id));
    DB::table('wbcms_acl_system_administrators')->delete();
    app(AclSchema::class)->migrate();
    $this->assertFalse($root->isSuperAdmin());
    $this->assertFalse($acl->allows($root, 'cms.pages.publish', $site->id));
  }

  #[Test]
  public function site_roles_do_not_leak_and_loaded_relations_do_not_keep_revoked_grants(): void
  {
    $user = $this->user();
    $a = $this->site();
    $b = $this->site('beta');
    $publisher = $this->role(['cms.pages.read', 'cms.pages.publish']);
    $writer = $this->role(['cms.pages.read', 'cms.pages.update-draft']);
    $this->assign($user, $a, $publisher);
    $this->assign($user, $b, $writer);
    $user->load('sites');
    $acl = app(CmsAuthorization::class);
    $this->assertTrue($acl->allows($user, 'cms.pages.publish', $a->id));
    $this->assertFalse($acl->allows($user, 'cms.pages.publish', $b->id));
    DB::table('wbcms_acl_role_permissions')->where('role_id', $publisher->id)->where('permission', 'cms.pages.publish')->delete();
    $this->assertFalse($acl->allows($user, 'cms.pages.publish', $a->id));
    DB::table('wbcms_site_user')->where('user_id', $user->id)->delete();
    $this->assertFalse($user->canAccessAdmin());
  }

  #[Test]
  public function page_lists_and_direct_http_writes_use_the_same_grants(): void
  {
    $user = $this->user();
    $site = $this->site();
    $other = $this->site('beta');
    $this->assign($user, $site, $this->role(['cms.pages.read']));
    $page = Page::query()->create(['site_id' => $site->id, 'title' => 'Visible', 'status' => 'draft']);
    Page::query()->create(['site_id' => $other->id, 'title' => 'Hidden', 'status' => 'draft']);
    $this->assertSame([$page->id], app(AdminAuthorization::class)->scopePagesForUser(Page::query(), $user)->pluck('id')->all());
    Route::middleware(['web', RequireAdminAccess::class])->delete('/acl/pages/{page}', fn () => response('unexpected'))->name('admin.pages.destroy');
    $this->actingAs($user)->delete('/acl/pages/'.$page->id)->assertForbidden();
    $this->assertNotNull($page->fresh());
  }

  #[Test]
  public function publish_does_not_imply_unpublish_delete_or_host_authority(): void
  {
    $user = $this->user();
    $site = $this->site();
    $this->assign($user, $site, $this->role(['cms.pages.read', 'cms.pages.publish']));
    $page = Page::query()->create(['site_id' => $site->id, 'title' => 'Page', 'status' => 'draft']);
    $workflow = app(PageWorkflowManager::class);
    $this->assertTrue($workflow->canRunAction($user, $page, 'publish'));
    $page->status = 'published';
    $this->assertFalse($workflow->canRunAction($user, $page, 'restore_draft'));
    Gate::define('host.billing.manage', fn () => false);
    $this->assertFalse(Gate::forUser($this->user('super_admin'))->allows('host.billing.manage'));
  }

  #[Test]
  public function role_updates_are_versioned_audited_and_built_in_roles_are_protected(): void
  {
    $root = $this->user('super_admin');
    $role = $this->role(['cms.pages.read']);
    $writer = app(WriteCmsAccess::class);
    $writer->role($root, ['name' => 'Renamed', 'permissions' => ['cms.panel.access'], 'version' => 1], $role);
    $this->assertDatabaseHas('wbcms_acl_events', ['action' => 'permission.revoked', 'role_id' => $role->id, 'permission' => 'cms.pages.read']);
    try {
    $writer->role($root, ['name' => 'Stale', 'permissions' => [], 'version' => 1], $role);
    $this->fail();
    } catch (ValidationException) {
    }
    $this->assertSame('Renamed', $role->fresh()->name);
    $this->expectException(HttpException::class);
    $writer->role($root, ['name' => 'Forbidden', 'version' => 1], CmsRole::query()->where('handle', 'editor')->firstOrFail());
  }

  #[Test]
  public function assigned_roles_cannot_be_deleted_and_last_admin_cannot_be_demoted(): void
  {
    $user = $this->user();
    $site = $this->site();
    $role = $this->role(['cms.pages.read']);
    $this->assign($user, $site, $role);
    $root = User::query()->where('role', 'super_admin')->firstOrFail();
    try {
    app(WriteCmsAccess::class)->deleteRole($root, $role, 1);
    $this->fail();
    } catch (ValidationException) {
    }
    $this->expectException(ValidationException::class);
    app(WriteCmsAccess::class)->user($root, $root, ['role' => 'editor', 'is_active' => false, 'site_ids' => []]);
  }

  #[Test]
  public function personal_tokens_are_rechecked_after_role_revocation(): void
  {
    $user = $this->user();
    $site = $this->site();
    $role = $this->role(['cms.pages.read', 'cms.pages.publish']);
    $this->assign($user, $site, $role);
    $page = Page::query()->create(['site_id' => $site->id, 'title' => 'Page', 'status' => 'draft']);
    $issued = app(CmsApiTokenIssuer::class)->issue('Synthetic delegation', $user, ['content.publish'], 'personal', [$site->id]);
    Route::middleware(['web', RequireInternalApiToken::class, 'internal-api.capability:content.publish'])->post('/acl-api/pages/{page}/publish', fn () => response()->json(['ok' => true]))->name('internal-content-api.pages.publish');
    $this->withToken($issued->plainToken)->postJson('/acl-api/pages/'.$page->id.'/publish')->assertOk();
    DB::table('wbcms_acl_role_permissions')->where('role_id', $role->id)->where('permission', 'cms.pages.publish')->delete();
    $this->withToken($issued->plainToken)->postJson('/acl-api/pages/'.$page->id.'/publish')->assertForbidden();
  }

  #[Test]
  public function api_transport_capabilities_preserve_separate_native_operation_grants(): void
  {
    $user = $this->user();
    $site = $this->site();
    $role = $this->role(['cms.pages.read', 'cms.pages.create']);
    $this->assign($user, $site, $role);
    $policy = app(PersonalApiTokenPolicy::class);
    $this->assertTrue($policy->canUse($user, 'content.apply'));
    $this->assertFalse($policy->canUse($user, 'content.publish'));
    $issued = app(CmsApiTokenIssuer::class)->issue('Create-only delegation', $user, ['content.apply'], 'personal', [$site->id]);
    Route::middleware(['web', RequireInternalApiToken::class, 'internal-api.capability:content.apply'])->post('/acl-api/content/apply', function (Request $request) use ($site) {
      app(CmsResourcePolicy::class)->authorizePlan(['mode' => $request->input('mode'), 'site' => ['id' => $site->id]]);

      return response()->json(['ok' => true]);
    })->name('internal-content-api.content.apply');
    $this->withToken($issued->plainToken)->postJson('/acl-api/content/apply', ['site_id' => $site->id, 'mode' => 'create_draft_page'])->assertOk();
    $this->withToken($issued->plainToken)->postJson('/acl-api/content/apply', ['site_id' => $site->id, 'mode' => 'replace_existing_draft_page'])->assertForbidden();
    $this->assign($user, $site, $this->role(['cms.pages.read', 'cms.pages.archive']));
    $this->assertTrue($policy->canUse($user, 'content.publish'));
    $this->assertFalse(app(CmsAuthorization::class)->allows($user, 'cms.pages.publish', $site->id));
    $this->withToken($issued->plainToken)->postJson('/acl-api/content/apply', ['site_id' => $site->id, 'mode' => 'create_draft_page'])->assertForbidden();
  }

  #[Test]
  public function plugin_grants_require_active_site_delegable_definitions(): void
  {
    $registry = new PluginRegistry(['appointments' => true]);
    $registry->register(PluginDefinition::make('appointments')->permissions([PluginPermission::make('appointments.view')->siteDelegable()]));
    $this->app->instance(PluginRegistry::class, $registry);
    $user = $this->user();
    $site = $this->site();
    $this->assign($user, $site, $this->role(['appointments.view']));
    $resolver = app(PluginAccessResolver::class);
    $this->assertTrue($resolver->canAccessPluginPermission($user, 'appointments.view'));
    $this->assertSame([$site->id], $resolver->permittedSiteIds($user, 'appointments.view')->all());
    $this->app->instance(PluginRegistry::class, new PluginRegistry(['appointments' => false]));
    $this->assertFalse($resolver->canAccessPluginPermission($user, 'appointments.view'));
  }

  #[Test]
  public function composite_plans_check_every_operation_before_writing(): void
  {
    $user = $this->user();
    $site = $this->site();
    $this->assign($user, $site, $this->role(['cms.pages.read', 'cms.pages.create']));
    request()->setUserResolver(fn () => $user);
    $this->expectException(HttpException::class);
    app(CmsResourcePolicy::class)->authorizePlan(['mode' => 'create_draft_page', 'site' => ['id' => $site->id], 'navigation_menus' => [['handle' => 'header']]]);
  }

  #[Test]
  public function seo_in_nested_payloads_cannot_bypass_operation_grants(): void
  {
    $user = $this->user();
    $site = $this->site();
    $this->assign($user, $site, $this->role(['cms.pages.read', 'cms.pages.update-draft']));
    $this->expectException(HttpException::class);
    app(CmsResourcePolicy::class)->authorizeFields(['blocks' => [['translations' => ['seo_keywords' => 'hidden']]]], $user, $site->id);
  }

  #[Test]
  public function built_in_site_code_rights_are_preserved_but_not_delegable_to_copies(): void
  {
    $user = $this->user('site_admin');
    $site = $this->site();
    $user->sites()->attach($site->id);
    $acl = app(CmsAuthorization::class);
    $this->assertTrue($acl->allows($user, 'cms.legacy.site-assets.write', $site->id));
    $editor = $this->user();
    $editor->sites()->attach($site->id);
    $this->assertTrue($acl->allows($editor, 'cms.legacy.site-assets.read', $site->id));
    $this->assertFalse($acl->allows($editor, 'cms.legacy.site-assets.write', $site->id));
    $catalog = app(PermissionCatalog::class)->editable();
    $this->assertArrayNotHasKey('cms.legacy.site-assets.write', $catalog);
    $this->assertTrue($acl->usesLegacyRoles($editor));
    $this->assign($editor, $site, $this->role(['cms.media.read']));
    $this->assertFalse($acl->usesLegacyRoles($editor));
  }

  #[Test]
  public function real_role_routes_validate_and_save_only_for_super_admins(): void
  {
    $this->withoutMiddleware([RedirectIfNotInstalled::class]);
    $root = $this->user('super_admin');
    $this->actingAs($root)->post('/webadmin/access/roles', ['name' => 'Reader', 'permissions' => ['cms.panel.access', 'cms.pages.read']])->assertRedirect();
    $this->assertDatabaseHas('wbcms_acl_roles', ['name' => 'Reader', 'is_protected' => false]);
    $reader = CmsRole::where('name', 'Reader')->firstOrFail();
    $editUrl = '/webadmin/access/roles/'.$reader->id.'/edit';
    $this->get($editUrl)->assertOk()->assertSee('value="cms.pages.read" checked', false);
    $this->from($editUrl)->put('/webadmin/access/roles/'.$reader->id, ['name' => '', 'version' => $reader->version])->assertSessionHasErrors('name');
    $this->get($editUrl)->assertOk()->assertDontSee('value="cms.pages.read" checked', false);
    $this->actingAs($root)->post('/webadmin/access/roles', ['name' => 'Invalid', 'permissions' => ['cms.pages.read']])->assertSessionHasErrors('permissions');
    $site = $this->site();
    $user = $this->user();
    $user->sites()->attach($site->id);
    $this->actingAs($user)->post('/webadmin/access/roles', ['name' => 'Escalation', 'permissions' => []])->assertForbidden();
    $this->assertDatabaseMissing('wbcms_acl_roles', ['name' => 'Escalation']);
  }

  #[Test]
  public function user_deletion_rechecks_authority_after_acquiring_the_acl_lock(): void
  {
    $root = $this->user('super_admin');
    $target = $this->user();
    $revoked = false;
    DB::connection()->beforeExecuting(function ($sql) use ($root, &$revoked): void {
      if (! $revoked && str_contains($sql, 'wbcms_acl_locks') && str_starts_with($sql, 'select')) {
        $revoked = true;
        DB::table('wbcms_acl_system_administrators')->where('user_id', $root->id)->delete();
      }
    });
    try {
      app(WriteCmsAccess::class)->deleteUser($root, $target);
      $this->fail('Deletion accepted revoked administrator authority.');
    } catch (HttpException $e) {
      $this->assertSame(403, $e->getStatusCode());
    }
    $this->assertNotNull($target->fresh());
    $this->assertDatabaseMissing('wbcms_acl_events', ['action' => 'user.deleted']);
  }

  #[Test]
  public function self_deletion_rechecks_account_activity_after_acquiring_the_acl_lock(): void
  {
    $user = $this->user();
    $deactivated = false;
    DB::connection()->beforeExecuting(function ($sql) use ($user, &$deactivated): void {
      if (! $deactivated && str_contains($sql, 'wbcms_acl_locks') && str_starts_with($sql, 'select')) {
        $deactivated = true;
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
      }
    });
    try {
      app(WriteCmsAccess::class)->deleteUser($user, $user);
      $this->fail('Deletion accepted a deactivated account.');
    } catch (HttpException $e) {
      $this->assertSame(403, $e->getStatusCode());
    }
    $this->assertNotNull($user->fresh());
    $this->assertDatabaseMissing('wbcms_acl_events', ['action' => 'user.deleted']);
  }

  #[Test]
  public function user_form_keeps_assignments_without_panel_access_and_after_validation_errors(): void
  {
    $this->withoutMiddleware([RedirectIfNotInstalled::class]);
    $root = $this->user('super_admin');
    $user = $this->user();
    $site = $this->site();
    $role = app(WriteCmsAccess::class)->role($root, ['name' => 'Paused role', 'permissions' => []]);
    $this->assign($user, $site, $role);
    $this->assertFalse($user->canAccessAdmin());
    $this->actingAs($root)->get('/webadmin/users/'.$user->id.'/edit')->assertOk()
      ->assertSee('name="site_ids[]" value="'.$site->id.'" checked', false);
    $this->from('/webadmin/users/'.$user->id.'/edit')->put('/webadmin/users/'.$user->id, [
      'name' => $user->name, 'email' => $root->email, 'role' => 'editor', 'is_active' => true,
      'site_ids' => [(string) $site->id], 'site_roles' => [$site->id => (string) $role->id],
    ])->assertSessionHasErrors('email');
    $this->get('/webadmin/users/'.$user->id.'/edit')->assertOk()
      ->assertSee('name="site_ids[]" value="'.$site->id.'" checked', false);
    $this->assertDatabaseHas('wbcms_site_user', ['user_id' => $user->id, 'site_id' => $site->id, 'role_id' => $role->id]);
  }

  #[Test]
  public function reader_panel_controls_follow_the_same_operation_grants(): void
  {
    $this->withoutMiddleware([RedirectIfNotInstalled::class]);
    $user = $this->user();
    $site = $this->site();
    $this->assign($user, $site, $this->role(['cms.pages.read']));
    $locale = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $page = Page::create(['site_id' => $site->id, 'title' => 'Read-only draft', 'status' => 'draft']);
    $this->actingAs($user)->get('/webadmin')->assertOk()
      ->assertDontSee('href="'.route('admin.pages.create').'"', false)
      ->assertDontSee('href="'.route('admin.shared-slots.index').'"', false)
      ->assertDontSee('href="'.route('admin.media.index').'"', false);
    $this->get('/webadmin/pages?site='.$site->id)->assertOk()
      ->assertSee('Read-only draft')
      ->assertDontSee('aria-label="Delete page"', false)
      ->assertDontSee('>New Page</a>', false);
    $this->get('/webadmin/pages/'.$page->id.'/edit')->assertOk()
      ->assertSee('name="title" disabled', false)
      ->assertSee(__('webblocks-cms::acl.edit_locked'));
  }

  #[Test]
  public function role_panel_renders_copy_and_effective_permissions(): void
  {
    $root = $this->user('super_admin');
    $site = $this->site();
    $controller = app(CmsRoleController::class);
    request()->setUserResolver(fn () => $root);
    $this->actingAs($root);
    $view = $controller->create(Request::create('/webadmin/access/roles/create'), app(CmsRoleQuery::class));
    $html = $view->render();
    $this->assertStringContainsString('name="permissions[]"', $html);
    $this->assertStringNotContainsString('cms.legacy-api.', $html);
    $this->assertStringContainsString('Create drafts', $html);
  }
}
