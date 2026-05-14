<?php

namespace Tests\Feature\Admin;

use App\Models\Locale;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\System\SystemSettings;
use App\Support\WebBlocks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_view_system_settings_page_without_application_brand_fields(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.system.settings.edit'));

        $response->assertOk();
        $response->assertSee('System Settings');
        $response->assertSee('Project');
        $response->assertSee('Project Name');
        $response->assertSee('Project Tagline');
        $response->assertDontSee('Application name');
        $response->assertDontSee('Application slogan');
        $response->assertSee('Default locale');
        $response->assertSee('Timezone');
        $response->assertSee('Admin listing rows per page');
        $response->assertSee('Cookie settings');
        $response->assertSee('Show the public privacy settings banner when visitor reports are enabled.');
        $response->assertSee('Visitors who decline still contribute privacy-safe anonymous page view counts.');
        $response->assertSee('Application version');
        $response->assertSee('Environment');
        $response->assertSee('System');
        $response->assertSee('Maintenance');
    }

    #[Test]
    public function cookie_banner_checkbox_is_not_inside_the_general_card(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.system.settings.edit'));

        $response->assertOk();
        $response->assertSee('>General<', false);
        $response->assertSee('>Cookie settings<', false);
        $response->assertSeeInOrder(['>General<', '>Cookie settings<'], false);
    }

    #[Test]
    public function admin_can_save_minimal_system_settings(): void
    {
        $user = User::factory()->superAdmin()->create();
        $locale = Locale::query()->where('is_enabled', true)->firstOrFail();

        $response = $this->actingAs($user)->put(route('admin.system.settings.update'), [
            'project_name' => 'WebBlocks UI Docs',
            'project_tagline' => 'Install-specific admin context',
            'default_locale' => $locale->code,
            'timezone' => 'Europe/Istanbul',
            'admin_listing_per_page' => '12',
            'visitor_consent_banner_enabled' => '1',
        ]);

        $response->assertRedirect(route('admin.system.settings.edit'));

        $this->assertSame('WebBlocks UI Docs', SystemSetting::query()->where('key', 'system.project_name')->value('value'));
        $this->assertSame('Install-specific admin context', SystemSetting::query()->where('key', 'system.project_tagline')->value('value'));
        $this->assertSame($locale->code, SystemSetting::query()->where('key', 'system.default_locale')->value('value'));
        $this->assertSame('Europe/Istanbul', SystemSetting::query()->where('key', 'system.timezone')->value('value'));
        $this->assertSame('12', SystemSetting::query()->where('key', SystemSettings::ADMIN_LISTING_PER_PAGE)->value('value'));
        $this->assertSame('1', SystemSetting::query()->where('key', 'system.visitor_consent_banner_enabled')->value('value'));

        $followUp = $this->actingAs($user)->get(route('admin.system.settings.edit'));
        $followUp->assertSee('Europe/Istanbul');
        $followUp->assertSee('WebBlocks UI Docs');
        $followUp->assertSee('Install-specific admin context');
        $followUp->assertSee('value="12"', false);
    }

    #[Test]
    public function settings_require_valid_enabled_locale_and_timezone(): void
    {
        $user = User::factory()->superAdmin()->create();
        $disabledLocale = Locale::query()->create([
            'code' => 'de',
            'name' => 'German',
            'is_enabled' => false,
        ]);

        $response = $this->actingAs($user)->from(route('admin.system.settings.edit'))->put(route('admin.system.settings.update'), [
            'project_name' => str_repeat('a', 256),
            'project_tagline' => str_repeat('b', 256),
            'default_locale' => $disabledLocale->code,
            'timezone' => 'Not/A_Timezone',
            'visitor_consent_banner_enabled' => '1',
        ]);

        $response->assertRedirect(route('admin.system.settings.edit'));
        $response->assertSessionHasErrors(['project_name', 'project_tagline', 'default_locale', 'timezone']);
    }

    #[Test]
    public function settings_require_admin_listing_rows_per_page_to_be_an_integer_in_range(): void
    {
        $user = User::factory()->superAdmin()->create();
        $locale = Locale::query()->where('is_enabled', true)->firstOrFail();

        $response = $this->actingAs($user)->from(route('admin.system.settings.edit'))->put(route('admin.system.settings.update'), [
            'project_name' => 'WebBlocks UI Docs',
            'project_tagline' => 'Install-specific admin context',
            'default_locale' => $locale->code,
            'timezone' => 'UTC',
            'admin_listing_per_page' => '101',
            'visitor_consent_banner_enabled' => '1',
        ]);

        $response->assertRedirect(route('admin.system.settings.edit'));
        $response->assertSessionHasErrors(['admin_listing_per_page']);
    }

    #[Test]
    public function admin_listing_rows_per_page_falls_back_to_default_when_missing_or_invalid(): void
    {
        $settings = app(SystemSettings::class);

        $this->assertSame(15, $settings->adminListingPerPage());

        SystemSetting::query()->updateOrCreate(['key' => SystemSettings::ADMIN_LISTING_PER_PAGE], ['value' => '']);
        $this->assertSame(15, $settings->adminListingPerPage());

        SystemSetting::query()->updateOrCreate(['key' => SystemSettings::ADMIN_LISTING_PER_PAGE], ['value' => 'abc']);
        $this->assertSame(15, $settings->adminListingPerPage());

        SystemSetting::query()->updateOrCreate(['key' => SystemSettings::ADMIN_LISTING_PER_PAGE], ['value' => '0']);
        $this->assertSame(15, $settings->adminListingPerPage());

        SystemSetting::query()->updateOrCreate(['key' => SystemSettings::ADMIN_LISTING_PER_PAGE], ['value' => '200']);
        $this->assertSame(15, $settings->adminListingPerPage());
    }

    #[Test]
    public function legacy_application_name_and_slogan_settings_do_not_change_admin_product_identity(): void
    {
        $user = User::factory()->superAdmin()->create();

        SystemSetting::query()->updateOrCreate(['key' => 'system.app_name'], ['value' => 'Changed Site Name']);
        SystemSetting::query()->updateOrCreate(['key' => 'system.app_slogan'], ['value' => 'Changed Site Slogan']);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee(WebBlocks::name());
        $response->assertSee(WebBlocks::slogan());
        $response->assertSee(WebBlocks::name().' v'.WebBlocks::VERSION);
        $response->assertDontSee('Changed Site Name');
        $response->assertDontSee('Changed Site Slogan');
    }

    #[Test]
    public function admin_topbar_and_browser_title_use_project_identity_when_present(): void
    {
        $user = User::factory()->superAdmin()->create();

        SystemSetting::query()->updateOrCreate(['key' => 'system.project_name'], ['value' => 'WebBlocks UI Docs']);
        SystemSetting::query()->updateOrCreate(['key' => 'system.project_tagline'], ['value' => 'Install-specific admin context']);

        $response = $this->actingAs($user)->get(route('admin.system.settings.edit'));

        $response->assertOk();
        $response->assertSee('<title>WebBlocks UI Docs · System Settings · WebBlocks CMS</title>', false);
        $response->assertSee('<span class="wb-navbar-brand">', false);
        $response->assertSee('WebBlocks UI Docs');
        $response->assertSee('Install-specific admin context');
        $response->assertSee('WebBlocks CMS v'.WebBlocks::VERSION);
        $response->assertDontSee('<title>WebBlocks CMS · WebBlocks CMS</title>', false);
    }

    #[Test]
    public function admin_topbar_falls_back_to_primary_site_name_without_using_site_tagline(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $site->update([
            'display_name' => 'Primary Public Site',
            'tagline' => 'Public site tagline',
        ]);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Primary Public Site');
        $response->assertDontSee('Public site tagline');
        $response->assertSee('<title>Admin Dashboard · WebBlocks CMS</title>', false);
    }

    #[Test]
    public function general_card_actions_render_in_the_card_footer(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.system.settings.edit'));

        $response->assertOk();

        $html = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/<div class="wb-card">\s*<div class="wb-card-header"><strong>General<\/strong><\/div>.*?<form id="general-settings-form"[\s\S]*?<\/form>\s*<div class="wb-card-footer">\s*<div class="wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap" data-admin-form-actions>/s',
            $html,
        );
    }
}
