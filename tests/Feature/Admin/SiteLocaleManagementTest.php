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

    #[Test]
    public function site_domains_are_normalized_and_default_locale_is_preserved_on_save(): void
    {
        $user = User::factory()->create();
        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();

        $response = $this->actingAs($user)->put(route('admin.sites.update', $site), [
            'name' => $site->name,
            'handle' => 'Default Site',
            'domain' => 'https://PRIMARY.EXAMPLE.TEST/some/path',
            'is_primary' => 1,
            'locale_ids' => [$defaultLocale->id],
        ]);

        $response->assertRedirect(route('admin.sites.edit', $site));
        $this->assertSame('default-site', $site->fresh()->handle);
        $this->assertSame('primary.example.test', $site->fresh()->domain);
        $this->assertTrue($site->fresh()->hasEnabledLocale($defaultLocale));
    }

    #[Test]
    public function site_domain_must_be_unique_after_normalization(): void
    {
        $user = User::factory()->create();
        $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();

        Site::query()->create([
            'name' => 'Campaign',
            'handle' => 'campaign',
            'domain' => 'campaign.example.test',
            'is_primary' => false,
        ])->locales()->syncWithoutDetaching([$defaultLocale->id => ['is_enabled' => true]]);

        $response = $this->actingAs($user)->post(route('admin.sites.store'), [
            'name' => 'Campaign Copy',
            'handle' => 'campaign-copy',
            'domain' => 'https://CAMPAIGN.example.test/landing',
            'is_primary' => 0,
            'locale_ids' => [$defaultLocale->id],
        ]);

        $response->assertSessionHasErrors('domain');
    }

    #[Test]
    public function saving_a_second_primary_site_demotes_the_previous_primary_site(): void
    {
        $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
        $primary = Site::query()->where('is_primary', true)->firstOrFail();

        $secondary = Site::query()->create([
            'name' => 'Campaign',
            'handle' => 'campaign',
            'domain' => 'campaign.example.test',
            'is_primary' => true,
        ]);
        $secondary->locales()->syncWithoutDetaching([$defaultLocale->id => ['is_enabled' => true]]);

        $this->assertTrue($secondary->fresh()->is_primary);
        $this->assertFalse($primary->fresh()->is_primary);
    }

    #[Test]
    public function saving_a_second_default_locale_demotes_the_previous_default_locale(): void
    {
        $primaryDefault = Locale::query()->where('is_default', true)->firstOrFail();

        $locale = Locale::query()->create([
            'code' => 'pt-BR',
            'name' => 'Portuguese Brazil',
            'is_default' => true,
            'is_enabled' => true,
        ]);

        $this->assertSame('pt-br', $locale->fresh()->code);
        $this->assertTrue($locale->fresh()->is_default);
        $this->assertFalse($primaryDefault->fresh()->is_default);
    }
}
