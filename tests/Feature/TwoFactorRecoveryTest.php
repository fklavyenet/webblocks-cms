<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use WebBlocks\Cms\Actions\Auth\ManageTwoFactor;
use WebBlocks\Cms\Actions\Auth\ResetTwoFactor;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Notifications\System\TwoFactorEmail;
use WebBlocks\Cms\Queries\PanelNotificationQuery;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Auth\EmailTwoFactorCodes;
use WebBlocks\Cms\Support\Auth\TwoFactorAccountState;
use WebBlocks\Cms\Support\Auth\TwoFactorAuthentication;
use WebBlocks\Cms\Support\Auth\TwoFactorSession;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;

class TwoFactorRecoveryTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    if (! class_exists(User::class)) {
      class_alias(MaintenanceTestUser::class, User::class);
    }
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('auth.providers.users.model', User::class);
    $app['config']->set('mail.default', 'smtp');
    $app['config']->set('mail.mailers.smtp.transport', 'smtp');
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function defineRoutes($router): void
  {
    $router->middleware('web')->get('/webadmin/recovery-probe', fn () => 'panel');
    $router->middleware('web')->post('/host-recovery-write', fn () => 'written');
    $router->middleware('web')->get('/host-recovery-probe', fn () => 'host');
    $router->middleware(['web', 'internal-api.token'])->get('/webadmin/api/recovery-probe', fn () => response()->json(['ok' => true]))->name('internal-content-api.recovery-probe');
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->withoutMiddleware(RedirectIfNotInstalled::class);
    Notification::fake();
    $this->mock(PanelNotificationQuery::class)->shouldReceive('forUser')->andReturn(null);
  }

  private function user(string $name, ?string $role = 'super_admin'): User
  {
    return User::forceCreate(['name' => $name, 'email' => strtolower($name).'@example.test', 'role' => $role, 'password' => Hash::make('fixture-password'), 'is_active' => true]);
  }

  private function latestCode(): string
  {
    return Notification::sent(new AnonymousNotifiable, TwoFactorEmail::class)->last()->code;
  }

  private function enroll(User $user): array
  {
    $generation = app(ManageTwoFactor::class)->start($user);
    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));
    $this->assertSame('sent', app(EmailTwoFactorCodes::class)->send($request, $user, 'setup', $user->email, app(TwoFactorSession::class)->setupBinding($user, $generation)));

    return app(ManageTwoFactor::class)->confirm($user, $generation, $this->latestCode(), $request->session()->get(EmailTwoFactorCodes::SESSION.'.setup'));
  }

  private function signedIn(User $user): void
  {
    $this->flushSession();
    $this->actingAs($user);
    $credential = app(TwoFactorAuthentication::class)->credential($user);
    $this->withSession([
      TwoFactorAccountState::SESSION => app(TwoFactorAccountState::class)->state($user)?->session_version ?? '',
      TwoFactorSession::VERIFIED => $credential ? app(TwoFactorSession::class)->fingerprint($user, $credential) : '',
    ]);
  }

  private function resetUrl(User $user): string
  {
    return route('admin.users.two-factor.reset', ['user' => $user->id]);
  }

  #[Test]
  public function panel_reset_requires_the_actors_password_and_factor_and_records_only_the_selected_audit_data(): void
  {
    $actor = $this->user('Actor');
    $target = $this->user('Target');
    $actorCodes = $this->enroll($actor);
    $targetCodes = $this->enroll($target);
    $oldRemember = $target->remember_token;
    $this->signedIn($actor);
    $this->post($this->resetUrl($target), ['reason' => 'Owner identity confirmed', 'current_password' => 'wrong', 'code' => $actorCodes[0]])
      ->assertSessionHasErrors('current_password')->assertSessionMissing('_old_input');
    $this->post($this->resetUrl($target), ['reason' => 'Owner identity confirmed', 'current_password' => 'fixture-password', 'code' => $targetCodes[0]])
      ->assertSessionHasErrors('code')->assertSessionMissing('_old_input');
    $this->assertNotNull(app(TwoFactorAuthentication::class)->credential($target));
    $this->assertDatabaseMissing('wbcms_acl_events', ['action' => 'user.two_factor.reset']);
    $this->post($this->resetUrl($target), ['reason' => 'Owner identity confirmed', 'current_password' => 'fixture-password', 'code' => $actorCodes[0], 'actor_id' => $target->id, 'source' => 'console'])
      ->assertRedirect(route('admin.users.edit', $target))->assertSessionHasNoErrors();
    $this->assertNull(app(TwoFactorAuthentication::class)->credential($target));
    $this->assertTrue(app(TwoFactorAuthentication::class)->required($target));
    $this->assertNotSame($oldRemember, $target->fresh()->remember_token);
    foreach (['recovery_codes', 'email_codes', 'enrollments', 'credentials'] as $table) {
      $this->assertDatabaseMissing('wbcms_two_factor_'.$table, ['user_id' => $target->id]);
    }
    $this->assertDatabaseHas('wbcms_acl_events', ['action' => 'user.two_factor.reset', 'actor_id' => $actor->id, 'user_id' => $target->id, 'source' => 'panel', 'reason' => 'Owner identity confirmed']);
    $this->assertFalse(app(ManageTwoFactor::class)->verify($actor, '', $actorCodes[0]));
    $this->assertNotNull(app(TwoFactorAuthentication::class)->credential($actor));
  }

  #[Test]
  public function self_reset_non_super_admins_unenrolled_actors_and_host_only_targets_are_denied(): void
  {
    $actor = $this->user('Actor');
    $target = $this->user('Target');
    $host = $this->user('Host', null);
    $this->signedIn($actor);
    $this->post($this->resetUrl($target), ['reason' => 'Test'])->assertForbidden();
    $codes = $this->enroll($actor);
    $this->signedIn($actor);
    $input = ['reason' => 'Test', 'current_password' => 'fixture-password', 'recovery_code' => $codes[0]];
    $this->post($this->resetUrl($actor), $input)->assertForbidden();
    $this->post($this->resetUrl($host), $input)->assertForbidden();
    $member = $this->user('Member', 'editor');
    $this->signedIn($member);
    $this->post($this->resetUrl($target), $input)->assertForbidden();
    $this->assertDatabaseMissing('wbcms_acl_events', ['action' => 'user.two_factor.reset']);
  }

  #[Test]
  public function reset_forms_use_the_users_screen_and_email_codes_return_to_the_correct_account(): void
  {
    $actor = $this->user('Actor');
    $target = $this->user('Target');
    $this->enroll($actor);
    $this->signedIn($actor);
    $this->get(route('admin.users.edit', $target))->assertOk()->assertSee($this->resetUrl($target))->assertDontSee('webblocks-cms::two-factor.');
    $this->post(route('webblocks.two-factor.send'), ['purpose' => 'action', 'tab' => 'user-reset', 'target_user_id' => $target->id])
      ->assertRedirect(route('admin.users.edit', $target));
    $this->post($this->resetUrl($target), ['reason' => 'Verified in person', 'current_password' => 'fixture-password', 'code' => $this->latestCode()])->assertSessionHasNoErrors();
    $this->get(route('admin.access.roles.index', ['history_action' => 'user.two_factor.reset']))->assertOk()->assertSee('Verified in person')->assertDontSee('acl.events.user_two_factor_reset');
  }

  #[Test]
  public function optional_accounts_must_reenroll_and_successful_confirmation_clears_only_the_reset_requirement(): void
  {
    $target = $this->user('Target');
    $this->assertFalse(app(TwoFactorAuthentication::class)->required($target));
    $this->assertTrue(app(ResetTwoFactor::class)->fromConsole($target, 'Mailbox recovered'));
    $version = app(TwoFactorAccountState::class)->state($target)->session_version;
    $this->post('/webadmin/login', ['email' => $target->email, 'password' => 'fixture-password'])->assertRedirect();
    $this->get('/webadmin/recovery-probe')->assertRedirect(route('webblocks.two-factor.show'));
    $this->post(route('webblocks.two-factor.start'), ['current_password' => 'fixture-password'])->assertSessionHasNoErrors();
    $this->post(route('webblocks.two-factor.confirm'), ['code' => $this->latestCode()])->assertOk();
    $this->assertFalse(app(TwoFactorAuthentication::class)->required($target));
    $this->assertFalse((bool) app(TwoFactorAccountState::class)->state($target)->enrollment_required);
    $this->assertSame($version, app(TwoFactorAccountState::class)->state($target)->session_version);
    $this->get('/webadmin/recovery-probe')->assertOk();
  }

  #[Test]
  public function previously_open_sessions_are_rejected_on_host_routes_and_unsafe_json_requests(): void
  {
    $target = $this->user('Target');
    $this->enroll($target);
    $this->signedIn($target);
    app(ResetTwoFactor::class)->fromConsole($target, 'Lost mailbox and codes');
    $this->postJson('/host-recovery-write')->assertUnauthorized()->assertJsonPath('code', 'cms_session_revoked');
    $this->assertGuest('web');
    // A different old session also has no matching account version.
    $this->actingAs($target)->withSession([TwoFactorAccountState::SESSION => 'old-session']);
    $this->get('/host-recovery-probe')->assertRedirect(route('webblocks.auth.login'));
    $this->assertGuest('web');
  }

  #[Test]
  public function pending_logins_and_remember_cookies_cannot_survive_reset(): void
  {
    $target = $this->user('Target');
    $codes = $this->enroll($target);
    $this->post('/webadmin/login', ['email' => $target->email, 'password' => 'fixture-password', 'remember' => 1]);
    app(ResetTwoFactor::class)->fromConsole($target, 'Lost factor');
    $this->post(route('webblocks.two-factor.verify'), ['recovery_code' => $codes[0]])->assertRedirect(route('webblocks.auth.login'));
    $this->assertGuest('web');
    $target->forceFill(['remember_token' => 'old-remember-token'])->save();
    $cookie = $target->id.'|old-remember-token|'.$target->getAuthPassword();
    app(ResetTwoFactor::class)->fromConsole($target, 'Reset again');
    $this->flushSession();
    Auth::guard('web')->forgetUser();
    $this->withCookie(Auth::guard('web')->getRecallerName(), $cookie)->get('/webadmin/recovery-probe');
    $this->assertGuest('web');
  }

  #[Test]
  public function a_second_reset_revokes_sessions_that_survived_the_first_recovery_login(): void
  {
    $target = $this->user('Target');
    app(ResetTwoFactor::class)->fromConsole($target, 'First reset');
    $this->post('/webadmin/login', ['email' => $target->email, 'password' => 'fixture-password']);
    $first = session(TwoFactorAccountState::SESSION);
    $this->assertSame(app(TwoFactorAccountState::class)->state($target)->session_version, $first);
    app(ResetTwoFactor::class)->fromConsole($target, 'Second reset');
    $this->assertNotSame($first, app(TwoFactorAccountState::class)->state($target)->session_version);
    $this->get('/webadmin/recovery-probe')->assertRedirect(route('webblocks.auth.login'));
  }

  #[Test]
  public function the_console_command_recovers_the_only_super_admin_and_can_replace_an_inaccessible_mailbox(): void
  {
    $target = $this->user('Only');
    $this->enroll($target);
    $oldPassword = $target->password;
    $this->artisan('webblocks:two-factor:reset', ['user' => $target->email, '--email' => 'recovered@example.test', '--reason' => 'Owner verified through server access', '--force' => true])->assertSuccessful();
    $this->assertSame('recovered@example.test', $target->fresh()->email);
    $this->assertSame($oldPassword, $target->fresh()->password);
    $this->assertDatabaseHas('wbcms_acl_system_administrators', ['user_id' => $target->id]);
    $this->assertDatabaseCount('wbcms_acl_system_administrators', 1);
    $this->assertTrue(app(TwoFactorAuthentication::class)->required($target));
    $this->assertDatabaseHas('wbcms_acl_events', ['user_id' => $target->id, 'actor_id' => null, 'source' => 'console', 'action' => 'user.two_factor.recovered']);
    $this->assertNull(app(TwoFactorAuthentication::class)->credential($target));
  }

  #[Test]
  public function console_dry_run_cancellation_and_invalid_inputs_do_not_change_accounts_or_emit_events(): void
  {
    $target = $this->user('Target');
    $other = $this->user('Other');
    $host = $this->user('Host', null);
    $this->artisan('webblocks:two-factor:reset', ['user' => $target->id, '--reason' => 'Inspection', '--dry-run' => true])->assertSuccessful();
    $this->artisan('webblocks:two-factor:reset', ['user' => $target->id, '--reason' => 'Cancelled'])->expectsConfirmation(__('webblocks-cms::two-factor.cli_confirm', ['id' => $target->id]), 'no')->assertFailed();
    foreach ([['user' => 99999], ['user' => $host->id], ['user' => $target->id, '--email' => $other->email], ['user' => $target->id, '--email' => 'invalid'], ['user' => $target->id, '--reason' => '']] as $args) {
      $this->artisan('webblocks:two-factor:reset', [...['--reason' => 'Fixture', '--force' => true], ...$args])->assertFailed();
    }
    $this->assertDatabaseCount('wbcms_two_factor_account_states', 0);
    $this->assertDatabaseMissing('wbcms_acl_events', ['action' => 'user.two_factor.recovered']);
    $this->assertSame('target@example.test', $target->fresh()->email);
  }

  #[Test]
  public function resets_preserve_role_policy_and_token_authentication_and_do_not_invalidate_unrelated_sessions(): void
  {
    $target = $this->user('Target');
    $actor = $this->user('Actor');
    $this->enroll($actor);
    $this->signedIn($actor);
    DB::table('wbcms_two_factor_policy')->where('id', 1)->update(['require_super_admin' => true]);
    app(ResetTwoFactor::class)->fromConsole($target, 'Recovery');
    $this->get('/webadmin/recovery-probe')->assertOk();
    $this->assertTrue(app(TwoFactorAuthentication::class)->policy()['require_super_admin']);
    $this->actingAs($target)->withSession([TwoFactorAccountState::SESSION => 'stale']);
    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic client');
    $this->withToken($token->plainToken)->getJson('/webadmin/api/recovery-probe')->assertOk();
    $this->postJson('/host-recovery-write')->assertUnauthorized();
  }

  #[Test]
  public function reset_migrations_are_repeatable_and_keep_revocations_and_audit_evidence(): void
  {
    $target = $this->user('Target');
    app(ResetTwoFactor::class)->fromConsole($target, 'Retained evidence');
    $version = app(TwoFactorAccountState::class)->state($target)->session_version;
    foreach (['fresh', 'updates'] as $profile) {
      $migration = require dirname(__DIR__, 2).'/database/migrations/'.$profile.'/2026_10_27_170000_add_cms_two_factor_recovery.php';
      $migration->up();
      $migration->up();
      $migration->down();
    }
    $this->assertSame($version, app(TwoFactorAccountState::class)->state($target)->session_version);
    $this->assertTrue(app(TwoFactorAuthentication::class)->required($target));
    $this->assertDatabaseHas('wbcms_acl_events', ['reason' => 'Retained evidence']);
    $this->assertTrue(Schema::hasColumn('wbcms_acl_events', 'reason'));
  }

  #[Test]
  public function panel_replacement_addresses_are_validated_and_the_owner_must_confirm_the_new_mailbox(): void
  {
    $actor = $this->user('Actor');
    $target = $this->user('Target');
    $codes = $this->enroll($actor);
    $this->signedIn($actor);
    $input = ['reason' => 'Mailbox ownership checked', 'current_password' => 'fixture-password', 'recovery_code' => $codes[0]];
    $this->post($this->resetUrl($target), $input + ['recovery_email' => $actor->email])->assertSessionHasErrors('recovery_email')->assertSessionMissing('_old_input');
    $this->assertSame('target@example.test', $target->fresh()->email);
    $this->post($this->resetUrl($target), $input + ['recovery_email' => 'new-owner@example.test'])->assertSessionHasNoErrors();
    $this->assertSame('new-owner@example.test', $target->fresh()->email);
    $this->assertNull(app(TwoFactorAuthentication::class)->credential($target));
    $this->assertTrue(app(TwoFactorAuthentication::class)->required($target));
  }

  #[Test]
  public function a_failed_audit_write_rolls_back_credentials_codes_session_revocation_and_mailbox_changes(): void
  {
    $target = $this->user('Target');
    $this->enroll($target);
    $before = app(TwoFactorAuthentication::class)->credential($target);
    $remember = $target->fresh()->remember_token;
    DB::unprepared("CREATE TRIGGER reject_recovery_event BEFORE INSERT ON wbcms_acl_events BEGIN SELECT RAISE(ABORT, 'fixture audit failure'); END");
    try {
      app(ResetTwoFactor::class)->fromConsole($target, 'Must be atomic', 'changed@example.test');
      $this->fail('The audit write must fail.');
    } catch (QueryException) {
      $this->assertEquals($before, app(TwoFactorAuthentication::class)->credential($target));
      $this->assertSame($remember, $target->fresh()->remember_token);
      $this->assertSame('target@example.test', $target->fresh()->email);
      $this->assertDatabaseCount('wbcms_two_factor_account_states', 0);
      $this->assertDatabaseCount('wbcms_two_factor_recovery_codes', 10);
    }
  }

  #[Test]
  public function stale_super_admin_authority_is_rechecked_from_sql_before_any_reset(): void
  {
    $actor = $this->user('Actor');
    $target = $this->user('Target');
    $codes = $this->enroll($actor);
    $this->signedIn($actor);
    $this->assertTrue(app(CmsAuthorization::class)->isSuperAdmin($actor));
    DB::table('wbcms_acl_system_administrators')->where('user_id', $actor->id)->delete();
    $request = Request::create('/');
    $request->setUserResolver(fn () => $actor);
    $request->setLaravelSession(app('session.store'));
    try {
      app(ResetTwoFactor::class)->fromPanel($request, $target, ['reason' => 'Denied', 'current_password' => 'fixture-password', 'recovery_code' => $codes[0]]);
      $this->fail('Revoked SQL authority must not authorize a reset.');
    } catch (HttpException $exception) {
      $this->assertSame(403, $exception->getStatusCode());
      $this->assertDatabaseCount('wbcms_two_factor_account_states', 0);
      $this->assertDatabaseMissing('wbcms_acl_events', ['action' => 'user.two_factor.reset']);
    }
  }

  #[Test]
  public function noninteractive_console_recovery_requires_force_and_a_reason(): void
  {
    $target = $this->user('Target');
    $this->artisan('webblocks:two-factor:reset', ['user' => $target->id, '--reason' => 'Deliberate recovery', '--no-interaction' => true])->assertFailed();
    $this->assertDatabaseCount('wbcms_two_factor_account_states', 0);
    $this->artisan('webblocks:two-factor:reset', ['user' => $target->id, '--reason' => 'Deliberate recovery', '--no-interaction' => true, '--force' => true])->assertSuccessful();
    $this->assertDatabaseHas('wbcms_acl_events', ['user_id' => $target->id, 'reason' => 'Deliberate recovery']);
  }

  #[Test]
  public function host_management_gates_are_not_bypassed_by_cms_super_admin_membership(): void
  {
    $actor = $this->user('Actor');
    $target = $this->user('Target');
    $codes = $this->enroll($actor);
    $this->signedIn($actor);
    Gate::define('manage-users', fn () => false);
    $this->post($this->resetUrl($target), ['reason' => 'Denied by host', 'current_password' => 'fixture-password', 'recovery_code' => $codes[0]])->assertForbidden();
    $this->assertDatabaseCount('wbcms_two_factor_account_states', 0);
    $this->assertDatabaseMissing('wbcms_acl_events', ['action' => 'user.two_factor.reset']);
  }

  #[Test]
  public function a_host_bound_user_model_resolves_to_the_same_authorized_target(): void
  {
    $actor = $this->user('Actor');
    $target = $this->user('Target');
    $codes = $this->enroll($actor);
    $this->signedIn($actor);
    Route::bind('user', fn ($id) => User::findOrFail($id));
    $this->post($this->resetUrl($target), ['reason' => 'Bound account', 'current_password' => 'fixture-password', 'recovery_code' => $codes[0]])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('wbcms_two_factor_account_states', ['user_id' => $target->id, 'enrollment_required' => true]);
  }

  #[Test]
  public function custom_remember_token_columns_are_rotated_and_old_cookies_cannot_restore_access(): void
  {
    Schema::table('users', fn (Blueprint $table) => $table->text('custom_remember_token')->nullable());
    config(['auth.providers.users.model' => CustomRememberRecoveryUser::class]);
    $target = CustomRememberRecoveryUser::forceCreate(['name' => 'Custom', 'email' => 'custom@example.test', 'role' => 'super_admin', 'password' => Hash::make('fixture-password'), 'is_active' => true, 'custom_remember_token' => 'old-custom-token', 'remember_token' => 'ordinary-column']);
    Auth::forgetGuards();
    $name = Auth::guard('web')->getRecallerName();
    $cookie = $target->id.'|old-custom-token|'.$target->getAuthPassword();
    $this->withCookie($name, $cookie)->get('/host-recovery-probe')->assertOk();
    $this->assertAuthenticatedAs($target);
    $this->assertTrue(Auth::guard('web')->viaRemember());
    $this->assertTrue(app(ResetTwoFactor::class)->fromConsole($target, 'Custom host model'));
    $this->assertNotSame('old-custom-token', $target->fresh()->custom_remember_token);
    $this->assertSame('ordinary-column', $target->fresh()->remember_token);
    $this->flushSession();
    Auth::forgetGuards();
    $this->withCookie($name, $cookie)->get('/host-recovery-probe');
    $this->assertGuest('web');
  }
}

class CustomRememberRecoveryUser extends MaintenanceTestUser
{
  protected $casts = ['custom_remember_token' => 'encrypted'];

  public function getRememberTokenName()
  {
    return 'custom_remember_token';
  }
}
