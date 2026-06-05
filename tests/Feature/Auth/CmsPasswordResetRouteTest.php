<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use WebBlocks\Cms\Notifications\Auth\CmsResetPassword;

class CmsPasswordResetRouteTest extends TestCase
{
  use RefreshDatabase;

  public function test_cms_reset_password_link_screen_can_be_rendered(): void
  {
    $response = $this->get('/webadmin/forgot-password');

    $response->assertStatus(200);
    $response->assertSee('action="'.route('webblocks.auth.password.email').'"', false);
    $response->assertDontSee('action="http://localhost/forgot-password"', false);
  }

  public function test_cms_reset_password_link_can_be_requested(): void
  {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/webadmin/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, CmsResetPassword::class);
  }

  public function test_cms_reset_password_screen_can_be_rendered(): void
  {
    $response = $this->get('/webadmin/reset-password/test-token?email=editor%40example.com');

    $response->assertStatus(200);
    $response->assertSee('action="'.route('webblocks.auth.password.store').'"', false);
    $response->assertDontSee('action="http://localhost/reset-password"', false);
  }

  public function test_cms_password_can_be_reset_with_valid_token(): void
  {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/webadmin/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, CmsResetPassword::class, function (CmsResetPassword $notification) use ($user) {
      $response = $this->post('/webadmin/reset-password', [
        'token' => $notification->token,
        'email' => $user->email,
        'password' => 'password',
        'password_confirmation' => 'password',
      ]);

      $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

      return true;
    });
  }
}
