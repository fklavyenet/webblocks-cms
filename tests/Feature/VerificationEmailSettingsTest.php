<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Notifications\System\TwoFactorEmail;
use WebBlocks\Cms\Queries\PanelNotificationQuery;
use WebBlocks\Cms\Support\Mail\VerificationEmailTemplates;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Tests\TestCase;

class VerificationEmailSettingsTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('mail.default', 'smtp');
    $app['config']->set('mail.mailers.smtp.transport', 'smtp');
    $app['config']->set('app.name', 'Northstar Studio');
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $this->mock(PanelNotificationQuery::class)->shouldReceive('forUser')->andReturn(null);
  }

  private function actor(string $role = 'super_admin', bool $active = true): User
  {
    return $this->cmsActor((new VerificationEmailUser)->forceFill([
      'id' => 1, 'name' => 'Synthetic operator', 'email' => 'operator@example.test',
      'is_active' => $active, 'role' => $role,
    ]), []);
  }

  private function payload(string $locale = 'en'): array
  {
    return ['locale' => $locale, 'intent' => 'save'] + app(VerificationEmailTemplates::class)->defaults($locale);
  }

  public function test_only_active_super_admins_with_system_access_can_save_preview_or_reset(): void
  {
    foreach (['save', 'preview', 'reset'] as $intent) {
      foreach (['site_admin', 'editor'] as $role) {
        $this->actingAs($this->actor($role))->postJson(route('admin.system.settings.mail.verification'),
          array_replace($this->payload(), ['intent' => $intent]))->assertForbidden();
      }
      $this->actingAs($this->actor('super_admin', false))->postJson(route('admin.system.settings.mail.verification'),
        array_replace($this->payload(), ['intent' => $intent]))->assertForbidden();
    }
    $this->actingAs($this->actor());
    Gate::define('access-system', fn () => false);
    $this->postJson(route('admin.system.settings.mail.verification'), $this->payload())->assertForbidden();
    $this->assertSame(0, SystemSetting::query()->where('key', 'like', 'system.verification_email.%')->count());
  }

  public function test_localized_settings_are_scalar_rows_and_reset_changes_only_the_selected_language(): void
  {
    $this->actingAs($this->actor());
    SystemSetting::create(['key' => SystemSettings::CMS_MAIL_HOST, 'value' => 'smtp.example.test']);
    foreach (['en', 'tr'] as $locale) {
      $values = array_replace($this->payload($locale), ['subject' => '{code} / '.$locale.' / {app_name}', 'help' => 'Expires in {minutes} minutes.']);
      $this->post(route('admin.system.settings.mail.verification'), $values + ['cms_mail_host' => 'ignored.test'])
        ->assertRedirect(route('admin.system.settings.edit', ['tab' => 'mail', 'verification_locale' => $locale]))->assertSessionHasNoErrors();
      $this->assertDatabaseHas('wbcms_system_settings', ['key' => VerificationEmailTemplates::key($locale, 'subject'), 'value' => $values['subject']]);
    }
    $this->assertSame(10, SystemSetting::query()->where('key', 'like', 'system.verification_email.%')->count());
    $this->assertDatabaseHas('wbcms_system_settings', ['key' => SystemSettings::CMS_MAIL_HOST, 'value' => 'smtp.example.test']);
    app()->setLocale('tr');
    $message = (new TwoFactorEmail('012345'))->toMail(new AnonymousNotifiable);
    $this->assertSame('012345 / tr / Northstar Studio', $message->subject);
    $this->assertSame('smtp', $message->mailer);
    $this->assertStringContainsString('Expires in 5 minutes.', (string) $message->render());
    $this->post(route('admin.system.settings.mail.verification'), ['locale' => 'tr', 'intent' => 'reset'])
      ->assertRedirect(route('admin.system.settings.edit', ['tab' => 'mail', 'verification_locale' => 'tr']));
    $this->assertSame(5, SystemSetting::query()->where('key', 'like', 'system.verification_email.%')->count());
    $this->assertSame(app(VerificationEmailTemplates::class)->defaults('tr'), app(VerificationEmailTemplates::class)->templates('tr'));
    $this->assertSame('{code} / en / {app_name}', app(VerificationEmailTemplates::class)->templates('en')['subject']);
  }

  public function test_defaults_render_code_first_in_subject_and_large_bold_in_html_with_plain_text_alternative(): void
  {
    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      app()->setLocale($locale);
      $message = (new TwoFactorEmail('012345'))->toMail(new AnonymousNotifiable);
      $this->assertStringStartsWith('012345 — ', $message->subject);
      $this->assertStringNotContainsString('{', $message->subject);
      $html = (string) $message->render();
      $dom = new \DOMDocument;
      @$dom->loadHTML($html);
      $xpath = new \DOMXPath($dom);
      $code = $xpath->query('//p[contains(@class,"wb-email-verification-code")]')->item(0);
      $this->assertNotNull($code);
      $this->assertSame('012345', trim($code->textContent));
      $this->assertStringContainsString('font-size: 32px', $code->getAttribute('style'));
      $this->assertStringContainsString('font-weight: 700', $code->getAttribute('style'));
      $this->assertSame(1, $xpath->query('//p[contains(@class,"wb-email-verification-code")]/strong')->length);
      $text = (string) app(Markdown::class)->renderText($message->markdown, $message->data());
      $this->assertStringContainsString('012345', $text);
      $this->assertStringNotContainsString('<p', $text);
      $this->assertStringNotContainsString('{minutes}', $text);
    }
    app()->setLocale('unsupported');
    $this->assertSame('012345 — Your CMS verification code', (new TwoFactorEmail('012345'))->toMail(new AnonymousNotifiable)->subject);
  }

  public function test_preview_escapes_authored_markup_uses_a_sample_and_never_saves_or_sends(): void
  {
    Notification::fake();
    $this->actingAs($this->actor());
    $payload = array_replace($this->payload(), ['intent' => 'preview', 'greeting' => '<script>alert(1)</script>',
      'intro' => '[Open](https://example.test) **text** <a href="https://example.test">link</a>'."\nSecond line", 'help' => '{code} / {minutes} / {app_name}']);
    $response = $this->post(route('admin.system.settings.mail.verification'), $payload)->assertOk()
      ->assertHeader('Cache-Control', 'no-store, private')->assertSee('123456')->assertSee('Northstar Studio')
      ->assertDontSee('<script>', false)->assertDontSee('<a href="https://example.test">', false);
    $this->assertStringContainsString('[Open](https://example.test) **text**', $response->getContent());
    $this->assertSame(0, SystemSetting::query()->where('key', 'like', 'system.verification_email.%')->count());
    Notification::assertNothingSent();
  }

  public function test_validation_rejects_unknown_tokens_header_injection_unsupported_languages_and_oversize_text(): void
  {
    $this->actingAs($this->actor());
    foreach ([['locale', 'xx'], ['locale', ['en']], ['intent', 'invalid'], ['subject', "Subject\r\nBcc: injected@example.test"],
      ['subject', '{password}'], ['subject', str_repeat('x', 256)], ['intro', str_repeat('x', 2001)], ['help', '{code'],
      ['greeting', "hello\x00there"], ['salutation', "two\nlines"], ['intro', []], ['help', '']] as [$field, $value]) {
      $this->postJson(route('admin.system.settings.mail.verification'), array_replace($this->payload(), [$field => $value]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    $this->post(route('admin.system.settings.mail.verification'), array_replace($this->payload('tr'), ['subject' => '{unknown}']))
      ->assertRedirect(route('admin.system.settings.edit', ['tab' => 'mail', 'verification_locale' => 'tr']))
      ->assertSessionHasInput('subject', '{unknown}');
    $this->assertSame(0, SystemSetting::query()->where('key', 'like', 'system.verification_email.%')->count());
  }

  public function test_settings_render_the_editor_in_the_mail_panel_and_preserve_language_after_validation(): void
  {
    $this->actingAs($this->actor());
    $this->get(route('admin.system.settings.edit', ['tab' => 'mail', 'verification_locale' => 'tr']))->assertOk()
      ->assertSee('id="verification-email-title"', false)->assertSee('value="{code} — CMS doğrulama kodunuz"', false)
      ->assertSee('id="verification-email-reset" role="dialog"', false)->assertDontSee('<fieldset', false);
    $this->post(route('admin.system.settings.mail.verification'), array_replace($this->payload('tr'), ['subject' => '{unknown}']))->assertSessionHasErrors('subject');
    $this->get(route('admin.system.settings.edit', ['tab' => 'mail', 'verification_locale' => 'tr']))->assertOk()
      ->assertSee('value="{unknown}"', false)->assertSee('value="tr" selected', false);
    $this->get(route('admin.system.settings.edit', ['tab' => 'mail', 'verification_locale' => ['tr']]))->assertOk();
  }
}

class VerificationEmailUser extends User
{
  public function isSuperAdmin(): bool
  {
    return $this->role === 'super_admin';
  }

  public function normalizedRole(): string
  {
    return $this->role;
  }

  public function canAccessAdmin(): bool
  {
    return (bool) $this->is_active;
  }

  public function accessibleSiteIds(): Collection
  {
    return Site::pluck('id');
  }
}
