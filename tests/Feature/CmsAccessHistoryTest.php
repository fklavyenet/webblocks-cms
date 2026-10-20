<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\Access\WriteCmsAccess;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Queries\Access\CmsRoleQuery;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;

class CmsAccessHistoryTest extends TestCase
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
    if (! class_exists(User::class)) {
      class_alias(MaintenanceTestUser::class, User::class);
    }
    $this->withoutMiddleware([RedirectIfNotInstalled::class]);
    $this->actingAs($this->user('Administrator', 'super_admin'));
  }

  private function user(string $name, string $role = 'editor'): User
  {
    return User::forceCreate(['name' => $name, 'email' => str($name)->slug().'@example.test', 'password' => 'unused', 'role' => $role, 'is_active' => true]);
  }

  private function event(array $data = []): int
  {
    return DB::table('wbcms_acl_events')->insertGetId(['action' => 'role.saved', 'actor_id' => auth()->id(), 'created_at' => now(), ...$data]);
  }

  private function assertHistory(array $filters, array $ids): void
  {
    $this->get(route('admin.access.roles.index', $filters))->assertOk()
      ->assertViewHas('history', fn ($history) => $history->pluck('id')->all() === array_reverse($ids) && $history->total() === count($ids));
  }

  #[Test]
  public function new_accounts_record_absent_previous_states_and_failed_changes_leave_no_events(): void
  {
    $writer = app(WriteCmsAccess::class);
    $account = (new User)->mergeFillable(['name', 'email', 'password']);
    $subject = $writer->user(auth()->user(), $account, ['name' => 'New account', 'email' => 'new-account@example.test', 'password' => 'unused', 'role' => 'editor', 'is_active' => true, 'site_ids' => []]);
    foreach (['user.activity.changed' => 'active', 'user.system_role.changed' => 'site_roles'] as $action => $state) {
      $event = DB::table('wbcms_acl_events')->where('user_id', $subject->id)->where('action', $action)->sole();
      $this->assertNull($event->before_value);
      $this->assertSame($state, $event->after_value);
    }
    $before = DB::table('wbcms_acl_events')->count();
    $actor = auth()->user();
    try {
      $writer->user($actor, $actor, ['name' => $actor->name, 'email' => $actor->email, 'role' => 'super_admin', 'is_active' => false]);
      $this->fail('The last active administrator cannot be disabled.');
    } catch (ValidationException) {
      $this->assertSame($before, DB::table('wbcms_acl_events')->count());
      $this->assertTrue((bool) $actor->fresh()->is_active);
      $this->assertDatabaseHas('wbcms_acl_system_administrators', ['user_id' => $actor->id]);
    }
  }

  #[Test]
  public function account_and_system_membership_transitions_have_filterable_before_and_after_values(): void
  {
    $subject = $this->user('Subject');
    $write = app(WriteCmsAccess::class);
    $data = ['name' => $subject->name, 'email' => $subject->email, 'role' => 'editor', 'is_active' => true, 'site_ids' => []];
    $write->user(auth()->user(), $subject, [...$data, 'is_active' => false]);
    $write->user(auth()->user(), $subject, $data);
    $write->user(auth()->user(), $subject, [...$data, 'role' => 'super_admin']);
    $write->user(auth()->user(), $subject, $data);
    $activity = DB::table('wbcms_acl_events')->where('action', 'user.activity.changed')->orderBy('id')->get();
    $system = DB::table('wbcms_acl_events')->where('action', 'user.system_role.changed')->orderBy('id')->get();
    $this->assertSame([['active', 'inactive'], ['inactive', 'active']], $activity->map(fn ($row) => [$row->before_value, $row->after_value])->all());
    $this->assertSame([['site_roles', 'super_admin'], ['super_admin', 'site_roles']], $system->map(fn ($row) => [$row->before_value, $row->after_value])->all());
    $this->assertHistory(['history_user' => $subject->id, 'history_action' => 'user.activity.changed'], $activity->pluck('id')->all());
    $this->assertHistory(['history_action' => 'user.system_role.changed'], $system->pluck('id')->all());
    $before = DB::table('wbcms_acl_events')->count();
    $write->user(auth()->user(), $subject, $data);
    $this->assertSame($before + 1, DB::table('wbcms_acl_events')->count(), 'Unchanged states must not emit transition events.');
    foreach (['en', 'de', 'es', 'fr', 'it', 'tr'] as $locale) {
      config()->set('app.locale', $locale);
      $this->get(route('admin.access.roles.index', ['history_action' => 'user.activity.changed']))->assertOk()
        ->assertSee(trans('webblocks-cms::acl.events.user_activity_changed', [], $locale))
        ->assertSee(trans('webblocks-cms::acl.event_values.inactive', [], $locale))
        ->assertDontSee('webblocks-cms::acl.', false);
    }
    $this->expectException(ValidationException::class);
    $root = auth()->user();
    $write->user($root, $root, ['name' => $root->name, 'email' => $root->email, 'role' => 'editor', 'is_active' => true]);
  }

  #[Test]
  public function missing_site_validation_is_translated_on_both_user_write_routes(): void
  {
    $subject = $this->user('Subject');
    foreach (['en', 'de', 'tr', 'es', 'it', 'fr'] as $locale) {
      config()->set('app.locale', $locale);
      foreach (['editor', 'site_admin'] as $role) {
        $data = ['name' => 'Fixture', 'email' => 'new@example.test', 'password' => 'FixtureOnly987!', 'password_confirmation' => 'FixtureOnly987!', 'role' => $role, 'is_active' => true, 'site_ids' => []];
        $expected = trans('webblocks-cms::acl.site_required', [], $locale);
        if ($locale !== 'en') {
          $this->assertNotSame(trans('webblocks-cms::acl.site_required', [], 'en'), $expected);
        }
        $this->postJson(route('admin.users.store'), $data)->assertUnprocessable()->assertJsonPath('errors.site_ids.0', $expected);
        $this->putJson(route('admin.users.update', $subject), [...$data, 'email' => $subject->email])->assertUnprocessable()->assertJsonPath('errors.site_ids.0', $expected);
      }
    }
    $this->assertDatabaseCount('users', 2);
  }

  #[Test]
  public function user_matches_actor_or_target_and_combines_with_site_role_and_action(): void
  {
    $subject = $this->user('Subject');
    $other = $this->user('Other');
    $site = Site::create(['name' => 'Northstar', 'handle' => 'northstar']);
    $role = CmsRole::where('handle', 'editor')->firstOrFail();
    $target = $this->event(['action' => 'assignment.changed', 'user_id' => $subject->id, 'site_id' => $site->id, 'after_value' => (string) $role->id]);
    $actor = $this->event(['actor_id' => $subject->id, 'user_id' => $other->id, 'site_id' => $site->id, 'role_id' => $role->id]);
    $this->event(['actor_id' => $other->id, 'user_id' => $other->id, 'site_id' => $site->id, 'role_id' => $role->id]);
    $this->event(['user_id' => $subject->id, 'role_id' => $role->id]);
    $this->assertHistory(['history_user' => $subject->id, 'history_site' => $site->id], [$target, $actor]);
    $this->assertHistory(['history_user' => $subject->id, 'history_site' => $site->id, 'history_role' => $role->id, 'history_action' => 'assignment.changed'], [$target]);
    $this->assertHistory(['history_user' => $subject->id, 'history_action' => 'permission.granted'], []);
  }

  #[Test]
  public function role_matches_direct_events_and_both_sides_of_real_assignment_changes(): void
  {
    $actor = auth()->user();
    $subject = $this->user('Subject');
    $site = Site::create(['name' => 'Northstar', 'handle' => 'northstar']);
    $writer = app(WriteCmsAccess::class);
    $first = $writer->role($actor, ['name' => 'Desk', 'permissions' => ['cms.pages.read']]);
    $second = CmsRole::where('handle', 'editor')->firstOrFail();
    $data = ['name' => $subject->name, 'email' => $subject->email, 'role' => 'editor', 'is_active' => true, 'site_ids' => [$site->id], 'site_roles' => [$site->id => $first->id]];
    $writer->user($actor, $subject, $data);
    $writer->user($actor, $subject, [...$data, 'site_roles' => [$site->id => $second->id]]);
    $writer->user($actor, $subject, [...$data, 'site_ids' => []]);
    $assignments = DB::table('wbcms_acl_events')->where('action', 'assignment.changed')->pluck('id')->all();
    $direct = DB::table('wbcms_acl_events')->where('role_id', $first->id)->pluck('id')->all();
    $this->event(['action' => 'role.saved', 'before_value' => (string) $first->id]);
    $this->event(['action' => 'user.access.saved', 'after_value' => (string) $first->id]);
    $this->assertHistory(['history_role' => $first->id], [...$direct, $assignments[0], $assignments[1]]);
    $this->assertHistory(['history_role' => $second->id], [$assignments[1], $assignments[2]]);
    $this->assertHistory(['history_role' => $first->id, 'history_action' => 'assignment.changed'], [$assignments[0], $assignments[1]]);
  }

  #[Test]
  public function deleted_references_remain_discoverable_and_filterable_without_changing_history(): void
  {
    $subject = $this->user('Removed user');
    $site = Site::create(['name' => 'Removed site', 'handle' => 'removed']);
    $role = CmsRole::create(['name' => 'Removed role', 'handle' => 'custom-removed']);
    $id = $this->event(['action' => 'assignment.changed', 'actor_id' => $subject->id, 'user_id' => $subject->id, 'site_id' => $site->id, 'before_value' => (string) $role->id]);
    $subject->delete();
    $site->delete();
    $role->delete();
    $before = DB::table('wbcms_acl_events')->get()->toJson();
    $filters = ['history_user' => $subject->id, 'history_site' => $site->id, 'history_role' => $role->id];
    $response = $this->get(route('admin.access.roles.index', $filters))->assertOk();
    foreach (['user' => $subject->id, 'site' => $site->id, 'role' => $role->id] as $kind => $reference) {
      $this->assertSame(__('webblocks-cms::acl.history_unavailable', ['id' => $reference]), $response->viewData('historyOptions')[$kind][$reference]);
    }
    $this->assertHistory($filters, [$id]);
    $this->get(route('admin.access.roles.index', ['history_user' => 999999]))->assertOk()
      ->assertSee(__('webblocks-cms::acl.history_no_matches'))
      ->assertDontSee(__('webblocks-cms::acl.history_empty'));
    $this->assertSame($before, DB::table('wbcms_acl_events')->get()->toJson());
  }

  #[Test]
  public function every_event_action_can_be_selected_independently(): void
  {
    $ids = [];
    foreach (CmsRoleQuery::HISTORY_ACTIONS as $action) {
      $ids[$action] = $this->event(['action' => $action]);
    }
    foreach ($ids as $action => $id) {
      $this->assertHistory(['history_action' => $action], [$id]);
    }
    $this->assertHistory([], array_values($ids));
  }

  #[Test]
  public function forms_reset_only_their_own_filters_and_preserve_independent_pagination(): void
  {
    SystemSetting::create(['key' => SystemSettings::ADMIN_LISTING_PER_PAGE, 'value' => '1']);
    foreach (range(1, 3) as $index) {
      CmsRole::create(['name' => 'Desk '.$index, 'handle' => 'custom-desk-'.$index]);
      $this->event();
    }
    $filters = ['q' => 'Desk', 'type' => 'custom', 'roles_page' => 2, 'history_action' => 'role.saved', 'history_page' => 2];
    $response = $this->get(route('admin.access.roles.index', $filters))->assertOk()
      ->assertViewHas('roles', fn ($roles) => $roles->currentPage() === 2 && $roles->total() === 3)
      ->assertViewHas('history', fn ($history) => $history->currentPage() === 2 && $history->total() === 3);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $roleForm = '//form[.//input[@id="acl_roles_search"]]';
    $historyForm = '//form[.//select[@name="history_action"]]';
    foreach (['history_action' => 'role.saved', 'history_page' => '2'] as $name => $value) {
      $this->assertSame(1, $xpath->query($roleForm.'//input[@type="hidden"][@name="'.$name.'"][@value="'.$value.'"]')->length);
    }
    foreach (['q' => 'Desk', 'type' => 'custom', 'roles_page' => '2'] as $name => $value) {
      $this->assertSame(1, $xpath->query($historyForm.'//input[@type="hidden"][@name="'.$name.'"][@value="'.$value.'"]')->length);
    }
    $this->assertSame(0, $xpath->query($roleForm.'//input[@name="roles_page"]')->length);
    $this->assertSame(0, $xpath->query($historyForm.'//input[@name="history_page"]')->length);
    $this->assertSame(route('admin.access.roles.index', ['history_action' => 'role.saved', 'history_page' => 2]), $xpath->query($roleForm.'//a')->item(0)->getAttribute('href'));
    $this->assertSame(route('admin.access.roles.index', ['q' => 'Desk', 'type' => 'custom', 'roles_page' => 2]), $xpath->query($historyForm.'//a')->item(0)->getAttribute('href'));
    foreach (['user', 'site', 'role', 'action'] as $field) {
      $this->assertSame(1, $xpath->query('//label[@for="acl_history_'.$field.'"]')->length);
    }
    $this->get(route('admin.access.roles.index', [...$filters, 'history_page' => 99]))
      ->assertRedirect(route('admin.access.roles.index', [...$filters, 'history_page' => 3]));
    $response->assertSee('history_action=role.saved', false)->assertSee('roles_page=2', false);
  }

  #[Test]
  public function invalid_filters_are_rejected_and_access_remains_super_admin_only(): void
  {
    foreach (['history_user' => '0', 'history_site' => '-1', 'history_role' => 'text', 'history_action' => 'invalid'] as $field => $value) {
      $this->getJson(route('admin.access.roles.index', [$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    $this->getJson(route('admin.access.roles.index', ['history_user' => ['1']]))->assertUnprocessable()->assertJsonValidationErrors('history_user');
    $this->actingAs($this->user('Reader'));
    $this->get(route('admin.access.roles.index', ['history_user' => auth()->id()]))->assertForbidden();
  }

  #[Test]
  public function history_controls_are_translated_in_every_panel_language_and_escape_names(): void
  {
    $subject = $this->user('Subject');
    $subject->forceFill(['name' => '<script>unsafe()</script>'])->save();
    $this->event(['user_id' => $subject->id]);
    foreach (['en', 'de', 'es', 'fr', 'it', 'tr'] as $locale) {
      config()->set('app.locale', $locale);
      $this->get(route('admin.access.roles.index', ['history_user' => $subject->id]))->assertOk()
        ->assertSee(trans('webblocks-cms::acl.history_user', [], $locale))
        ->assertSee(trans('webblocks-cms::acl.history_all_action', [], $locale))
        ->assertSee(trans('webblocks-cms::acl.history_filters_help', [], $locale))
        ->assertDontSee('webblocks-cms::acl.', false)
        ->assertDontSee('<script>unsafe()</script>', false)
        ->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false);
    }
  }
}
