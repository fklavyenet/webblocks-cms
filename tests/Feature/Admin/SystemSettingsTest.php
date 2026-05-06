<?php

namespace Tests\Feature\Admin;

use App\Models\Locale;
use App\Models\SystemSetting;
use App\Models\User;
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
        $response->assertDontSee('Application name');
        $response->assertDontSee('Application slogan');
        $response->assertSee('Default locale');
        $response->assertSee('Timezone');
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
            'default_locale' => $locale->code,
            'timezone' => 'Europe/Istanbul',
            'visitor_consent_banner_enabled' => '1',
        ]);

        $response->assertRedirect(route('admin.system.settings.edit'));

        $this->assertSame($locale->code, SystemSetting::query()->where('key', 'system.default_locale')->value('value'));
        $this->assertSame('Europe/Istanbul', SystemSetting::query()->where('key', 'system.timezone')->value('value'));
        $this->assertSame('1', SystemSetting::query()->where('key', 'system.visitor_consent_banner_enabled')->value('value'));

        $followUp = $this->actingAs($user)->get(route('admin.system.settings.edit'));
        $followUp->assertSee('Europe/Istanbul');
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
            'default_locale' => $disabledLocale->code,
            'timezone' => 'Not/A_Timezone',
            'visitor_consent_banner_enabled' => '1',
        ]);

        $response->assertRedirect(route('admin.system.settings.edit'));
        $response->assertSessionHasErrors(['default_locale', 'timezone']);
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
}
