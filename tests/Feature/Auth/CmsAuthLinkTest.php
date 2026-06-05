<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Notifications\Auth\CmsResetPassword;
use WebBlocks\Cms\Support\Mail\CmsMailConfigurationException;
use WebBlocks\Cms\Support\Mail\CmsMailSettingsResolver;
use WebBlocks\Cms\Support\System\SystemSettings;

class CmsAuthLinkTest extends TestCase
{
  use RefreshDatabase;

  public function test_cms_login_screen_uses_prefixed_password_link_and_hides_root_registration(): void
  {
    $response = $this->get('/webadmin/login');

    $response->assertOk();
    $response->assertSee('href="'.route('webblocks.auth.password.request').'"', false);
    $response->assertDontSee('href="http://localhost/forgot-password"', false);
    $response->assertDontSee('href="/forgot-password"', false);
    $response->assertDontSee('href="http://localhost/register"', false);
    $response->assertDontSee('href="/register"', false);
    $response->assertDontSee('Need an account?');
  }

  public function test_cms_forgot_password_screen_uses_prefixed_form_and_login_links(): void
  {
    $response = $this->get('/webadmin/forgot-password');

    $response->assertOk();
    $response->assertSee('action="'.route('webblocks.auth.password.email').'"', false);
    $response->assertSee('href="'.route('login').'"', false);
    $response->assertDontSee('action="http://localhost/forgot-password"', false);
    $response->assertDontSee('href="http://localhost/login"', false);
  }

  public function test_cms_reset_password_screen_uses_prefixed_form_and_login_links(): void
  {
    $response = $this->get('/webadmin/reset-password/test-token?email=editor%40example.com');

    $response->assertOk();
    $response->assertSee('action="'.route('webblocks.auth.password.store').'"', false);
    $response->assertSee('href="'.route('login').'"', false);
    $response->assertDontSee('action="http://localhost/reset-password"', false);
    $response->assertDontSee('href="http://localhost/login"', false);
  }

  public function test_cms_forgot_password_sends_prefixed_reset_link(): void
  {
    Notification::fake();

    $user = User::factory()->create(['email' => 'editor@example.com']);

    $this->post('/webadmin/forgot-password', ['email' => $user->email])
      ->assertSessionHas('status');

    Notification::assertSentTo($user, CmsResetPassword::class, function (CmsResetPassword $notification) use ($user): bool {
      $mail = $notification->toMail($user);

      return str_contains((string) $mail->actionUrl, '/webadmin/reset-password/')
        && str_contains((string) $mail->actionUrl, 'email=editor%40example.com');
    });
  }

  public function test_cms_password_reset_notification_uses_custom_cms_mailer_when_enabled(): void
  {
    $user = User::factory()->create(['email' => 'editor@example.com']);

    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_MODE], ['value' => SystemSettings::CMS_MAIL_MODE_CUSTOM]);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_MAILER], ['value' => 'smtp']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_HOST], ['value' => 'smtp.example.test']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_PORT], ['value' => '587']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_ENCRYPTION], ['value' => 'none']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_USERNAME], ['value' => 'mailer@example.test']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_PASSWORD], ['value' => 'stored-secret']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_FROM_ADDRESS], ['value' => 'cms@example.test']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_FROM_NAME], ['value' => 'WebBlocks CMS']);

    $mail = (new CmsResetPassword('test-token', $user->email))->toMail($user);

    $this->assertSame(CmsMailSettingsResolver::MAILER_NAME, $mail->mailer);
    $this->assertSame(['cms@example.test', 'WebBlocks CMS'], $mail->from);
    $this->assertSame('smtp.example.test', config('mail.mailers.'.CmsMailSettingsResolver::MAILER_NAME.'.host'));
    $this->assertSame(587, config('mail.mailers.'.CmsMailSettingsResolver::MAILER_NAME.'.port'));
    $this->assertNull(config('mail.mailers.'.CmsMailSettingsResolver::MAILER_NAME.'.encryption'));
    $this->assertSame('stored-secret', config('mail.mailers.'.CmsMailSettingsResolver::MAILER_NAME.'.password'));
  }

  public function test_cms_forgot_password_handles_mail_transport_failures_without_500_or_secret_leaks(): void
  {
    $user = User::factory()->create(['email' => 'editor@example.com']);
    $this->configureCustomCmsMail([
      SystemSettings::CMS_MAIL_PASSWORD => 'stored-secret',
    ]);

    $throwingMailer = Mockery::mock();
    $throwingMailer->shouldReceive('send')
      ->once()
      ->andThrow(new \RuntimeException('SMTP failed password=stored-secret token=reset-token editor@example.com'));

    Mail::shouldReceive('purge')->once()->with(CmsMailSettingsResolver::MAILER_NAME);
    Mail::shouldReceive('mailer')->once()->with(CmsMailSettingsResolver::MAILER_NAME)->andReturn($throwingMailer);
    Log::spy();

    $this->from('/webadmin/forgot-password')
      ->post('/webadmin/forgot-password', ['email' => $user->email])
      ->assertRedirect('/webadmin/forgot-password')
      ->assertSessionHasErrors([
        'email' => 'The password reset email could not be sent. Please check CMS Mail settings or contact an administrator.',
      ]);

    Log::shouldHaveReceived('warning')
      ->once()
      ->withArgs(fn (string $message, array $context = []): bool => $message === 'CMS password reset email could not be sent.'
        && ($context['cms_mail_mode'] ?? null) === SystemSettings::CMS_MAIL_MODE_CUSTOM
        && ($context['mailer'] ?? null) === CmsMailSettingsResolver::MAILER_NAME
        && ($context['host'] ?? null) === 'smtp.example.test'
        && ($context['port'] ?? null) === 587
        && ($context['encryption'] ?? null) === 'tls'
        && ($context['username_configured'] ?? null) === true
        && ($context['password_configured'] ?? null) === true
        && ($context['from_address'] ?? null) === 'cms@example.test'
        && ($context['exception_class'] ?? null) === \RuntimeException::class
        && str_contains((string) ($context['sanitized_message'] ?? ''), 'password=[redacted]')
        && str_contains((string) ($context['sanitized_message'] ?? ''), '[redacted-email]')
        && ! str_contains(json_encode($context), 'stored-secret')
        && ! str_contains(json_encode($context), 'reset-token'));
  }

  public function test_cms_forgot_password_reports_incomplete_custom_mail_as_controlled_error(): void
  {
    $user = User::factory()->create(['email' => 'editor@example.com']);
    $this->configureCustomCmsMail([
      SystemSettings::CMS_MAIL_PASSWORD => null,
    ]);

    Log::spy();

    $this->from('/webadmin/forgot-password')
      ->post('/webadmin/forgot-password', ['email' => $user->email])
      ->assertRedirect('/webadmin/forgot-password')
      ->assertSessionHasErrors([
        'email' => 'The password reset email could not be sent. Please check CMS Mail settings or contact an administrator.',
      ]);

    Log::shouldHaveReceived('warning')
      ->once()
      ->withArgs(fn (string $message, array $context = []): bool => $message === 'CMS password reset email could not be sent.'
        && ($context['password_configured'] ?? null) === false
        && ($context['exception_class'] ?? null) === CmsMailConfigurationException::class
        && ! str_contains(json_encode($context), 'stored-secret'));
  }

  private function configureCustomCmsMail(array $overrides = []): void
  {
    $settings = [
      SystemSettings::CMS_MAIL_MODE => SystemSettings::CMS_MAIL_MODE_CUSTOM,
      SystemSettings::CMS_MAIL_MAILER => 'smtp',
      SystemSettings::CMS_MAIL_HOST => 'smtp.example.test',
      SystemSettings::CMS_MAIL_PORT => '587',
      SystemSettings::CMS_MAIL_ENCRYPTION => 'tls',
      SystemSettings::CMS_MAIL_USERNAME => 'mailer@example.test',
      SystemSettings::CMS_MAIL_PASSWORD => 'stored-secret',
      SystemSettings::CMS_MAIL_FROM_ADDRESS => 'cms@example.test',
      SystemSettings::CMS_MAIL_FROM_NAME => 'WebBlocks CMS',
    ];

    foreach ($overrides + $settings as $key => $value) {
      SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
  }
}
