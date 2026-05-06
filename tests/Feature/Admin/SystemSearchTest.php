<?php

namespace Tests\Feature\Admin;

use App\Models\PublicSearchIndex;
use App\Models\User;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemSearchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function super_admin_can_view_search_status_screen(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.system.search.index'));

        $response->assertOk();
        $response->assertSee('Search');
        $response->assertSee('Total indexed rows');
    }

    #[Test]
    public function non_super_admin_cannot_access_search_status_screen(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $user = User::factory()->siteAdmin()->create();

        $this->actingAs($user)->get(route('admin.system.search.index'))->assertForbidden();
    }

    #[Test]
    public function rebuild_action_returns_success_message(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->post(route('admin.system.search.rebuild'))
            ->assertRedirect(route('admin.system.search.index'));

        $this->assertSame(0, PublicSearchIndex::query()->count());
    }
}
