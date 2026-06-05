<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
  use RefreshDatabase;

  public function test_cms_profile_page_is_displayed_from_the_webadmin_namespace(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this
      ->actingAs($user)
      ->get('/webadmin/profile');

    $response->assertOk();
    $response->assertSee('Profile Information');
    $response->assertSee('Change Password');
  }

  public function test_cms_profile_information_can_be_updated(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this
      ->actingAs($user)
      ->put('/webadmin/profile', [
        'name' => 'Test User',
        'email' => 'test@example.com',
      ]);

    $response
      ->assertSessionHasNoErrors()
      ->assertRedirect('/webadmin/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
  }
}
