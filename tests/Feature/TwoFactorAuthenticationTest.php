<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use PragmaRX\Google2FA\Google2FA;
use WebBlocks\Cms\Actions\Auth\ManageTwoFactor;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Auth\TwoFactorAuthentication;
use WebBlocks\Cms\Support\Auth\TwoFactorSession;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('auth.providers.users.model', TwoFactorTestUser::class);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function defineRoutes($router): void
  {
    $router->middleware('web')->get('/webadmin/two-factor-probe', fn () => 'protected')->name('admin.two-factor-probe');
    $router->middleware('web')->post('/webadmin/two-factor-write', fn () => 'mutated')->name('admin.two-factor-write');
    $router->middleware(['web', 'internal-api.token'])->get('/webadmin/api/two-factor-probe', fn () => response()->json(['ok' => true]))->name('internal-content-api.two-factor-probe');
    $router->middleware('web')->get('/host-account', fn () => 'host');
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->withoutMiddleware(RedirectIfNotInstalled::class);
    $this->travelTo(now()->startOfMinute()->addSeconds(5));
  }

  private function actor(string $role = 'super_admin'): TwoFactorTestUser
  {
    return TwoFactorTestUser::forceCreate(['name' => 'Fictional operator', 'email' => 'operator@example.test', 'password' => Hash::make('fixture-password'), 'is_active' => true, 'role' => $role]);
  }

  private function code(TwoFactorTestUser $user): string
  {
    $row = app(TwoFactorAuthentication::class)->enrollment($user) ?? app(TwoFactorAuthentication::class)->credential($user);

    return (new Google2FA)->oathTotp(Crypt::decryptString($row->secret), (int) floor(now()->timestamp / 30));
  }

  private function enroll(TwoFactorTestUser $user): array
  {
    $generation = app(ManageTwoFactor::class)->start($user);

    return app(ManageTwoFactor::class)->confirm($user, $generation, $this->code($user));
  }

  private function signedIn(TwoFactorTestUser $user): void
  {
    $this->actingAs($user);
    $credential = app(TwoFactorAuthentication::class)->credential($user);
    $this->withSession([TwoFactorSession::VERIFIED => app(TwoFactorSession::class)->fingerprint($user, $credential)]);
  }

  #[Test]
  public function enrollment_requires_password_a_session_bound_setup_and_a_valid_code(): void
  {
    $user = $this->actor();
    $this->actingAs($user)->get(route('webblocks.two-factor.show'))->assertOk()->assertDontSee('data:image/svg+xml');
    $this->post(route('webblocks.two-factor.start'), ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
    $this->assertNull(app(TwoFactorAuthentication::class)->credential($user));
    $this->post(route('webblocks.two-factor.start'), ['current_password' => 'fixture-password'])->assertRedirect(route('webblocks.two-factor.show'));
    $row = app(TwoFactorAuthentication::class)->enrollment($user) ?? app(TwoFactorAuthentication::class)->credential($user);
    $secret = Crypt::decryptString($row->secret);
    $this->assertNotSame($secret, $row->secret);
    $this->get(route('webblocks.two-factor.show'))->assertOk()->assertSee('data:image/svg+xml;base64', false)->assertSee($secret)
      ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
    $this->post(route('webblocks.two-factor.confirm'), ['code' => 'bad'])->assertSessionHasErrors('code')->assertSessionMissing('_old_input.code');
    $this->post(route('webblocks.two-factor.confirm'), ['code' => $this->code($user)])->assertOk()->assertViewHas('recoveryCodes', fn ($codes) => count($codes) === 10);
    $this->assertNotNull(app(TwoFactorAuthentication::class)->credential($user)->confirmed_at);
    $this->get(route('webblocks.two-factor.show'))->assertOk()->assertDontSee($secret)->assertViewHas('recoveryCodes', []);
    $this->assertFalse(app(ManageTwoFactor::class)->verify($user, $this->code($user)));
  }

  #[Test]
  public function a_password_only_login_stays_guest_until_the_second_factor_succeeds(): void
  {
    $user = $this->actor();
    $codes = $this->enroll($user);
    $this->post('/webadmin/login', ['email' => $user->email, 'password' => 'fixture-password', 'remember' => 1])
      ->assertRedirect(route('webblocks.two-factor.challenge'));
    $this->assertGuest('web');
    $this->assertNull($user->fresh()->last_login_at);
    $this->assertEmpty($user->fresh()->remember_token);
    $this->post(route('webblocks.two-factor.verify'), ['recovery_code' => 'wrong-code'])->assertSessionHasErrors('code')->assertSessionMissing('_old_input.recovery_code');
    $this->assertGuest('web');
    $this->post(route('webblocks.two-factor.verify'), ['recovery_code' => $codes[0]])->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user, 'web');
    $this->assertNotNull($user->fresh()->last_login_at);
    $this->assertNotEmpty($user->fresh()->remember_token);
    $this->get('/webadmin/two-factor-probe')->assertOk();
    $this->assertFalse(app(ManageTwoFactor::class)->verify($user, '', $codes[0]));
  }

  #[Test]
  public function totp_accepts_a_small_clock_window_and_atomically_rejects_replay(): void
  {
    $user = $this->actor();
    $this->enroll($user);
    $this->travel(30)->seconds();
    $code = $this->code($user);
    $this->assertTrue(app(ManageTwoFactor::class)->verify($user, $code));
    $this->assertFalse(app(ManageTwoFactor::class)->verify($user, $code));
    $this->travel(90)->seconds();
    $this->assertFalse(app(ManageTwoFactor::class)->verify($user, $code));
  }

  #[Test]
  public function host_login_remembered_sessions_preview_plugin_recovery_and_unsafe_requests_cannot_bypass_the_gate(): void
  {
    $user = $this->actor();
    $codes = $this->enroll($user);
    $this->actingAs($user)->get('/webadmin/two-factor-probe')->assertRedirect(route('webblocks.two-factor.challenge'));
    $this->get('/host-account')->assertOk();
    $this->post('/webadmin/two-factor-write')->assertRedirect(route('webblocks.two-factor.challenge'));
    $this->postJson('/webadmin/two-factor-write')->assertStatus(423)->assertJsonPath('code', 'cms_two_factor_required');
    $this->get('/webadmin/pages/1/preview')->assertRedirect(route('webblocks.two-factor.challenge'));
    $this->get('/webadmin/plugin-recovery')->assertRedirect(route('webblocks.two-factor.challenge'));
    $this->post(route('webblocks.two-factor.verify'), ['recovery_code' => $codes[0]])->assertRedirect('/webadmin/plugin-recovery');
    $this->get('/webadmin/two-factor-probe')->assertOk();
    // A new host login event must clear the previous second-factor proof.
    Auth::guard('web')->login($user, true);
    $this->get('/webadmin/two-factor-probe')->assertRedirect(route('webblocks.two-factor.challenge'));
  }

  #[Test]
  public function pending_login_expires_and_password_reset_or_inactivity_invalidates_it(): void
  {
    $user = $this->actor();
    $codes = $this->enroll($user);
    $this->post('/webadmin/login', ['email' => $user->email, 'password' => 'fixture-password']);
    $this->travel(6)->minutes();
    $this->post(route('webblocks.two-factor.verify'), ['recovery_code' => $codes[0]])->assertRedirect(route('webblocks.auth.login'));
    $this->assertGuest('web');
    $this->post('/webadmin/login', ['email' => $user->email, 'password' => 'fixture-password']);
    $user->forceFill(['password' => Hash::make('changed-password')])->save();
    $this->post(route('webblocks.two-factor.verify'), ['recovery_code' => $codes[0]])->assertRedirect(route('webblocks.auth.login'));
    $this->post('/webadmin/login', ['email' => $user->email, 'password' => 'changed-password']);
    $user->forceFill(['is_active' => false])->save();
    $this->post(route('webblocks.two-factor.verify'), ['recovery_code' => $codes[0]])->assertRedirect(route('webblocks.auth.login'));
    $this->assertGuest('web');
  }

  #[Test]
  public function regenerating_and_disabling_require_password_and_factor_and_revoke_old_codes(): void
  {
    $user = $this->actor();
    $codes = $this->enroll($user);
    $this->signedIn($user);
    $this->post(route('webblocks.two-factor.regenerate'), ['current_password' => 'wrong', 'recovery_code' => $codes[0]])->assertSessionHasErrors('current_password');
    $response = $this->post(route('webblocks.two-factor.regenerate'), ['current_password' => 'fixture-password', 'recovery_code' => $codes[0]])->assertOk();
    $newCodes = $response->viewData('recoveryCodes');
    $this->assertCount(10, $newCodes);
    $this->assertFalse(app(ManageTwoFactor::class)->verify($user, '', $codes[1]));
    $this->assertDatabaseMissing('wbcms_two_factor_recovery_codes', ['code_hash' => $newCodes[0]]);
    $this->assertDatabaseHas('wbcms_two_factor_recovery_codes', ['user_id' => $user->id, 'code_hash' => hash('sha256', $newCodes[0])]);
    $this->delete(route('webblocks.two-factor.disable'), ['current_password' => 'fixture-password', 'recovery_code' => $newCodes[0]])->assertRedirect(route('webblocks.two-factor.show'));
    $this->assertNull(app(TwoFactorAuthentication::class)->credential($user));
    $this->assertDatabaseCount('wbcms_two_factor_recovery_codes', 0);
  }

  #[Test]
  public function mandatory_roles_include_super_admin_built_in_and_custom_assignments_on_any_site(): void
  {
    $user = $this->actor();
    $role = CmsRole::create(['name' => 'Reviewers', 'handle' => 'custom-reviewers']);
    $editor = CmsRole::where('handle', 'editor')->firstOrFail();
    $this->actingAs($user)->put(route('webblocks.two-factor.policy'), ['current_password' => 'fixture-password', 'require_super_admin' => 1, 'role_ids' => [$role->id, $editor->id]])
      ->assertRedirect(route('admin.system.settings.edit', ['tab' => 'security']));
    $this->get('/webadmin/two-factor-probe')->assertRedirect(route('webblocks.two-factor.show'));
    $this->get(route('webblocks.two-factor.show'))->assertOk()->assertSee('Your CMS role requires');
    $codes = $this->enroll($user);
    $this->signedIn($user);
    $this->delete(route('webblocks.two-factor.disable'), ['current_password' => 'fixture-password', 'recovery_code' => $codes[0]])->assertSessionHasErrors('code');
    $this->assertNotNull(app(TwoFactorAuthentication::class)->credential($user));
    $site = Site::create(['name' => 'Fictional site', 'handle' => 'fictional']);
    $member = TwoFactorTestUser::forceCreate(['name' => 'Fictional reviewer', 'email' => 'reviewer@example.test', 'password' => Hash::make('fixture-password'), 'role' => null, 'is_active' => true]);
    DB::table('wbcms_site_user')->insert(['user_id' => $member->id, 'site_id' => $site->id, 'role_id' => $role->id]);
    DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $role->id, 'permission' => 'cms.panel.access']);
    $this->assertTrue(app(TwoFactorAuthentication::class)->required($member));
    $this->actingAs($member)->get('/webadmin/two-factor-probe')->assertRedirect(route('webblocks.two-factor.show'));
    $this->put(route('webblocks.two-factor.policy'), ['current_password' => 'fixture-password', 'require_super_admin' => 0])->assertForbidden();
  }

  #[Test]
  public function policy_rejects_unknown_roles_and_requires_reauthentication_when_enrolled(): void
  {
    $user = $this->actor();
    $codes = $this->enroll($user);
    $this->signedIn($user);
    $this->put(route('webblocks.two-factor.policy'), ['current_password' => 'fixture-password', 'require_super_admin' => 1])->assertSessionHasErrors('code');
    $this->put(route('webblocks.two-factor.policy'), ['current_password' => 'fixture-password', 'recovery_code' => $codes[0], 'require_super_admin' => 1, 'role_ids' => [999]])->assertSessionHasErrors('role_ids.0');
    $this->assertFalse(app(TwoFactorAuthentication::class)->required($user));
    $this->put(route('webblocks.two-factor.policy'), ['current_password' => 'fixture-password', 'recovery_code' => $codes[0], 'require_super_admin' => 1])->assertSessionHasNoErrors();
    $this->assertTrue(app(TwoFactorAuthentication::class)->required($user));
  }

  #[Test]
  public function setup_expires_and_another_session_cannot_read_or_confirm_it(): void
  {
    $user = $this->actor();
    app(ManageTwoFactor::class)->start($user);
    $row = app(TwoFactorAuthentication::class)->enrollment($user) ?? app(TwoFactorAuthentication::class)->credential($user);
    $this->actingAs($user)->get(route('webblocks.two-factor.show'))->assertOk()->assertDontSee(Crypt::decryptString($row->secret));
    $this->post(route('webblocks.two-factor.confirm'), ['code' => $this->code($user)])->assertSessionHasErrors('code');
    $this->withSession([TwoFactorSession::SETUP => $row->generation]);
    $this->travel(11)->minutes();
    $this->post(route('webblocks.two-factor.confirm'), ['code' => $this->code($user)])->assertSessionHasErrors('code');
    $this->assertNull(app(TwoFactorAuthentication::class)->credential($user));
  }

  #[Test]
  public function guessing_is_rate_limited_per_account_and_sensitive_input_is_never_flashed(): void
  {
    $user = $this->actor();
    $this->enroll($user);
    $this->actingAs($user);
    for ($i = 0; $i < 5; $i++) {
      $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($i + 1)])
        ->post(route('webblocks.two-factor.verify'), ['code' => 'not-a-code', 'recovery_code' => 'not-a-recovery-code'])
        ->assertSessionHasErrors()->assertSessionMissing('_old_input');
    }
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.99'])->post(route('webblocks.two-factor.verify'), ['code' => '000000'])->assertStatus(429);
  }

  #[Test]
  public function plugin_recovery_login_uses_the_same_second_factor(): void
  {
    $user = $this->actor();
    $codes = $this->enroll($user);
    $this->post('/webadmin/plugin-recovery/login', ['email' => $user->email, 'password' => 'fixture-password'])->assertRedirect(route('webblocks.two-factor.challenge'));
    $this->assertGuest('web');
    $this->post(route('webblocks.two-factor.verify'), ['recovery_code' => $codes[0]])->assertRedirect(route('admin.plugins.recovery.index'));
  }

  #[Test]
  public function replacement_keeps_the_old_factor_until_confirmation_then_revokes_old_proofs_and_codes(): void
  {
    $user = $this->actor();
    $codes = $this->enroll($user);
    $old = app(TwoFactorAuthentication::class)->credential($user);
    $this->signedIn($user);
    DB::table('wbcms_two_factor_policy')->where('id', 1)->update(['require_super_admin' => true]);
    $this->post(route('webblocks.two-factor.start'), ['current_password' => 'fixture-password'])->assertSessionHasErrors('code');
    $this->post(route('webblocks.two-factor.start'), ['current_password' => 'fixture-password', 'recovery_code' => $codes[0]])->assertSessionHasNoErrors();
    $this->assertSame($old->generation, app(TwoFactorAuthentication::class)->credential($user)->generation);
    $this->get(route('webblocks.two-factor.show'))->assertOk()->assertSee('data:image/svg+xml;base64', false);
    $this->post(route('webblocks.two-factor.confirm'), ['code' => $this->code($user)])->assertOk();
    $this->assertNotSame($old->generation, app(TwoFactorAuthentication::class)->credential($user)->generation);
    $this->assertFalse(app(ManageTwoFactor::class)->verify($user, '', $codes[1]));
    $this->withSession([TwoFactorSession::VERIFIED => app(TwoFactorSession::class)->fingerprint($user, $old)]);
    $this->get('/webadmin/two-factor-probe')->assertRedirect(route('webblocks.two-factor.challenge'));
  }

  #[Test]
  public function optional_users_keep_the_existing_login_and_cannot_configure_other_accounts(): void
  {
    $user = $this->actor();
    $this->post('/webadmin/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->assertGuest('web');
    $this->post('/webadmin/login', ['email' => $user->email, 'password' => 'fixture-password'])->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user, 'web');
    $this->get('/webadmin/two-factor-probe')->assertOk();
    $this->post(route('webblocks.two-factor.start'), ['current_password' => 'fixture-password', 'user_id' => 999])->assertSessionHasNoErrors();
    $this->assertNotNull(app(TwoFactorAuthentication::class)->enrollment($user));
    $this->assertDatabaseMissing('wbcms_two_factor_enrollments', ['user_id' => 999]);
  }

  #[Test]
  public function a_fresh_totp_finishes_login_and_password_changes_invalidate_verified_sessions(): void
  {
    $user = $this->actor();
    $this->enroll($user);
    $this->travel(30)->seconds();
    $this->post('/webadmin/login', ['email' => $user->email, 'password' => 'fixture-password'])->assertRedirect(route('webblocks.two-factor.challenge'));
    $this->post(route('webblocks.two-factor.verify'), ['code' => $this->code($user)])->assertRedirect(route('admin.dashboard'));
    $this->get('/webadmin/two-factor-probe')->assertOk();
    $user->forceFill(['password' => Hash::make('new-password')])->save();
    // A real subsequent request reloads its identity through the session guard.
    Auth::guard('web')->forgetUser();
    $this->get('/webadmin/two-factor-probe')->assertRedirect(route('webblocks.two-factor.challenge'));
  }

  #[Test]
  public function security_forms_render_in_all_panel_languages_with_distinct_labels_and_local_messages(): void
  {
    $user = $this->actor();
    $this->actingAs($user);
    $englishKeys = array_keys(require dirname(__DIR__, 2).'/resources/lang/en/two-factor.php');
    foreach (['en', 'tr', 'de', 'fr', 'es', 'it'] as $locale) {
      $user->forceFill(['admin_locale' => $locale]);
      $catalog = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/two-factor.php';
      $this->assertEqualsCanonicalizing($englishKeys, array_keys($catalog));
      $this->get(route('webblocks.two-factor.show'))->assertOk()->assertDontSee('webblocks-cms::two-factor.');
    }
    $this->assertTrue(app(TwoFactorAuthentication::class)->dependenciesReady());
    $this->assertFalse(app(TwoFactorAuthentication::class)->policy()['require_super_admin']);
  }

  #[Test]
  public function bearer_tokens_keep_their_own_authentication_even_when_a_browser_cookie_is_present(): void
  {
    $user = $this->actor();
    $this->enroll($user);
    $this->actingAs($user)->getJson('/webadmin/api/two-factor-probe')->assertUnauthorized();
    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic API client');
    $this->withToken($token->plainToken)->getJson('/webadmin/api/two-factor-probe')->assertOk();
    $this->withToken('invalid')->getJson('/webadmin/api/two-factor-probe')->assertUnauthorized();
    $this->withToken($token->plainToken)->postJson('/webadmin/two-factor-write')->assertStatus(423);
  }

  #[Test]
  public function both_migration_paths_are_idempotent_and_preserve_enrollment_and_policy(): void
  {
    $user = $this->actor();
    $this->enroll($user);
    DB::table('wbcms_two_factor_policy')->where('id', 1)->update(['require_super_admin' => true]);
    $before = app(TwoFactorAuthentication::class)->credential($user);
    foreach (['fresh', 'updates'] as $profile) {
      $migration = require dirname(__DIR__, 2).'/database/migrations/'.$profile.'/2026_10_27_120000_create_cms_two_factor.php';
      $migration->up();
      $migration->down();
    }
    $this->assertEquals($before, app(TwoFactorAuthentication::class)->credential($user));
    $this->assertTrue(app(TwoFactorAuthentication::class)->required($user));
    $this->assertDatabaseCount('wbcms_two_factor_recovery_codes', 10);
  }
}

class TwoFactorTestUser extends CmsSqlTestUser
{
  protected $table = 'users';
}
