<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;

class AdminLookupMemoTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function actor(string $role): User
  {
    if (! class_exists(User::class)) {
      class_alias(MaintenanceTestUser::class, User::class);
    }

    return $this->cmsActor(User::forceCreate(['name' => 'Synthetic operator', 'email' => $role.'@example.test', 'password' => 'unused', 'role' => $role, 'is_active' => true]));
  }

  #[Test]
  public function repeated_access_inputs_are_bounded_without_sharing_mutable_results_or_actors(): void
  {
    $admin = $this->actor('super_admin');
    $editor = $this->actor('editor');
    $site = Site::create(['name' => 'Northstar', 'handle' => 'northstar']);
    $other = Site::create(['name' => 'Harbor', 'handle' => 'harbor']);
    $role = CmsRole::create(['name' => 'Reader', 'handle' => 'custom-reader']);
    foreach (['cms.panel.access', 'cms.pages.read'] as $permission) {
      DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $role->id, 'permission' => $permission]);
    }
    $editor->sites()->attach($site, ['role_id' => $role->id]);
    $acl = app(CmsAuthorization::class);
    $locale = app(AdminLocaleResolver::class);
    $this->assertTrue($acl->allows($admin, 'cms.pages.read', $other->id));
    $this->assertTrue($acl->allows($editor, 'cms.pages.read', $site->id));
    $this->assertFalse($acl->allows($editor, 'cms.pages.read', $other->id));
    $this->assertFalse($acl->allows($editor, 'cms.pages.delete', $site->id));
    $this->assertFalse($acl->usesLegacyRoles($editor));
    $this->assertTrue($locale->userPreferencesAvailable());
    $acl->siteIds($editor)->push($other->id);
    $this->assertSame([], $this->queries(function () use ($acl, $locale, $admin, $editor, $site, $other): void {
      for ($i = 0; $i < 20; $i++) {
        $this->assertTrue($acl->allows($admin, 'cms.pages.read', $other->id));
        $this->assertTrue($acl->allows($editor, 'cms.pages.read', $site->id));
        $this->assertFalse($acl->allows($editor, 'cms.pages.read', $other->id));
        $this->assertFalse($acl->allows($editor, 'cms.pages.delete', $site->id));
        $this->assertFalse($acl->usesLegacyRoles($editor));
        $this->assertSame([$site->id], $acl->siteIds($editor)->all());
        $this->assertTrue($locale->userPreferencesAvailable());
      }
    }));
    $editor->is_active = false;
    $this->assertFalse($acl->allows($editor, 'cms.pages.read', $site->id));
    $this->assertTrue($acl->siteIds($editor)->isEmpty());
    $admin->admin_locale = 'tr';
    $editor->admin_locale = 'de';
    $this->assertSame('tr', $locale->locale($admin));
    $this->assertSame('de', $locale->locale($editor));
  }

  #[Test]
  public function raw_grant_membership_writes_rollback_requests_and_worker_scopes_refresh_access(): void
  {
    $actor = $this->actor('editor');
    $site = Site::create(['name' => 'Northstar', 'handle' => 'northstar']);
    $role = CmsRole::create(['name' => 'Reader', 'handle' => 'custom-reader']);
    DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $role->id, 'permission' => 'cms.panel.access']);
    $actor->sites()->attach($site, ['role_id' => $role->id]);
    $acl = app(CmsAuthorization::class);
    $this->assertFalse($acl->allows($actor, 'cms.pages.read', $site->id));
    DB::beginTransaction();
    DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $role->id, 'permission' => 'cms.pages.read']);
    $this->assertTrue($acl->allows($actor, 'cms.pages.read', $site->id));
    DB::rollBack();
    $this->assertFalse($acl->allows($actor, 'cms.pages.read', $site->id));
    DB::table('wbcms_acl_system_administrators')->insert(['user_id' => $actor->id]);
    $this->assertTrue($acl->isSuperAdmin($actor));
    DB::table('wbcms_acl_system_administrators')->where('user_id', $actor->id)->delete();
    $this->assertFalse($acl->isSuperAdmin($actor));
    DB::table('wbcms_site_user')->where('user_id', $actor->id)->delete();
    $this->assertTrue($acl->siteIds($actor)->isEmpty());
    app()->instance('request', Request::create('/next'));
    $this->assertNotEmpty($this->queries(fn () => $acl->siteIds($actor)));
    app()->forgetScopedInstances();
    $this->assertNotEmpty($this->queries(fn () => $acl->siteIds($actor)));
  }

  #[Test]
  public function schema_results_are_connection_scoped_and_ddl_invalidates_negative_results(): void
  {
    $acl = app(CmsAuthorization::class);
    $locale = app(AdminLocaleResolver::class);
    $original = DB::getDefaultConnection();
    config(['database.connections.other-admin' => config('database.connections.sqlite')]);
    DB::connection('other-admin')->getSchemaBuilder()->create('users', function ($table): void {
      $table->id();
    });
    $this->assertTrue($acl->ready());
    $this->assertTrue($locale->userPreferencesAvailable());
    try {
      DB::setDefaultConnection('other-admin');
      $this->assertFalse($acl->ready());
      $this->assertFalse($locale->userPreferencesAvailable());
      $this->assertSame([], $this->queries(function () use ($acl, $locale): void {
        $this->assertFalse($acl->ready());
        $this->assertFalse($locale->userPreferencesAvailable());
      }));
    } finally {
      DB::setDefaultConnection($original);
    }
    $this->assertTrue($acl->ready());
    $this->assertTrue($locale->userPreferencesAvailable());
    Schema::rename('wbcms_acl_roles', 'temporarily_unavailable_roles');
    $this->assertFalse($acl->ready());
    Schema::rename('temporarily_unavailable_roles', 'wbcms_acl_roles');
    $this->assertTrue($acl->ready());
    Schema::table('users', fn ($table) => $table->renameColumn('admin_locale', 'temporary_locale'));
    $this->assertFalse($locale->userPreferencesAvailable());
    Schema::table('users', fn ($table) => $table->renameColumn('temporary_locale', 'admin_locale'));
    $this->assertTrue($locale->userPreferencesAvailable());
  }

  private function queries(callable $work): array
  {
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
      $work();

      return DB::getQueryLog();
    } finally {
      DB::disableQueryLog();
    }
  }
}
