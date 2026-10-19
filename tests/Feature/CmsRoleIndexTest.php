<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Tests\TestCase;

class CmsRoleIndexTest extends TestCase
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

  protected function setUp(): void
  {
    parent::setUp();
    $this->withoutMiddleware([RedirectIfNotInstalled::class]);
    $this->actingAs(User::forceCreate(['name' => 'Synthetic administrator', 'email' => 'admin@example.test', 'password' => 'unused', 'role' => 'super_admin', 'is_active' => true]));
  }

  private function role(string $name, string $description = ''): CmsRole
  {
    return CmsRole::create(['name' => $name, 'handle' => 'custom-'.str($name)->slug(), 'description' => $description, 'is_protected' => false]);
  }

  #[Test]
  public function the_initial_screen_has_a_real_empty_history_and_protected_role_modals(): void
  {
    $response = $this->get('/webadmin/access/roles')->assertOk()
      ->assertViewHas('totalCount', 2)->assertViewHas('roles', fn ($roles) => $roles->total() === 2)
      ->assertSee(__('webblocks-cms::acl.history_empty'))
      ->assertDontSee('id="acl-event-', false);
    $editor = CmsRole::where('handle', 'editor')->firstOrFail();
    $response->assertSee('data-wb-target="#acl-role-'.$editor->id.'"', false)
      ->assertSee('id="acl-role-'.$editor->id.'"', false)
      ->assertDontSee(route('admin.access.roles.edit', $editor), false)
      ->assertSee(route('admin.access.roles.create', ['copy' => $editor->id]), false);
  }

  #[Test]
  public function role_search_and_type_filters_keep_total_and_filtered_counts_distinct(): void
  {
    $desk = $this->role('News desk', 'Daily editorial team');
    $this->role('Media desk', 'Image editors');
    $this->get('/webadmin/access/roles?q=Daily&type=custom')->assertOk()
      ->assertViewHas('totalCount', 4)
      ->assertViewHas('roles', fn ($roles) => $roles->total() === 1 && $roles->first()->is($desk));
    $this->get('/webadmin/access/roles?type=protected')->assertOk()
      ->assertViewHas('roles', fn ($roles) => $roles->total() === 2 && $roles->every(fn ($role) => $role->is_protected));
    $this->get('/webadmin/access/roles?q=0')->assertOk()->assertViewHas('roles', fn ($roles) => $roles->total() === 0);
    $this->get('/webadmin/access/roles?q=no-matching-role')->assertOk()
      ->assertSee(__('webblocks-cms::acl.roles_empty'))
      ->assertViewHas('roles', fn ($roles) => $roles->total() === 0);
  }

  #[Test]
  public function role_and_history_pagination_are_independent_and_recover_out_of_range_pages(): void
  {
    SystemSetting::create(['key' => SystemSettings::ADMIN_LISTING_PER_PAGE, 'value' => '2']);
    foreach (range(1, 5) as $index) {
      $this->role('Team '.$index);
      DB::table('wbcms_acl_events')->insert(['action' => 'role.saved', 'actor_id' => auth()->id(), 'after_value' => 'Team '.$index, 'created_at' => now()]);
    }
    $this->get('/webadmin/access/roles?roles_page=2&history_page=3')->assertOk()
      ->assertViewHas('roles', fn ($roles) => $roles->currentPage() === 2 && $roles->count() === 2 && $roles->total() === 7)
      ->assertViewHas('history', fn ($history) => $history->currentPage() === 3 && $history->count() === 1 && $history->total() === 5)
      ->assertSee('history_page=3', false)->assertSee('roles_page=2', false)
      ->assertSee('data-wb-target="#acl-event-', false);
    $this->get('/webadmin/access/roles?type=custom&roles_page=99&history_page=2')
      ->assertRedirect('/webadmin/access/roles?type=custom&roles_page=3&history_page=2');
  }

  #[Test]
  public function filters_are_validated_and_non_administrators_cannot_read_the_screen(): void
  {
    $this->getJson('/webadmin/access/roles?type=invalid')->assertUnprocessable()->assertJsonValidationErrors('type');
    $this->getJson('/webadmin/access/roles?roles_page=0')->assertUnprocessable()->assertJsonValidationErrors('roles_page');
    $this->actingAs(User::forceCreate(['name' => 'Unassigned user', 'email' => 'reader@example.test', 'password' => 'unused', 'role' => 'editor', 'is_active' => true]));
    $this->get('/webadmin/access/roles')->assertForbidden();
  }
}
