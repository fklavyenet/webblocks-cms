<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminSidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function settings_page_marks_maintenance_group_and_settings_item_active(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.system.settings.edit'));

        $response->assertOk();
        $response->assertSee('wb-nav-group-toggle is-active', false);
        $response->assertSee('href="'.route('admin.system.settings.edit').'"', false);
        $response->assertSee('class="wb-nav-group-item is-active"', false);
    }

    #[Test]
    public function locales_page_marks_system_group_and_locales_item_active(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.locales.index'));

        $response->assertOk();
        $response->assertSee('wb-nav-group-toggle is-active', false);
        $response->assertSee('href="'.route('admin.locales.index').'"', false);
        $response->assertSee('class="wb-nav-group-item is-active"', false);
    }
}
