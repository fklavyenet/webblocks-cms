<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Site;

class ProfileManagementTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function topbar_dropdown_includes_profile_link_for_authenticated_cms_admin_users(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSee('href="'.route('admin.profile.edit').'"', false);
    $this->assertLessThan(
      strpos($response->getContent(), 'Logout'),
      strpos($response->getContent(), 'href="'.route('admin.profile.edit').'"'),
    );
  }

  #[Test]
  public function profile_page_renders_for_authenticated_cms_admin_users(): void
  {
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $user = User::factory()->editor()->create();
    $user->sites()->sync([$site->id]);

    $response = $this->actingAs($user)->get(route('admin.profile.edit'));

    $response->assertOk();
    $response->assertSee('Profile');
    $response->assertSee('Manage your account details and password.');
    $response->assertSee('Profile Information');
    $response->assertSee('Change Password');
    $response->assertSee('<div class="wb-card-header">', false);
    $response->assertSee('<h2 class="wb-card-title">Profile Information</h2>', false);
    $response->assertSee('<h2 class="wb-card-title">Change Password</h2>', false);
    $response->assertSee('action="'.route('admin.profile.update').'"', false);
    $response->assertSee('action="'.route('admin.profile.password.update').'"', false);
    $this->assertSame(2, substr_count($response->getContent(), 'class="wb-card-header"'));
    $this->assertSame(2, substr_count($response->getContent(), 'class="wb-card-footer"'));
  }

  #[Test]
  public function profile_update_changes_only_the_current_users_name_and_email(): void
  {
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $otherSite = Site::query()->create([
      'name' => 'Other Site',
      'handle' => 'other-site',
      'is_primary' => false,
    ]);
    $user = User::factory()->editor()->create([
      'name' => 'Original Name',
      'email' => 'original@example.com',
      'is_active' => true,
    ]);
    $user->sites()->sync([$site->id]);

    $response = $this->actingAs($user)->put(route('admin.profile.update'), [
      'name' => 'Updated Profile',
      'email' => 'updated-profile@example.com',
      'role' => User::ROLE_SUPER_ADMIN,
      'is_active' => false,
      'site_ids' => [$otherSite->id],
    ]);

    $response->assertRedirect(route('admin.profile.edit'));

    $user->refresh();
    $this->assertSame('Updated Profile', $user->name);
    $this->assertSame('updated-profile@example.com', $user->email);
    $this->assertSame(User::ROLE_EDITOR, $user->role);
    $this->assertTrue($user->is_active);
    $this->assertEquals([$site->id], $user->sites()->pluck('sites.id')->all());
  }

  #[Test]
  public function profile_update_does_not_edit_another_user(): void
  {
    $user = User::factory()->superAdmin()->create();
    $otherUser = User::factory()->editor()->create([
      'name' => 'Other User',
      'email' => 'other-user@example.com',
    ]);

    $response = $this->actingAs($user)->put(route('admin.profile.update'), [
      'id' => $otherUser->id,
      'user_id' => $otherUser->id,
      'name' => 'Current User',
      'email' => 'current-user@example.com',
    ]);

    $response->assertRedirect(route('admin.profile.edit'));
    $this->assertSame('Other User', $otherUser->fresh()->name);
    $this->assertSame('other-user@example.com', $otherUser->fresh()->email);
    $this->get('/webadmin/profile/'.$otherUser->id)->assertNotFound();
  }

  #[Test]
  public function password_change_requires_current_password(): void
  {
    $user = User::factory()->superAdmin()->create([
      'password' => 'current-password',
    ]);

    $response = $this->actingAs($user)
      ->from(route('admin.profile.edit'))
      ->put(route('admin.profile.password.update'), [
        'new_password' => 'new-password',
        'new_password_confirmation' => 'new-password',
      ]);

    $response->assertRedirect(route('admin.profile.edit'));
    $response->assertSessionHasErrors('current_password');
    $this->assertTrue(Hash::check('current-password', $user->fresh()->password));
  }

  #[Test]
  public function password_change_succeeds_with_valid_current_password_and_confirmation(): void
  {
    $user = User::factory()->superAdmin()->create([
      'password' => 'current-password',
    ]);

    $response = $this->actingAs($user)->put(route('admin.profile.password.update'), [
      'current_password' => 'current-password',
      'new_password' => 'new-password',
      'new_password_confirmation' => 'new-password',
    ]);

    $response->assertRedirect(route('admin.profile.edit'));
    $response->assertSessionHas('status', 'Password updated successfully.');
    $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    $this->assertNotNull($user->fresh()->remember_token);
  }

  #[Test]
  public function password_change_fails_with_wrong_current_password(): void
  {
    $user = User::factory()->superAdmin()->create([
      'password' => 'current-password',
    ]);

    $response = $this->actingAs($user)
      ->from(route('admin.profile.edit'))
      ->put(route('admin.profile.password.update'), [
        'current_password' => 'wrong-password',
        'new_password' => 'new-password',
        'new_password_confirmation' => 'new-password',
      ]);

    $response->assertRedirect(route('admin.profile.edit'));
    $response->assertSessionHasErrors('current_password');
    $this->assertTrue(Hash::check('current-password', $user->fresh()->password));
  }

  #[Test]
  public function guests_are_redirected_through_the_existing_cms_auth_flow(): void
  {
    $this->get(route('admin.profile.edit'))->assertRedirect(route('login'));
    $this->put(route('admin.profile.update'), [
      'name' => 'Guest',
      'email' => 'guest@example.com',
    ])->assertRedirect(route('login'));
  }

  #[Test]
  public function profile_password_fields_use_webblocks_ui_password_toggle_pattern(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.profile.edit'));

    $response->assertOk();
    $response->assertSee('data-wb-password-toggle', false);
    $response->assertSee('data-wb-target="#profile_current_password"', false);
    $response->assertSee('data-wb-target="#profile_new_password"', false);
    $response->assertSee('data-wb-target="#profile_new_password_confirmation"', false);
    $response->assertSee('wb-input-group', false);
    $response->assertSee('wb-input-addon-btn', false);
    $response->assertSee('wb-icon-eye', false);
    $this->assertSame(3, substr_count($response->getContent(), 'data-wb-password-toggle'));
  }
}
