<?php

namespace Tests\Feature\Admin;

use App\Models\Locale;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_view_settings_page_from_system_maintenance_navigation(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.system.settings.edit'));

        $response->assertOk();
        $response->assertSee('Settings');
        $response->assertSee('Application name');
        $response->assertSee('Application slogan');
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
            'app_name' => 'My WebBlocks',
            'app_slogan' => 'Compact system copy',
            'default_locale' => $locale->code,
            'timezone' => 'Europe/Istanbul',
            'visitor_consent_banner_enabled' => '1',
        ]);

        $response->assertRedirect(route('admin.system.settings.edit'));

        $this->assertSame('My WebBlocks', SystemSetting::query()->where('key', 'system.app_name')->value('value'));
        $this->assertSame('Compact system copy', SystemSetting::query()->where('key', 'system.app_slogan')->value('value'));
        $this->assertSame($locale->code, SystemSetting::query()->where('key', 'system.default_locale')->value('value'));
        $this->assertSame('Europe/Istanbul', SystemSetting::query()->where('key', 'system.timezone')->value('value'));
        $this->assertSame('1', SystemSetting::query()->where('key', 'system.visitor_consent_banner_enabled')->value('value'));

        $followUp = $this->actingAs($user)->get(route('admin.system.settings.edit'));
        $followUp->assertSee('My WebBlocks');
        $followUp->assertSee('Compact system copy');
        $followUp->assertSee('Europe/Istanbul');
    }

    #[Test]
    public function settings_require_valid_enabled_locale_and_non_blank_app_name(): void
    {
        $user = User::factory()->superAdmin()->create();
        $disabledLocale = Locale::query()->create([
            'code' => 'de',
            'name' => 'German',
            'is_enabled' => false,
        ]);

        $response = $this->actingAs($user)->from(route('admin.system.settings.edit'))->put(route('admin.system.settings.update'), [
            'app_name' => ' ',
            'app_slogan' => 'Bad payload',
            'default_locale' => $disabledLocale->code,
            'timezone' => 'Not/A_Timezone',
            'visitor_consent_banner_enabled' => '1',
        ]);

        $response->assertRedirect(route('admin.system.settings.edit'));
        $response->assertSessionHasErrors(['app_name', 'default_locale', 'timezone']);
    }
}
