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
    $response->assertSee('Mail');
    $response->assertSee('Use environment mail config');
    $response->assertSee('Diagnostics');
    $response->assertSee('Password configured');
    $response->assertSee('no');
    $response->assertSee('Privacy');
    $response->assertSee('Show the public privacy settings banner when visitor reports are enabled.');
    $response->assertSee('Visitors who decline still contribute privacy-safe anonymous page view counts.');
    $response->assertSee('Application version');
    $response->assertSee('Environment');
    $response->assertSee('System');
    $response->assertSee('Maintenance');
  }

  #[Test]
  public function system_settings_page_uses_separate_cards_forms_and_read_only_runtime_information(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.settings.edit'));

    $response->assertOk();

    $document = $this->htmlDocument($response->getContent());
    $xpath = new DOMXPath($document);
    $this->assertSame(4, $xpath->query('//form[contains(@action, "/webadmin/system/settings")]')->length);
    $this->assertSame(4, $xpath->query('//button[normalize-space()="Save Changes"]')->length);

    foreach (['General', 'Project Identity', 'Mail', 'Privacy', 'Runtime Information'] as $cardTitle) {
      $this->settingsCard($xpath, $cardTitle);
    }

    $runtimeCard = $this->settingsCard($xpath, 'Runtime Information');
    $this->assertSame(0, $this->queryElements($xpath, './/form', $runtimeCard)->length);
    $this->assertSame(0, $this->queryElements($xpath, './/button[normalize-space()="Save Changes"]', $runtimeCard)->length);
    $this->assertSame(0, $this->queryElements($xpath, './/*[@name="version" or @name="environment"]', $runtimeCard)->length);

    $mailCard = $this->settingsCard($xpath, 'Mail');
    $this->assertSame(1, $this->queryElements($xpath, './/*[@name="cms_mail_mode"]', $mailCard)->length);
    $this->assertSame(0, $this->queryElements($xpath, './/*[@name="cms_mail_host" or @name="cms_mail_password" or @name="cms_mail_from_address"]', $mailCard)->length);
    $this->assertSame(1, $this->queryElements($xpath, './/*[@data-wb-mail-diagnostics]//table[contains(concat(" ", normalize-space(@class), " "), " wb-table ")]', $mailCard)->length);
    $this->assertSame(1, $this->queryElements($xpath, './/*[@data-wb-mail-diagnostics]//th[normalize-space()="Setting"]', $mailCard)->length);
    $this->assertSame(1, $this->queryElements($xpath, './/*[@data-wb-mail-diagnostics]//th[normalize-space()="Value"]', $mailCard)->length);
    $this->assertSame(12, $this->queryElements($xpath, './/*[@data-wb-mail-diagnostic-item]', $mailCard)->length);
    $this->assertSame(12, $this->queryElements($xpath, './/tr[@data-wb-mail-diagnostic-item]/th[@data-wb-mail-diagnostic-label]', $mailCard)->length);
    $this->assertSame(12, $this->queryElements($xpath, './/tr[@data-wb-mail-diagnostic-item]/td[@data-wb-mail-diagnostic-value]', $mailCard)->length);
    $this->assertSame(1, $this->queryElements($xpath, './/tr[@data-wb-mail-diagnostic-item][th[normalize-space()="Password configured"] and td[normalize-space()="no"]]', $mailCard)->length);
    $this->assertSame(0, $this->queryElements($xpath, './/div[contains(concat(" ", normalize-space(@class), " "), " wb-settings-row ")][.//*[@data-wb-mail-diagnostics]]', $mailCard)->length);

    foreach (['general', 'project', 'mail', 'privacy'] as $section) {
      $this->assertSame(1, $xpath->query('//input[@type="hidden" and @name="section" and @value="'.$section.'"]')->length);
    }

    $response->assertSee('>General<', false);
    $response->assertSee('>Mail<', false);
    $response->assertSee('>Privacy<', false);
    $response->assertSee('>Project Identity<', false);
    $response->assertSee('>Runtime Information<', false);
    $response->assertSee((string) WebBlocks::VERSION);
    $response->assertSee(app()->environment());
  }

  #[Test]
  public function mail_custom_fields_render_only_when_custom_mode_is_selected(): void
  {
    $user = User::factory()->superAdmin()->create();

    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_MODE], ['value' => SystemSettings::CMS_MAIL_MODE_CUSTOM]);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_MAILER], ['value' => 'smtp']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_HOST], ['value' => 'smtp.example.test']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_PORT], ['value' => '587']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_FROM_ADDRESS], ['value' => 'cms@example.test']);

    $response = $this->actingAs($user)->get(route('admin.system.settings.edit'));

    $response->assertOk();

    $document = $this->htmlDocument($response->getContent());
    $xpath = new DOMXPath($document);
    $mailCard = $this->settingsCard($xpath, 'Mail');

    $this->assertSame(1, $this->queryElements($xpath, './/*[@name="cms_mail_host"]', $mailCard)->length);
    $this->assertSame(1, $this->queryElements($xpath, './/*[@name="cms_mail_password"]', $mailCard)->length);
    $this->assertSame(1, $this->queryElements($xpath, './/*[@name="cms_mail_from_address"]', $mailCard)->length);
    $this->assertSame(1, $this->queryElements($xpath, './/tr[@data-wb-mail-diagnostic-item][th[normalize-space()="From address"]]//a[@href="mailto:cms@example.test" and normalize-space()="cms@example.test"]', $mailCard)->length);
  }

  #[Test]
  public function general_save_updates_only_general_settings(): void
  {
    $user = User::factory()->superAdmin()->create();
    $locale = Locale::query()->where('is_enabled', true)->firstOrFail();

    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::PROJECT_NAME], ['value' => 'Existing Project']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::VISITOR_CONSENT_BANNER_ENABLED], ['value' => '0']);

    $response = $this->actingAs($user)->put(route('admin.system.settings.update'), [
      'section' => 'general',
      'default_locale' => $locale->code,
      'timezone' => 'Europe/Istanbul',
      'admin_listing_per_page' => '12',
    ]);

    $response->assertRedirect(route('admin.system.settings.edit'));

    $this->assertSame($locale->code, SystemSetting::query()->where('key', 'system.default_locale')->value('value'));
    $this->assertSame('Europe/Istanbul', SystemSetting::query()->where('key', 'system.timezone')->value('value'));
    $this->assertSame('12', SystemSetting::query()->where('key', SystemSettings::ADMIN_LISTING_PER_PAGE)->value('value'));
    $this->assertSame('Existing Project', SystemSetting::query()->where('key', SystemSettings::PROJECT_NAME)->value('value'));
    $this->assertSame('0', SystemSetting::query()->where('key', SystemSettings::VISITOR_CONSENT_BANNER_ENABLED)->value('value'));

    $followUp = $this->actingAs($user)->get(route('admin.system.settings.edit'));
    $followUp->assertSee('Europe/Istanbul');
    $followUp->assertSee('value="12"', false);
  }

  #[Test]
  public function project_identity_save_updates_only_project_identity_settings(): void
  {
    $user = User::factory()->superAdmin()->create();
    $locale = Locale::query()->where('is_enabled', true)->firstOrFail();
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::DEFAULT_LOCALE], ['value' => $locale->code]);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::VISITOR_CONSENT_BANNER_ENABLED], ['value' => '1']);

    $response = $this->actingAs($user)->put(route('admin.system.settings.update'), [
      'section' => 'project',
      'project_name' => 'Project Atlas',
      'project_tagline' => 'Focused settings cards',
    ]);

    $response->assertRedirect(route('admin.system.settings.edit'));

    $this->assertSame('Project Atlas', SystemSetting::query()->where('key', SystemSettings::PROJECT_NAME)->value('value'));
    $this->assertSame('Focused settings cards', SystemSetting::query()->where('key', SystemSettings::PROJECT_TAGLINE)->value('value'));
    $this->assertSame($locale->code, SystemSetting::query()->where('key', SystemSettings::DEFAULT_LOCALE)->value('value'));
    $this->assertSame('1', SystemSetting::query()->where('key', SystemSettings::VISITOR_CONSENT_BANNER_ENABLED)->value('value'));
  }

  #[Test]
  public function privacy_save_updates_only_privacy_settings(): void
  {
    $user = User::factory()->superAdmin()->create();
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::PROJECT_NAME], ['value' => 'Existing Project']);

    $response = $this->actingAs($user)->put(route('admin.system.settings.update'), [
      'section' => 'privacy',
      'visitor_consent_banner_enabled' => '0',
    ]);

    $response->assertRedirect(route('admin.system.settings.edit'));

    $this->assertSame('0', SystemSetting::query()->where('key', SystemSettings::VISITOR_CONSENT_BANNER_ENABLED)->value('value'));
    $this->assertSame('Existing Project', SystemSetting::query()->where('key', SystemSettings::PROJECT_NAME)->value('value'));
  }

  #[Test]
  public function saving_environment_mail_mode_does_not_require_smtp_fields(): void
  {
    $user = User::factory()->superAdmin()->create();
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::PROJECT_NAME], ['value' => 'Existing Project']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_HOST], ['value' => 'smtp.example.test']);

    $response = $this->actingAs($user)->put(route('admin.system.settings.update'), [
      'section' => 'mail',
      'cms_mail_mode' => 'env',
    ]);

    $response->assertRedirect(route('admin.system.settings.edit'));
    $response->assertSessionHasNoErrors();

    $this->assertSame(SystemSettings::CMS_MAIL_MODE_ENV, SystemSetting::query()->where('key', SystemSettings::CMS_MAIL_MODE)->value('value'));
    $this->assertSame('smtp.example.test', SystemSetting::query()->where('key', SystemSettings::CMS_MAIL_HOST)->value('value'));
    $this->assertSame('Existing Project', SystemSetting::query()->where('key', SystemSettings::PROJECT_NAME)->value('value'));
  }

  #[Test]
  public function mail_save_updates_only_mail_settings(): void
  {
    $user = User::factory()->superAdmin()->create();
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::PROJECT_NAME], ['value' => 'Existing Project']);

    $response = $this->actingAs($user)->put(route('admin.system.settings.update'), [
      'section' => 'mail',
      'cms_mail_mode' => 'custom',
      'cms_mail_mailer' => 'smtp',
      'cms_mail_host' => 'smtp.example.test',
      'cms_mail_port' => '587',
      'cms_mail_encryption' => 'tls',
      'cms_mail_username' => 'mailer@example.test',
      'cms_mail_password' => 'stored-secret',
      'cms_mail_clear_password' => '0',
      'cms_mail_from_address' => 'cms@example.test',
      'cms_mail_from_name' => 'WebBlocks CMS',
      'cms_mail_reply_to_address' => 'reply@example.test',
      'cms_mail_timeout' => '20',
    ]);

    $response->assertRedirect(route('admin.system.settings.edit'));
    $this->assertSame(SystemSettings::CMS_MAIL_MODE_CUSTOM, SystemSetting::query()->where('key', SystemSettings::CMS_MAIL_MODE)->value('value'));
    $this->assertSame('smtp.example.test', SystemSetting::query()->where('key', SystemSettings::CMS_MAIL_HOST)->value('value'));
    $this->assertSame('stored-secret', SystemSetting::query()->where('key', SystemSettings::CMS_MAIL_PASSWORD)->value('value'));
    $this->assertSame('Existing Project', SystemSetting::query()->where('key', SystemSettings::PROJECT_NAME)->value('value'));
  }

  #[Test]
  public function custom_smtp_mail_mode_validates_required_mail_fields(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->from(route('admin.system.settings.edit'))->put(route('admin.system.settings.update'), [
      'section' => 'mail',
      'cms_mail_mode' => 'custom',
      'cms_mail_mailer' => 'smtp',
      'cms_mail_clear_password' => '0',
    ]);

    $response->assertRedirect(route('admin.system.settings.edit'));
    $response->assertSessionHasErrors(['cms_mail_host', 'cms_mail_port', 'cms_mail_from_address']);
  }

  #[Test]
  public function cms_mail_secret_is_preserved_when_password_field_is_blank(): void
  {
    $user = User::factory()->superAdmin()->create();

    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_PASSWORD], ['value' => 'stored-secret']);

    $response = $this->actingAs($user)->put(route('admin.system.settings.update'), [
      'section' => 'mail',
      'cms_mail_mode' => 'custom',
      'cms_mail_mailer' => 'smtp',
      'cms_mail_host' => 'smtp.example.test',
      'cms_mail_port' => '587',
      'cms_mail_encryption' => 'tls',
      'cms_mail_username' => 'mailer@example.test',
      'cms_mail_password' => '',
      'cms_mail_clear_password' => '0',
      'cms_mail_from_address' => 'cms@example.test',
      'cms_mail_from_name' => 'WebBlocks CMS',
    ]);

    $response->assertRedirect(route('admin.system.settings.edit'));
    $this->assertSame('stored-secret', SystemSetting::query()->where('key', SystemSettings::CMS_MAIL_PASSWORD)->value('value'));
  }

  #[Test]
  public function stored_cms_mail_secret_can_be_cleared(): void
  {
    $user = User::factory()->superAdmin()->create();

    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_PASSWORD], ['value' => 'stored-secret']);

    $response = $this->actingAs($user)->put(route('admin.system.settings.update'), [
      'section' => 'mail',
      'cms_mail_mode' => 'custom',
      'cms_mail_mailer' => 'smtp',
      'cms_mail_host' => 'smtp.example.test',
      'cms_mail_port' => '587',
      'cms_mail_encryption' => 'tls',
      'cms_mail_username' => 'mailer@example.test',
      'cms_mail_password' => '',
      'cms_mail_clear_password' => '1',
      'cms_mail_from_address' => 'cms@example.test',
      'cms_mail_from_name' => 'WebBlocks CMS',
    ]);

    $response->assertRedirect(route('admin.system.settings.edit'));
    $this->assertNull(SystemSetting::query()->where('key', SystemSettings::CMS_MAIL_PASSWORD)->value('value'));
  }

  #[Test]
  public function mail_diagnostics_never_expose_the_stored_secret(): void
  {
    $user = User::factory()->superAdmin()->create();

    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_MODE], ['value' => SystemSettings::CMS_MAIL_MODE_CUSTOM]);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_MAILER], ['value' => 'smtp']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_HOST], ['value' => 'smtp.example.test']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_PORT], ['value' => '587']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_PASSWORD], ['value' => 'stored-secret']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_FROM_ADDRESS], ['value' => 'cms@example.test']);

    $response = $this->actingAs($user)->get(route('admin.system.settings.edit'));

    $response->assertOk();
    $response->assertSee('Password configured');
    $response->assertSee('yes');
    $response->assertSee('Secret values are never displayed.');
    $response->assertDontSee('stored-secret');
  }

  #[Test]
  public function mail_diagnostics_reports_incomplete_custom_mail_without_exposing_secret(): void
  {
    $user = User::factory()->superAdmin()->create();

    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_MODE], ['value' => SystemSettings::CMS_MAIL_MODE_CUSTOM]);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_MAILER], ['value' => 'smtp']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_HOST], ['value' => 'smtp.example.test']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_PORT], ['value' => '587']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_USERNAME], ['value' => 'mailer@example.test']);
    SystemSetting::query()->updateOrCreate(['key' => SystemSettings::CMS_MAIL_FROM_ADDRESS], ['value' => 'cms@example.test']);

    $response = $this->actingAs($user)->get(route('admin.system.settings.edit'));

    $response->assertOk();
    $response->assertSee('Incomplete or invalid custom settings');
    $response->assertSee('Password configured');
    $response->assertSee('no');
    $response->assertDontSee('stored-secret');
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
      'section' => 'general',
      'project_name' => str_repeat('a', 256),
      'project_tagline' => str_repeat('b', 256),
      'default_locale' => $disabledLocale->code,
      'timezone' => 'Not/A_Timezone',
      'visitor_consent_banner_enabled' => '1',
    ]);

    $response->assertRedirect(route('admin.system.settings.edit'));
    $response->assertSessionHasErrors(['default_locale', 'timezone']);
  }

  #[Test]
  public function settings_require_admin_listing_rows_per_page_to_be_an_integer_in_range(): void
  {
    $user = User::factory()->superAdmin()->create();
    $locale = Locale::query()->where('is_enabled', true)->firstOrFail();

    $response = $this->actingAs($user)->from(route('admin.system.settings.edit'))->put(route('admin.system.settings.update'), [
      'section' => 'general',
      'default_locale' => $locale->code,
      'timezone' => 'UTC',
      'admin_listing_per_page' => '101',
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
  public function admin_topbar_uses_project_identity_while_browser_title_uses_product_suffix(): void
  {
    $user = User::factory()->superAdmin()->create();

    SystemSetting::query()->updateOrCreate(['key' => 'system.project_name'], ['value' => 'WebBlocks UI Docs']);
    SystemSetting::query()->updateOrCreate(['key' => 'system.project_tagline'], ['value' => 'Install-specific admin context']);

    $response = $this->actingAs($user)->get(route('admin.system.settings.edit'));

    $response->assertOk();
    $response->assertSee('<title>System Settings - WebBlocks CMS</title>', false);
    $response->assertSee('<span class="wb-navbar-brand">', false);
    $response->assertSee('WebBlocks UI Docs');
    $response->assertSee('Install-specific admin context');
    $response->assertSee('WebBlocks CMS v'.WebBlocks::VERSION);
    $response->assertDontSee('<title>WebBlocks UI Docs · System Settings · WebBlocks CMS</title>', false);
    $response->assertDontSee('<title>WebBlocks CMS - WebBlocks CMS</title>', false);
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
    $response->assertSee('<title>Dashboard - WebBlocks CMS</title>', false);
  }

  #[Test]
  public function general_card_actions_render_in_the_card_footer(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.settings.edit'));

    $response->assertOk();

    $document = $this->htmlDocument($response->getContent());
    $xpath = new DOMXPath($document);
    $settingsCard = $this->settingsCard($xpath, 'General');

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

  private function settingsCard(DOMXPath $xpath, string $title): DOMElement
  {
    $settingsCard = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " wb-card ")][.//div[contains(concat(" ", normalize-space(@class), " "), " wb-card-header ")]/strong[normalize-space()="'.$title.'"]]')->item(0);

    $this->assertInstanceOf(DOMElement::class, $settingsCard);

    return $settingsCard;
  }

  private function settingsForm(DOMXPath $xpath): DOMElement
  {
    $form = $this->queryElements($xpath, './/form[contains(@action, "/webadmin/system/settings")]', $this->settingsCard($xpath, 'General'))->item(0);

    $this->assertInstanceOf(DOMElement::class, $form);

    return $form;
  }

  private function queryElements(DOMXPath $xpath, string $expression, ?DOMElement $context = null): DOMNodeList
  {
    return $xpath->query($expression, $context);
  }
}
