<?php

namespace Tests\Feature\Admin;

use App\Models\Site;
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
        $admin = User::factory()->superAdmin()->create();
        $managedUser = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('Users');
        $response->assertSee($managedUser->email);
    }

    #[Test]
    public function users_index_can_search_by_name(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $matchingUser = User::factory()->create(['name' => 'Osman Editor']);
        $hiddenUser = User::factory()->create(['name' => 'Jane Writer']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'Osman']));

        $response->assertOk();
        $response->assertSee($matchingUser->email);
        $response->assertDontSee($hiddenUser->email);
        $response->assertSee('name="q"', false);
        $response->assertSee('value="Osman"', false);
    }

    #[Test]
    public function users_index_can_search_by_email(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $matchingUser = User::factory()->create(['email' => 'osman@example.com']);
        $hiddenUser = User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'osman@example.com']));

        $response->assertOk();
        $response->assertSee($matchingUser->email);
        $response->assertDontSee($hiddenUser->email);
    }

    #[Test]
    public function users_index_can_filter_active_users(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $activeUser = User::factory()->create(['name' => 'Active User']);
        $inactiveUser = User::factory()->inactive()->create(['name' => 'Inactive User']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['status' => 'active']));

        $response->assertOk();
        $response->assertSee($activeUser->email);
        $response->assertDontSee($inactiveUser->email);
    }

    #[Test]
    public function users_index_can_filter_inactive_users(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $activeUser = User::factory()->create(['name' => 'Active User']);
        $inactiveUser = User::factory()->inactive()->create(['name' => 'Inactive User']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['status' => 'inactive']));

        $response->assertOk();
        $response->assertSee($inactiveUser->email);
        $response->assertDontSee($activeUser->email);
    }

    #[Test]
    public function users_index_can_filter_super_admin_users(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $adminUser = User::factory()->superAdmin()->create(['email' => 'admin-filter@example.com']);
        $nonAdminUser = User::factory()->editor()->create(['email' => 'user-filter@example.com']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['role' => User::ROLE_SUPER_ADMIN]));

        $response->assertOk();
        $response->assertSee($adminUser->email);
        $response->assertDontSee($nonAdminUser->email);
    }

    #[Test]
    public function users_index_can_filter_editor_users(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $adminUser = User::factory()->superAdmin()->create(['email' => 'admin-filter@example.com']);
        $nonAdminUser = User::factory()->editor()->create(['email' => 'user-filter@example.com']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['role' => User::ROLE_EDITOR]));

        $response->assertOk();
        $response->assertSee($nonAdminUser->email);
        $response->assertDontSee($adminUser->email);
    }

    #[Test]
    public function users_index_supports_combined_search_and_filters(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $matchingUser = User::factory()->superAdmin()->create([
            'name' => 'Osman Active Admin',
            'email' => 'osman-admin@example.com',
            'is_active' => true,
        ]);
        $wrongRoleUser = User::factory()->editor()->create([
            'name' => 'Osman Member',
            'email' => 'osman-user@example.com',
            'is_active' => true,
        ]);
        $wrongStatusUser = User::factory()->superAdmin()->inactive()->create([
            'name' => 'Osman Inactive Admin',
            'email' => 'osman-inactive@example.com',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index', [
            'q' => 'osman',
            'status' => 'active',
            'role' => User::ROLE_SUPER_ADMIN,
        ]));

        $response->assertOk();
        $response->assertSee($matchingUser->email);
        $response->assertDontSee($wrongRoleUser->email);
        $response->assertDontSee($wrongStatusUser->email);
    }

    #[Test]
    public function pagination_links_preserve_active_user_filters(): void
    {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->count(16)->sequence(fn ($sequence) => [
            'name' => 'Member User '.($sequence->index + 1),
            'email' => 'member-user-'.($sequence->index + 1).'@example.com',
        ])->create();

        $response = $this->actingAs($admin)->get(route('admin.users.index', [
            'q' => 'member',
            'status' => 'active',
            'role' => User::ROLE_EDITOR,
        ]));

        $response->assertOk();
        $response->assertSee('page=2', false);
        $response->assertSee('q=member', false);
        $response->assertSee('status=active', false);
        $response->assertSee('role='.User::ROLE_EDITOR, false);
    }

    #[Test]
    public function non_super_admin_cannot_access_users_index(): void
    {
        $user = User::factory()->editor()->create();

        $response = $this->actingAs($user)->get(route('admin.users.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function super_admin_can_create_site_admin_with_site_assignments(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $primarySite = Site::query()->where('is_primary', true)->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Editor User',
            'email' => 'editor@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => User::ROLE_SITE_ADMIN,
            'is_active' => '1',
            'site_ids' => [$primarySite->id],
        ]);

        $user = User::query()->where('email', 'editor@example.com')->first();

        $response->assertRedirect(route('admin.users.edit', $user));
        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_SITE_ADMIN, $user->role);
        $this->assertFalse($user->is_admin);
        $this->assertTrue($user->is_active);
        $this->assertEquals([$primarySite->id], $user->sites()->pluck('sites.id')->all());
    }

    #[Test]
    public function super_admin_can_edit_user_role_and_site_assignments(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $primarySite = Site::query()->where('is_primary', true)->firstOrFail();
        $secondarySite = Site::query()->create([
            'name' => 'Secondary Site',
            'handle' => 'secondary-site',
            'is_primary' => false,
        ]);
        $managedUser = User::factory()->siteAdmin()->create();
        $managedUser->sites()->sync([$primarySite->id]);

        $response = $this->actingAs($admin)->put(route('admin.users.update', $managedUser), [
            'name' => 'Updated User',
            'email' => 'updated@example.com',
            'password' => '',
            'password_confirmation' => '',
            'role' => User::ROLE_EDITOR,
            'is_active' => '1',
            'site_ids' => [$secondarySite->id],
        ]);

        $response->assertRedirect(route('admin.users.edit', $managedUser));

        $managedUser->refresh();
        $this->assertSame('Updated User', $managedUser->name);
        $this->assertSame('updated@example.com', $managedUser->email);
        $this->assertSame(User::ROLE_EDITOR, $managedUser->role);
        $this->assertFalse($managedUser->is_admin);
        $this->assertTrue($managedUser->is_active);
        $this->assertEquals([$secondarySite->id], $managedUser->sites()->pluck('sites.id')->all());
    }

    #[Test]
    public function super_admin_can_deactivate_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $managedUser = User::factory()->editor()->create();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $managedUser), [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'password' => '',
            'password_confirmation' => '',
            'role' => User::ROLE_EDITOR,
            'site_ids' => $managedUser->sites()->pluck('sites.id')->all(),
        ]);

        $response->assertRedirect(route('admin.users.edit', $managedUser));
        $this->assertFalse($managedUser->fresh()->is_active);
    }

    #[Test]
    public function super_admin_cannot_delete_the_last_active_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $admin));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHasErrors('user_lifecycle');
        $this->assertNotNull($admin->fresh());
    }

    #[Test]
    public function super_admin_cannot_deactivate_the_last_active_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->from(route('admin.users.edit', $admin))->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'password' => '',
            'password_confirmation' => '',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $response->assertRedirect(route('admin.users.edit', $admin));
        $response->assertSessionHasErrors('user_lifecycle');
        $this->assertTrue($admin->fresh()->is_active);
    }

    #[Test]
    public function super_admin_can_delete_a_normal_non_critical_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $managedUser = User::factory()->editor()->create();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $managedUser));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertNull($managedUser->fresh());
    }

    #[Test]
    public function site_admin_and_editor_require_at_least_one_site_assignment(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.create'))
            ->post(route('admin.users.store'), [
                'name' => 'Editor User',
                'email' => 'editor-no-site@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => User::ROLE_EDITOR,
                'is_active' => '1',
                'site_ids' => [],
            ]);

        $response->assertRedirect(route('admin.users.create'));
        $response->assertSessionHasErrors('site_ids');
        $this->assertDatabaseMissing('users', ['email' => 'editor-no-site@example.com']);
    }
}
