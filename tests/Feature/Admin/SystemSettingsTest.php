<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\WebBlocks;

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
  public function system_settings_page_uses_one_editable_form_with_one_action_row_and_read_only_information(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.settings.edit'));

    $response->assertOk();

    $document = $this->htmlDocument($response->getContent());
    $xpath = new DOMXPath($document);
    $form = $this->settingsForm($xpath);

    $this->assertSame(1, $xpath->query('//form[contains(@action, "/webadmin/system/settings")]')->length);
    $this->assertSame(1, $this->queryElements($xpath, './/button[normalize-space()="Save Changes"]', $form)->length);
    $this->assertSame(1, $this->queryElements($xpath, './/a[normalize-space()="Cancel"]', $form)->length);

    foreach (['default_locale', 'timezone', 'admin_listing_per_page', 'project_name', 'project_tagline'] as $fieldName) {
      $this->assertSame(1, $this->queryElements($xpath, './/*[@name="'.$fieldName.'"]', $form)->length);
    }

    $this->assertSame(2, $this->queryElements($xpath, './/*[@name="visitor_consent_banner_enabled"]', $form)->length);

    foreach (['project_name', 'project_tagline', 'default_locale', 'timezone', 'admin_listing_per_page'] as $fieldName) {
      $this->assertSame(0, $this->queryElements($xpath, './/input[@type="hidden" and @name="'.$fieldName.'"]', $form)->length);
    }

    $this->assertSame(1, $this->queryElements($xpath, './/input[@type="hidden" and @name="visitor_consent_banner_enabled" and @value="0"]', $form)->length);
    $this->assertSame(0, $this->queryElements($xpath, './/*[@name="version" or @name="environment"]', $form)->length);

    $response->assertSee('>General<', false);
    $response->assertSee('>Cookie settings<', false);
    $response->assertSee('>Project<', false);
    $response->assertSee('>Information<', false);
    $response->assertSee((string) WebBlocks::VERSION);
    $response->assertSee(app()->environment());
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
  public function admin_can_save_all_settings_together_with_cookie_banner_disabled(): void
  {
    $user = User::factory()->superAdmin()->create();
    $locale = Locale::query()->where('is_enabled', true)->firstOrFail();

    $response = $this->actingAs($user)->put(route('admin.system.settings.update'), [
      'project_name' => 'Project Atlas',
      'project_tagline' => 'Unified settings card',
      'default_locale' => $locale->code,
      'timezone' => 'UTC',
      'admin_listing_per_page' => '10',
      'visitor_consent_banner_enabled' => '0',
    ]);

    $response->assertRedirect(route('admin.system.settings.edit'));

    $this->assertSame('Project Atlas', SystemSetting::query()->where('key', SystemSettings::PROJECT_NAME)->value('value'));
    $this->assertSame('Unified settings card', SystemSetting::query()->where('key', SystemSettings::PROJECT_TAGLINE)->value('value'));
    $this->assertSame($locale->code, SystemSetting::query()->where('key', SystemSettings::DEFAULT_LOCALE)->value('value'));
    $this->assertSame('UTC', SystemSetting::query()->where('key', SystemSettings::TIMEZONE)->value('value'));
    $this->assertSame('10', SystemSetting::query()->where('key', SystemSettings::ADMIN_LISTING_PER_PAGE)->value('value'));
    $this->assertSame('0', SystemSetting::query()->where('key', SystemSettings::VISITOR_CONSENT_BANNER_ENABLED)->value('value'));
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

    $document = $this->htmlDocument($response->getContent());
    $xpath = new DOMXPath($document);
    $settingsCard = $this->settingsCard($xpath);

    $footers = $this->queryElements($xpath, './/div[contains(concat(" ", normalize-space(@class), " "), " wb-card-footer ")]', $settingsCard);
    $this->assertSame(1, $footers->length);

    $footer = $footers->item(0);
    $this->assertStringContainsString('Save Changes', $footer->textContent);
    $this->assertStringContainsString('Cancel', $footer->textContent);

    $footerAfterPerPageField = $this->queryElements($xpath, './/div[contains(concat(" ", normalize-space(@class), " "), " wb-card-footer ")][preceding::*[@id="settings_admin_listing_per_page"]]', $settingsCard);
    $this->assertSame(1, $footerAfterPerPageField->length);

    $bodiesAfterFooter = $this->queryElements($xpath, './/div[contains(concat(" ", normalize-space(@class), " "), " wb-card-body ")][preceding::div[contains(concat(" ", normalize-space(@class), " "), " wb-card-footer ")]]', $settingsCard);
    $this->assertSame(0, $bodiesAfterFooter->length);
  }

  private function htmlDocument(string $html): DOMDocument
  {
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    return $document;
  }

  private function settingsCard(DOMXPath $xpath): DOMElement
  {
    $settingsCard = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " wb-card ")][.//div[contains(concat(" ", normalize-space(@class), " "), " wb-card-header ")]/strong[normalize-space()="Settings"]]')->item(0);

    $this->assertInstanceOf(DOMElement::class, $settingsCard);

    return $settingsCard;
  }

  private function settingsForm(DOMXPath $xpath): DOMElement
  {
    $form = $this->queryElements($xpath, './/form[contains(@action, "/webadmin/system/settings")]', $this->settingsCard($xpath))->item(0);

    $this->assertInstanceOf(DOMElement::class, $form);

    return $form;
  }

  private function queryElements(DOMXPath $xpath, string $expression, ?DOMElement $context = null): DOMNodeList
  {
    return $xpath->query($expression, $context);
  }
}
