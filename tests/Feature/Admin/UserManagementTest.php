<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_open_users_index(): void
    {
        $admin = User::factory()->admin()->create();
        $managedUser = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('Users');
        $response->assertSee($managedUser->email);
    }

    #[Test]
    public function non_admin_cannot_access_users_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.users.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function admin_can_create_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Editor User',
            'email' => 'editor@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_admin' => '1',
            'is_active' => '1',
        ]);

        $user = User::query()->where('email', 'editor@example.com')->first();

        $response->assertRedirect(route('admin.users.edit', $user));
        $this->assertNotNull($user);
        $this->assertTrue($user->is_admin);
        $this->assertTrue($user->is_active);
    }

    #[Test]
    public function admin_can_edit_user(): void
    {
        $admin = User::factory()->admin()->create();
        $managedUser = User::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $managedUser), [
            'name' => 'Updated User',
            'email' => 'updated@example.com',
            'password' => '',
            'password_confirmation' => '',
            'is_admin' => '0',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.users.edit', $managedUser));

        $managedUser->refresh();
        $this->assertSame('Updated User', $managedUser->name);
        $this->assertSame('updated@example.com', $managedUser->email);
        $this->assertFalse($managedUser->is_admin);
        $this->assertTrue($managedUser->is_active);
    }

    #[Test]
    public function admin_can_deactivate_user(): void
    {
        $admin = User::factory()->admin()->create();
        $managedUser = User::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $managedUser), [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'password' => '',
            'password_confirmation' => '',
            'is_admin' => '0',
        ]);

        $response->assertRedirect(route('admin.users.edit', $managedUser));
        $this->assertFalse($managedUser->fresh()->is_active);
    }

    #[Test]
    public function admin_cannot_delete_the_last_active_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $admin));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHasErrors('user_lifecycle');
        $this->assertNotNull($admin->fresh());
    }

    #[Test]
    public function admin_cannot_deactivate_the_last_active_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->from(route('admin.users.edit', $admin))->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'password' => '',
            'password_confirmation' => '',
            'is_admin' => '1',
        ]);

        $response->assertRedirect(route('admin.users.edit', $admin));
        $response->assertSessionHasErrors('user_lifecycle');
        $this->assertTrue($admin->fresh()->is_active);
    }

    #[Test]
    public function admin_can_delete_a_normal_non_critical_user(): void
    {
        $admin = User::factory()->admin()->create();
        $managedUser = User::factory()->create();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $managedUser));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertNull($managedUser->fresh());
    }
}
