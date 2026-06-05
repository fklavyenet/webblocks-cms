<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use WebBlocks\Cms\Support\WebBlocks;

class CmsAuthenticationTest extends TestCase
{
  use RefreshDatabase;

  public function test_cms_login_screen_can_be_rendered(): void
  {
    $response = $this->get('/webadmin/login');

    $response->assertStatus(200);
    $response->assertSee(WebBlocks::uiCssUrl(), false);
    $response->assertSee(WebBlocks::iconsCssUrl(), false);
    $response->assertSee(WebBlocks::uiJsUrl(), false);
    $response->assertSee('webblocks-ui.css', false);
    $response->assertSee('webblocks-icons.css', false);
    $response->assertSee('webblocks-ui.js', false);
    $response->assertSee('<script src="'.WebBlocks::uiJsUrl().'" defer></script>', false);
    $response->assertSee('webblocks-ui@v2.7.12', false);
    $response->assertDontSee('raw.githubusercontent.com/fklavyenet/webblocks-ui', false);
    $response->assertDontSee('@b43f92b', false);
    $response->assertDontSee('webblocks-ui.min.css', false);
    $response->assertDontSee('webblocks-icons.min.css', false);
    $response->assertDontSee('webblocks-ui.min.js', false);
    $response->assertDontSee('cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master', false);
  }

  public function test_cms_users_can_authenticate_using_the_login_screen(): void
  {
    $user = User::factory()->editor()->create();

    $response = $this->post('/webadmin/login', [
      'email' => $user->email,
      'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('admin.dashboard', absolute: false));
    $this->assertNotNull($user->fresh()->last_login_at);
  }

  public function test_cms_users_can_not_authenticate_with_invalid_password(): void
  {
    $user = User::factory()->editor()->create();

    $this->post('/webadmin/login', [
      'email' => $user->email,
      'password' => 'wrong-password',
    ]);

    $this->assertGuest();
  }

  public function test_inactive_cms_users_cannot_authenticate(): void
  {
    $user = User::factory()->editor()->inactive()->create();

    $response = $this->from('/webadmin/login')->post('/webadmin/login', [
      'email' => $user->email,
      'password' => 'password',
    ]);

    $response->assertRedirect('/webadmin/login');
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->assertNull($user->fresh()->last_login_at);
  }

  public function test_cms_users_can_logout(): void
  {
    $user = User::factory()->editor()->create();

    $response = $this->actingAs($user)->post('/webadmin/logout');

    $this->assertGuest();
    $response->assertRedirect(route('login'));
  }
}
