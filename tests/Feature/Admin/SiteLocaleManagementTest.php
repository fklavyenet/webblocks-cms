<?php

namespace Tests\Feature\Admin;

use App\Models\Locale;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SiteLocaleManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sites_index_renders_primary_and_locale_context(): void
    {
        $user = User::factory()->create();
        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $locale = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);

        $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);

        $response = $this->actingAs($user)->get(route('admin.sites.index'));

        $response->assertOk();
        $response->assertSee('Sites');
        $response->assertSee($site->name);
        $response->assertSee('Primary');
        $response->assertSee('tr');
    }

    #[Test]
    public function locales_index_renders_default_and_enabled_context(): void
    {
        $user = User::factory()->create();
        Locale::query()->create([
            'code' => 'de',
            'name' => 'German',
            'is_default' => false,
            'is_enabled' => false,
        ]);

        $response = $this->actingAs($user)->get(route('admin.locales.index'));

        $response->assertOk();
        $response->assertSee('Locales');
        $response->assertSee('en');
        $response->assertSee('Default');
        $response->assertSee('German');
        $response->assertSee('Disabled');
    }
}
