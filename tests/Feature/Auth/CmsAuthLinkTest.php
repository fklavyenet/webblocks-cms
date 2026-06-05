<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use WebBlocks\Cms\Notifications\Auth\CmsResetPassword;

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
}
