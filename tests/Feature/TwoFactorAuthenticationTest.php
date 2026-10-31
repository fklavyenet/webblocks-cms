<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\Auth\ManageTwoFactor;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Notifications\System\TwoFactorEmail;
use WebBlocks\Cms\Queries\PanelNotificationQuery;
use WebBlocks\Cms\Support\Auth\EmailTwoFactorCodes;
use WebBlocks\Cms\Support\Auth\TwoFactorAuthentication;
use WebBlocks\Cms\Support\Auth\TwoFactorSchema;
use WebBlocks\Cms\Support\Auth\TwoFactorSession;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\Mail\CmsMailSettingsResolver;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginQuickSetting;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('mail.default', 'smtp');
    $app['config']->set('mail.mailers.smtp.transport', 'smtp');
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
    Notification::fake();
    // The notification aggregate belongs to the host's concrete User type.
    $this->mock(PanelNotificationQuery::class)->shouldReceive('forUser')->andReturn(null);
    $this->travelTo(now()->startOfMinute()->addSeconds(5));
  }

  private function actor(string $role = 'super_admin'): TwoFactorTestUser
  {
    return TwoFactorTestUser::forceCreate(['name' => 'Fictional operator', 'email' => 'operator@example.test', 'password' => Hash::make('fixture-password'), 'is_active' => true, 'role' => $role]);
  }

  private function code(TwoFactorTestUser $user): string
  {
    return Notification::sent(new AnonymousNotifiable, TwoFactorEmail::class)->last()->code;
  }

  private function enroll(TwoFactorTestUser $user): array
  {
    $generation = app(ManageTwoFactor::class)->start($user);
    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));
    $this->assertSame('sent', app(EmailTwoFactorCodes::class)->send($request, $user, 'setup', $user->email, app(TwoFactorSession::class)->setupBinding($user, $generation)));
    $id = $request->session()->get(EmailTwoFactorCodes::SESSION.'.setup');

    return app(ManageTwoFactor::class)->confirm($user, $generation, $this->code($user), $id);
  }

  private function signedIn(TwoFactorTestUser $user): void
  {
    $this->actingAs($user);
    $credential = app(TwoFactorAuthentication::class)->credential($user);
    $this->withSession([TwoFactorSession::VERIFIED => app(TwoFactorSession::class)->fingerprint($user, $credential)]);
  }

  #[Test]
  public function two_factor_panel_management_never_resolves_plugin_quick_settings_without_their_providers(): void
  {
    $calls = 0;
    $registry = new PluginRegistry(['security-fixture' => true]);
    $registry->register(PluginDefinition::make('security-fixture')->quickSettings([
      PluginQuickSetting::make('security-fixture.status')->view('security-fixture::unloaded-view')
        ->resolveUsing(function () use (&$calls): array {
        $calls++;

        return [];
        }),
    ]));
    $this->app->instance(PluginRegistry::class, $registry);
    $user = $this->actor();

    // The provider (and its view namespace) is intentionally absent on 2FA URLs.
    $this->assertFalse(view()->exists('security-fixture::unloaded-view'));
    $this->actingAs($user)->get(route('webblocks.two-factor.show'))->assertOk()
      ->assertSee('id="admin-sidebar"', false)->assertDontSee('id="admin-quick-settings"', false);
    $this->post(route('webblocks.two-factor.start'), ['current_password' => 'fixture-password'])->assertSessionHasNoErrors();
    $this->get(route('webblocks.two-factor.show'))->assertOk();
    $this->post(route('webblocks.two-factor.confirm'), ['code' => $this->code($user)])->assertOk()
      ->assertSee('id="admin-sidebar"', false)->assertViewHas('recoveryCodes', fn ($codes) => count($codes) === 10);
    $this->get(route('webblocks.two-factor.show', ['tab' => 'replace']))->assertOk();
    $this->assertSame(0, $calls);
  }

  #[Test]
  public function challenge_shows_one_field_and_the_actual_email_request_state(): void
  {
    $user = $this->actor();
    $this->enroll($user);
    Notification::fake();
    $response = $this->actingAs($user)->get(route('webblocks.two-factor.challenge'))->assertOk()
      ->assertViewHas('emailCodeActive', false)->assertSee(__('webblocks-cms::two-factor.challenge_request_email'))
      ->assertDontSee('name="recovery_code"', false);
    Notification::assertNothingSent();
    $dom = new \DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new \DOMXPath($dom);
    $this->assertSame(1, $xpath->query('//input[@name="code"]')->length);
    $this->assertSame(1, $xpath->query('//div[@class="wb-action-group"][button[@form="two-factor-verify"] and form/button]')->length);
    $this->assertSame(1, $xpath->query('//form[@id="two-factor-verify"]//input[@name="code" and @required and not(@pattern)]')->length);

    $this->post(route('webblocks.two-factor.sendChallenge'))->assertSessionHasNoErrors();
    foreach ([1, 2] as $refresh) {
      $this->get(route('webblocks.two-factor.challenge'))->assertOk()->assertViewHas('emailCodeActive', true)
        ->assertSee(__('webblocks-cms::two-factor.challenge_email_sent'));
    }
    Notification::assertSentOnDemandTimes(TwoFactorEmail::class, 1);
    $this->travel(5)->minutes();
    $this->get(route('webblocks.two-factor.challenge'))->assertOk()->assertViewHas('emailCodeActive', false)
      ->assertSee(__('webblocks-cms::two-factor.challenge_request_email'));
  }

  #[Test]
  public function the_single_field_accepts_a_recovery_code_during_password_login(): void
  {
    $user = $this->actor();
    $codes = $this->enroll($user);
    $this->post('/webadmin/login', ['email' => $user->email, 'password' => 'fixture-password'])->assertRedirect(route('webblocks.two-factor.challenge'));
    $this->get(route('webblocks.two-factor.challenge'))->assertOk()->assertViewHas('emailCodeActive', true);
    $this->assertGuest('web');
    $this->post(route('webblocks.two-factor.verify'), ['code' => $codes[0]])->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user, 'web');
    $this->assertFalse(app(ManageTwoFactor::class)->verify($user, '', $codes[0]));
  }

  #[Test]
  public function super_admins_can_remove_the_policy_then_disable_using_the_single_code_field(): void
  {
    $user = $this->actor();
    $codes = $this->enroll($user);
    $this->signedIn($user);
    DB::table('wbcms_two_factor_policy')->where('id', 1)->update(['require_super_admin' => true]);
    $this->get(route('webblocks.two-factor.show'))->assertOk()
      ->assertSee(route('admin.system.settings.edit', ['tab' => 'security']).'#two-factor-policy', false)
      ->assertSee(__('webblocks-cms::two-factor.policy_manage_help'))
      ->assertDontSee(__('webblocks-cms::two-factor.required_setup_help'))
      ->assertDontSee('id="two-factor-disable"', false)->assertDontSee('name="recovery_code"', false);
    $this->put(route('webblocks.two-factor.policy'), ['current_password' => 'fixture-password', 'require_super_admin' => 0, 'code' => $codes[0], 'recovery_code' => $codes[1]])
      ->assertSessionHasErrors()->assertSessionMissing('_old_input.code')->assertSessionMissing('_old_input.recovery_code')->assertSessionMissing('_old_input.current_password');
    $this->assertTrue(app(TwoFactorAuthentication::class)->required($user));
    $this->put(route('webblocks.two-factor.policy'), ['current_password' => 'fixture-password', 'require_super_admin' => 0, 'code' => $codes[0]])
      ->assertSessionHasNoErrors();
    $this->assertFalse(app(TwoFactorAuthentication::class)->required($user));
    $this->get(route('webblocks.two-factor.show', ['tab' => 'disable']))->assertOk()->assertSee('id="two-factor-disable"', false)
      ->assertDontSee(__('webblocks-cms::two-factor.required_help'));
    $this->delete(route('webblocks.two-factor.disable'), ['current_password' => 'fixture-password', 'code' => $codes[1]])->assertSessionHasNoErrors();
    $this->assertNull(app(TwoFactorAuthentication::class)->credential($user));
  }

  #[Test]
  public function enrollment_requires_password_a_session_bound_setup_and_a_valid_code(): void
  {
    $user = $this->actor();
    $this->actingAs($user)->get(route('webblocks.two-factor.show'))->assertOk()->assertDontSee('data:image/svg+xml');
    $this->post(route('webblocks.two-factor.start'), ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
    $this->assertNull(app(TwoFactorAuthentication::class)->credential($user));
    $this->post(route('webblocks.two-factor.start'), ['current_password' => 'fixture-password'])->assertRedirect(route('webblocks.two-factor.show', ['tab' => 'recovery']));
    $row = app(TwoFactorAuthentication::class)->enrollment($user) ?? app(TwoFactorAuthentication::class)->credential($user);
    $this->assertSame($user->email, $row->email);
    $secret = $this->code($user);
    $this->assertDatabaseMissing('wbcms_two_factor_email_codes', ['code_hash' => $secret]);
    $this->get(route('webblocks.two-factor.show'))->assertOk()->assertDontSee('data:image')->assertSee('admin-sidebar')->assertSee($user->email)->assertDontSee($secret)
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
  public function email_codes_expire_are_single_use_and_have_a_persistent_attempt_limit(): void
  {
    $user = $this->actor();
    $this->enroll($user);
    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));
    $credential = app(TwoFactorAuthentication::class)->credential($user);
    $binding = app(TwoFactorSession::class)->fingerprint($user, $credential);
    $service = app(EmailTwoFactorCodes::class);
    $this->assertSame('sent', $service->send($request, $user, 'login', $user->email, $binding));
    $id = $request->session()->get(EmailTwoFactorCodes::SESSION.'.login');
    $code = $this->code($user);
    $this->assertFalse($service->consume($user, 'action', $user->email, $binding, $id, $code));
    $this->assertFalse($service->consume($user, 'login', $user->email, $binding, '', $code));
    $this->assertTrue($service->consume($user, 'login', $user->email, $binding, $id, $code));
    $this->assertFalse($service->consume($user, 'login', $user->email, $binding, $id, $code));
    $this->travel(61)->seconds();
    $service->send($request, $user, 'login', $user->email, $binding);
    $id = $request->session()->get(EmailTwoFactorCodes::SESSION.'.login');
    $code = $this->code($user);
    $wrong = $code === '000000' ? '000001' : '000000';
    for ($i = 0; $i < 5; $i++) {
      $this->assertFalse($service->consume($user, 'login', $user->email, $binding, $id, $wrong));
    }
    $this->assertFalse($service->consume($user, 'login', $user->email, $binding, $id, $code));
    $this->travel(61)->seconds();
    $service->send($request, $user, 'login', $user->email, $binding);
    $id = $request->session()->get(EmailTwoFactorCodes::SESSION.'.login');
    $this->travel(5)->minutes();
    $this->assertFalse($service->consume($user, 'login', $user->email, $binding, $id, $this->code($user)));
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
    $this->get(route('webblocks.two-factor.show'))->assertOk()->assertSee(__('webblocks-cms::two-factor.required_help'));
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
    $this->actingAs($user)->get(route('webblocks.two-factor.show'))->assertOk()->assertViewHas('setupEmail', null);
    $this->post(route('webblocks.two-factor.confirm'), ['code' => '123456'])->assertSessionHasErrors('code');
    $this->withSession([TwoFactorSession::SETUP => $row->generation]);
    $this->travel(11)->minutes();
    $this->post(route('webblocks.two-factor.confirm'), ['code' => '123456'])->assertSessionHasErrors('code');
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
    $this->travel(61)->seconds();
    DB::table('wbcms_two_factor_policy')->where('id', 1)->update(['require_super_admin' => true]);
    $this->post(route('webblocks.two-factor.start'), ['current_password' => 'fixture-password'])->assertSessionHasErrors('code');
    $this->post(route('webblocks.two-factor.start'), ['current_password' => 'fixture-password', 'recovery_code' => $codes[0]])->assertSessionHasNoErrors();
    $this->assertSame($old->generation, app(TwoFactorAuthentication::class)->credential($user)->generation);
    $this->get(route('webblocks.two-factor.show'))->assertOk()->assertDontSee('data:image')->assertSee('Enter the code sent to this address');
    $this->travel(61)->seconds();
    $this->post(route('webblocks.two-factor.send'), ['purpose' => 'setup'])->assertSessionHasNoErrors();
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
  public function a_fresh_email_code_finishes_login_and_password_changes_invalidate_verified_sessions(): void
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
  public function management_uses_the_panel_but_mandatory_setup_and_login_stay_restricted(): void
  {
    $user = $this->actor();
    $this->actingAs($user)->get(route('webblocks.two-factor.show'))->assertOk()
      ->assertSee('id="admin-sidebar"', false)->assertSee(route('admin.profile.edit'));
    DB::table('wbcms_two_factor_policy')->where('id', 1)->update(['require_super_admin' => true]);
    $this->get(route('webblocks.two-factor.show'))->assertOk()->assertDontSee('id="admin-sidebar"', false);
    $this->post(route('webblocks.two-factor.start'), ['current_password' => 'fixture-password'])->assertSessionHasNoErrors();
    $this->post(route('webblocks.two-factor.confirm'), ['code' => $this->code($user)])->assertOk()->assertSee('id="admin-sidebar"', false);
    $this->withSession([TwoFactorSession::VERIFIED => '']);
    $this->get(route('webblocks.two-factor.show'))->assertRedirect(route('webblocks.two-factor.challenge'));
    $this->get(route('webblocks.two-factor.challenge'))->assertOk()->assertDontSee('id="admin-sidebar"', false);
  }

  #[Test]
  public function challenge_sends_are_explicit_and_cannot_extend_a_pending_login(): void
  {
    $user = $this->actor();
    $this->enroll($user);
    Notification::fake();
    $this->actingAs($user)->get(route('webblocks.two-factor.challenge'))->assertOk();
    Notification::assertNothingSent();
    $this->post(route('webblocks.two-factor.sendChallenge'))->assertSessionHasNoErrors();
    Notification::assertSentOnDemandTimes(TwoFactorEmail::class, 1);
    $oldCode = $this->code($user);
    $oldId = session(EmailTwoFactorCodes::SESSION.'.login');
    $this->post(route('webblocks.two-factor.sendChallenge'))->assertSessionHasErrors('code');
    Notification::assertSentOnDemandTimes(TwoFactorEmail::class, 1);
    $this->travel(61)->seconds();
    $this->post(route('webblocks.two-factor.sendChallenge'))->assertSessionHasNoErrors();
    $binding = app(TwoFactorSession::class)->fingerprint($user, app(TwoFactorAuthentication::class)->credential($user));
    $this->assertFalse(app(EmailTwoFactorCodes::class)->consume($user, 'login', $user->email, $binding, $oldId, $oldCode));
    Auth::guard('web')->logout();
    $this->flushSession();
    $this->travel(61)->seconds();
    $this->post('/webadmin/login', ['email' => $user->email, 'password' => 'fixture-password']);
    $expiry = session(TwoFactorSession::PENDING.'.expires_at');
    $this->travel(4)->minutes();
    $this->post(route('webblocks.two-factor.sendChallenge'))->assertSessionHasNoErrors();
    $this->assertSame($expiry, session(TwoFactorSession::PENDING.'.expires_at'));
    $this->travel(61)->seconds();
    $this->post(route('webblocks.two-factor.verify'), ['code' => $this->code($user)])->assertRedirect(route('webblocks.auth.login'));
    $this->assertGuest('web');
  }

  #[Test]
  public function a_code_from_another_session_or_purpose_cannot_finish_login(): void
  {
    $user = $this->actor();
    $this->enroll($user);
    $this->post('/webadmin/login', ['email' => $user->email, 'password' => 'fixture-password']);
    $code = $this->code($user);
    $id = session(EmailTwoFactorCodes::SESSION.'.login');
    $this->withSession([EmailTwoFactorCodes::SESSION => []]);
    $this->get(route('webblocks.two-factor.challenge'))->assertOk()->assertViewHas('emailCodeActive', false);
    $this->post(route('webblocks.two-factor.verify'), ['code' => $code, 'challenge_id' => $id])->assertSessionHasErrors('code');
    $this->assertGuest('web');
    $this->withSession([EmailTwoFactorCodes::SESSION => ['login' => $id]]);
    $this->post(route('webblocks.two-factor.verify'), ['code' => $code])->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user);
  }

  #[Test]
  public function changing_profile_email_does_not_redirect_the_existing_factor_and_replacement_proves_both_addresses(): void
  {
    $user = $this->actor();
    $codes = $this->enroll($user);
    $original = $user->email;
    $user->forceFill(['email' => 'new-address@example.test'])->save();
    $this->signedIn($user);
    $this->post(route('webblocks.two-factor.send'), ['purpose' => 'action'])->assertSessionHasNoErrors();
    Notification::assertSentOnDemand(TwoFactorEmail::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === $original);
    $this->travel(61)->seconds();
    $this->post(route('webblocks.two-factor.start'), ['current_password' => 'fixture-password', 'code' => $this->code($user)])->assertSessionHasNoErrors();
    $this->assertSame($original, app(TwoFactorAuthentication::class)->credential($user)->email);
    Notification::assertSentOnDemand(TwoFactorEmail::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === $user->email);
    $this->post(route('webblocks.two-factor.confirm'), ['code' => $this->code($user)])->assertOk();
    $this->assertSame($user->email, app(TwoFactorAuthentication::class)->credential($user)->email);
    $this->assertFalse(app(ManageTwoFactor::class)->verify($user, '', $codes[0]));
  }

  #[Test]
  public function email_codes_can_authorize_policy_changes_and_recovery_renewal_once(): void
  {
    $user = $this->actor();
    $this->enroll($user);
    $this->signedIn($user);
    $this->post(route('webblocks.two-factor.send'), ['purpose' => 'action', 'tab' => 'policy'])
      ->assertRedirect(route('admin.system.settings.edit', ['tab' => 'security']));
    $code = $this->code($user);
    $this->put(route('webblocks.two-factor.policy'), ['current_password' => 'fixture-password', 'code' => $code, 'require_super_admin' => 1])->assertSessionHasNoErrors();
    $this->assertTrue(app(TwoFactorAuthentication::class)->required($user));
    $this->post(route('webblocks.two-factor.regenerate'), ['current_password' => 'fixture-password', 'code' => $code])->assertSessionHasErrors('code');
    $this->travel(61)->seconds();
    $this->post(route('webblocks.two-factor.send'), ['purpose' => 'action'])->assertSessionHasNoErrors();
    $this->post(route('webblocks.two-factor.regenerate'), ['current_password' => 'fixture-password', 'code' => $this->code($user)])
      ->assertOk()->assertViewHas('recoveryCodes', fn ($codes) => count($codes) === 10);
  }

  #[Test]
  public function password_changes_invalidate_setup_codes_and_missing_setup_sessions_cannot_resend(): void
  {
    $user = $this->actor();
    $this->actingAs($user)->post(route('webblocks.two-factor.start'), ['current_password' => 'fixture-password']);
    $code = $this->code($user);
    $user->forceFill(['password' => Hash::make('new-password')])->save();
    $this->post(route('webblocks.two-factor.confirm'), ['code' => $code])->assertSessionHasErrors('code');
    $this->assertNull(app(TwoFactorAuthentication::class)->credential($user));
    $this->withSession([TwoFactorSession::SETUP => '']);
    $this->post(route('webblocks.two-factor.send'), ['purpose' => 'setup'])->assertSessionHasErrors('code');
    Notification::assertSentOnDemandTimes(TwoFactorEmail::class, 1);
  }

  #[Test]
  public function mail_failures_leave_login_pending_and_recovery_available_without_logging_the_exception_message(): void
  {
    $user = $this->actor();
    $codes = $this->enroll($user);
    Notification::fake();
    Log::spy();
    $this->mock(CmsMailSettingsResolver::class)
      ->shouldReceive('resolvedMailerName')->andThrow(new \RuntimeException('sensitive SMTP message body'));
    $this->post('/webadmin/login', ['email' => $user->email, 'password' => 'fixture-password'])
      ->assertRedirect(route('webblocks.two-factor.challenge'))->assertSessionHas('email_error');
    $this->get(route('webblocks.two-factor.challenge'))->assertOk()->assertViewHas('emailCodeActive', false)
      ->assertSee(__('webblocks-cms::two-factor.challenge_request_email'));
    $this->assertGuest('web');
    $this->assertNotNull(DB::table('wbcms_two_factor_email_codes')->where('purpose', 'login')->first()->consumed_at);
    Notification::assertNothingSent();
    Log::shouldHaveReceived('warning')->once()->with('CMS verification email could not be sent.', ['exception_class' => \RuntimeException::class]);
    $this->post(route('webblocks.two-factor.verify'), ['recovery_code' => $codes[0]])->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user);
  }

  #[Test]
  public function log_and_array_transports_cannot_record_verification_codes_even_as_failover(): void
  {
    $user = $this->actor();
    foreach (['log', 'array', 'fallback'] as $mailer) {
      config(['mail.default' => $mailer, 'mail.mailers.'.$mailer.'.transport' => $mailer === 'fallback' ? 'failover' : $mailer,
        'mail.mailers.fallback.mailers' => ['smtp', 'log']]);
      $request = Request::create('/');
      $request->setLaravelSession(app('session.store'));
      $this->assertSame('send_failed', app(EmailTwoFactorCodes::class)->send($request, $user, 'login', $user->email, str_repeat('a', 64)));
      $this->travel(61)->seconds();
    }
    Notification::assertNothingSent();
  }

  #[Test]
  public function upgrading_totp_removes_secrets_preserves_enforcement_and_keeps_recovery_codes(): void
  {
    $user = $this->actor();
    $codes = $this->enroll($user);
    $old = app(TwoFactorAuthentication::class)->credential($user);
    DB::table('wbcms_two_factor_policy')->where('id', 1)->update(['require_super_admin' => true]);
    foreach (['credentials', 'enrollments'] as $name) {
      Schema::table('wbcms_two_factor_'.$name, function (Blueprint $table): void {
        $table->text('secret')->default('old-encrypted-secret');
        $table->dropColumn('email');
      });
    }
    Schema::table('wbcms_two_factor_credentials', fn (Blueprint $table) => $table->unsignedBigInteger('last_used_step')->nullable());
    DB::table('wbcms_two_factor_enrollments')->insert(['user_id' => $user->id, 'secret' => 'old-pending-secret', 'generation' => (string) Str::uuid(), 'expires_at' => now()->addMinutes(10)]);
    $schema = new TwoFactorSchema;
    $schema->migrate();
    $schema->migrate();
    $credential = app(TwoFactorAuthentication::class)->credential($user);
    $this->assertSame($user->email, $credential->email);
    $this->assertNotSame($old->generation, $credential->generation);
    $this->assertNotNull($credential->confirmed_at);
    $this->assertFalse(Schema::hasColumn('wbcms_two_factor_credentials', 'secret'));
    $this->assertFalse(Schema::hasColumn('wbcms_two_factor_credentials', 'last_used_step'));
    $this->assertDatabaseCount('wbcms_two_factor_enrollments', 0);
    $this->assertTrue(app(TwoFactorAuthentication::class)->required($user));
    $this->assertTrue(app(ManageTwoFactor::class)->verify($user, '', $codes[0]));
  }

  #[Test]
  public function recovery_management_keeps_the_restricted_layout_and_return_destination(): void
  {
    $user = $this->actor();
    $this->enroll($user);
    $this->signedIn($user);
    $this->withSession([TwoFactorSession::RECOVERY => true]);
    $this->get(route('webblocks.two-factor.show'))->assertOk()->assertDontSee('id="admin-sidebar"', false)
      ->assertViewHas('continueUrl', route('admin.plugins.recovery.index'));
  }

  #[Test]
  public function verification_mail_renders_localized_content_through_the_cms_mail_resolver(): void
  {
    $message = (new TwoFactorEmail('123456'))->toMail(new AnonymousNotifiable);
    $this->assertSame('smtp', $message->mailer);
    $this->assertSame(str_replace('{code}', '123456', __('webblocks-cms::two-factor.mail_subject')), $message->subject);
    $this->assertStringContainsString('123456', (string) $message->render());
    config(['mail.mailers.smtp.transport' => 'log']);
    $this->expectException(\RuntimeException::class);
    (new TwoFactorEmail('123456'))->toMail(new AnonymousNotifiable);
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
