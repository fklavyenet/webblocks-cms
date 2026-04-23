<?php

namespace Tests\Feature\Admin;

use App\Models\Locale;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SiteDeleteAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function delete_confirmation_screen_is_available_for_a_normal_site(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = $this->createSecondarySite();

        $response = $this->actingAs($user)->get(route('admin.sites.delete', $site));

        $response->assertOk();
        $response->assertSee('Delete Site');
        $response->assertSee('Campaign');
    }

    #[Test]
    public function delete_requires_explicit_confirmation_checkbox(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = $this->createSecondarySite();

        $response = $this->actingAs($user)->delete(route('admin.sites.destroy', $site), []);

        $response->assertSessionHasErrors('confirm_delete');
        $this->assertDatabaseHas('sites', ['id' => $site->id]);
    }

    #[Test]
    public function delete_removes_site_after_confirmation_and_redirects_to_index(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = $this->createSecondarySite();

        $response = $this->actingAs($user)->delete(route('admin.sites.destroy', $site), [
            'confirm_delete' => '1',
        ]);

        $response->assertRedirect(route('admin.sites.index'));
        $response->assertSessionHas('status');
        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
    }

    #[Test]
    public function primary_site_has_blocked_delete_path(): void
    {
        $user = User::factory()->superAdmin()->create();
        $primary = Site::primary();
        $this->createSecondarySite();

        $response = $this->actingAs($user)->get(route('admin.sites.delete', $primary));

        $response->assertOk();
        $response->assertSee('Delete Blocked');
        $response->assertSee('Primary site cannot be deleted.');
    }

    private function createSecondarySite(): Site
    {
        $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();

        $site = Site::query()->create([
            'name' => 'Campaign',
            'handle' => 'campaign',
            'domain' => 'campaign.example.test',
            'is_primary' => false,
        ]);

        $site->locales()->sync([$defaultLocale->id => ['is_enabled' => true]]);

        return $site;
    }
}
