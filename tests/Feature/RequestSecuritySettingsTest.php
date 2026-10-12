<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Policies\RequestSecuritySettingsPolicy;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\System\RequestSecuritySettings;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Tests\TestCase;

class RequestSecuritySettingsTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('webblocks-cms.routes.public', true);
    $app['config']->set('app.url', 'http://example.test');
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function defineRoutes($router): void
  {
    $router->get('/probe-security-api', fn () => response()->json(['ok' => true]))
      ->middleware(['throttle:internal-content-api', 'internal-api.token']);
  }

  protected function setUp(): void
  {
    parent::setUp();
    if (! class_exists('App\\Models\\User')) {
      class_alias(AuthenticatableUser::class, 'App\\Models\\User');
    }
    $english = Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $german = Locale::firstOrCreate(['code' => 'de'], ['name' => 'German', 'is_enabled' => true]);
    $site = Site::create(['name' => 'Example', 'handle' => 'example', 'domain' => 'example.test', 'is_primary' => true]);
    $site->locales()->syncWithoutDetaching([$english->id => ['is_enabled' => true], $german->id => ['is_enabled' => true]]);
    foreach (['/', '/about'] as $path) {
      $page = Page::create(['site_id' => $site->id, 'status' => Page::STATUS_PUBLISHED]);
      foreach ([$english, $german] as $locale) {
        $page->translations()->create(['locale_id' => $locale->id, 'name' => 'Example', 'slug' => $path === '/' ? 'home' : 'about', 'path' => $path]);
      }
    }
  }

  private function user(string $role = 'super_admin', bool $active = true): AuthenticatableUser
  {
    $userClass = method_exists('App\\Models\\User', 'canAccessAdmin') ? 'App\\Models\\User' : RequestSecuritySettingsUser::class;
    $user = Mockery::mock($userClass)->makePartial();
    $user->forceFill(['id' => 99, 'name' => 'Synthetic Operator', 'role' => $role, 'is_active' => $active]);
    $user->setRelation('sites', Site::all());
    $user->shouldReceive('isSuperAdmin')->andReturn($role === 'super_admin');
    $user->shouldReceive('normalizedRole')->andReturn($role);
    $user->shouldReceive('canAccessAdmin')->andReturn($active);
    $user->shouldReceive('accessibleSiteIds')->andReturn(Site::pluck('id'));

    return $user;
  }

  private function defaults(): array
  {
    return array_map(fn (array $definition) => $definition['default'], RequestSecuritySettings::DEFINITIONS);
  }

  private function saveLimits(array $changes = []): void
  {
    $this->actingAs($this->user())->put('/webadmin/system/settings/security', ['mode' => 'custom'] + array_replace($this->defaults(), $changes))
      ->assertRedirect(route('admin.system.settings.edit', ['tab' => 'security']))->assertSessionHasNoErrors();
  }

  #[Test]
  public function installation_defaults_and_environment_overrides_are_preserved(): void
  {
    $resolver = app(RequestSecuritySettings::class);
    $this->assertSame($this->defaults(), $resolver->values());
    $this->assertFalse($resolver->hasOverrides());
    config(['cms.public_rate_limits.pages_per_minute' => 240, 'cms.internal_api.ip_rate_limit_per_minute' => 450]);
    $this->assertSame(240, $resolver->values()['pages_per_minute']);
    $this->assertSame(450, $resolver->values()['api_ip_per_minute']);
    config(['cms.public_rate_limits.pages_per_minute' => 0, 'cms.internal_api.ip_rate_limit_per_minute' => -1]);
    $this->assertSame(120, $resolver->values()['pages_per_minute']);
    $this->assertSame(300, $resolver->values()['api_ip_per_minute']);
  }

  #[Test]
  public function a_missing_settings_schema_retains_the_original_limits(): void
  {
    Schema::drop('wbcms_system_settings');
    $this->assertSame($this->defaults(), app(RequestSecuritySettings::class)->values());
    $this->assertFalse(app(RequestSecuritySettings::class)->hasOverrides());
  }

  #[Test]
  public function only_active_super_admins_can_save_or_restore_security_limits(): void
  {
    $this->put('/webadmin/system/settings/security', ['mode' => 'defaults'])->assertStatus(302);
    foreach (['site_admin', 'editor'] as $role) {
      $this->actingAs($this->user($role))->putJson('/webadmin/system/settings/security', ['mode' => 'custom'] + $this->defaults())->assertForbidden();
      $this->putJson('/webadmin/system/settings/security', ['mode' => 'defaults'])->assertForbidden();
    }
    $this->actingAs($this->user('super_admin', false))->putJson('/webadmin/system/settings/security', ['mode' => 'defaults'])->assertForbidden();
    $this->assertFalse(app(RequestSecuritySettingsPolicy::class)->update($this->user('super_admin', false)));
    $this->assertSame(0, SystemSetting::whereIn('key', RequestSecuritySettings::keys())->count());
    $this->assertContains('can:access-system', Route::getRoutes()->getByName('admin.system.settings.security.update')->gatherMiddleware());
  }

  #[Test]
  public function the_policy_still_denies_a_site_admin_if_an_extension_changes_the_system_gate(): void
  {
    Gate::define('access-system', fn () => true);
    $this->actingAs($this->user('site_admin'))->putJson('/webadmin/system/settings/security', ['mode' => 'custom'] + $this->defaults())->assertForbidden();
    $this->assertSame(0, SystemSetting::whereIn('key', RequestSecuritySettings::keys())->count());
  }

  #[Test]
  public function the_super_admin_security_tab_renders_all_eight_limits_and_keeps_validation_input(): void
  {
    $this->actingAs($this->user())->get('/webadmin/system/settings?tab=security')->assertOk()
      ->assertSee('id="system-settings-security-panel"', false)->assertSee('Installation defaults are active.')
      ->assertSee('name="pages_per_hour"', false)->assertSee('name="api_ip_per_minute"', false)
      ->assertSee('name="search_max_characters"', false)->assertSee('Use installation defaults')
      ->assertDontSee('<fieldset', false)->assertDontSee('<legend', false)
      ->assertSee('role="group" aria-labelledby="settings-security-pages-title"', false)
      ->assertSee('role="group" aria-labelledby="settings-security-search-title"', false)
      ->assertSee('role="group" aria-labelledby="settings-security-api-title"', false)
      ->assertSee('role="group" aria-labelledby="settings-security-query-title"', false);
    $this->from('/webadmin/system/settings?tab=security')->put('/webadmin/system/settings/security', ['section' => ['project'], 'mode' => 'custom'] + array_replace($this->defaults(), ['pages_per_hour' => 0]))
      ->assertRedirect('/webadmin/system/settings?tab=security')->assertSessionHasErrors('pages_per_hour');
    $this->get('/webadmin/system/settings?tab=security')->assertOk()->assertSee('value="0"', false);
    $this->from('/webadmin/system/settings?tab=security')->put('/webadmin/system/settings/security', ['mode' => 'custom', 'project_name' => ['Injected']] + array_replace($this->defaults(), ['pages_per_hour' => [0]]))
      ->assertRedirect()->assertSessionHasErrors('pages_per_hour');
    $this->get('/webadmin/system/settings?tab=security')->assertOk()->assertSee('value="1800"', false);
    $this->get('/webadmin/system/settings?tab[]=security')->assertOk();
  }

  #[Test]
  public function panel_values_take_precedence_without_changing_host_configuration_or_unrelated_settings(): void
  {
    config(['cms.public_rate_limits.pages_per_minute' => 240]);
    app(SystemSettings::class)->save([SystemSettings::PROJECT_NAME => 'Existing Project']);
    $this->saveLimits(['pages_per_minute' => 75, 'search_max_characters' => 300, 'search_max_words' => 25]);
    $this->assertSame(75, app(RequestSecuritySettings::class)->values()['pages_per_minute']);
    $this->assertSame(300, app(RequestSecuritySettings::class)->values()['search_max_characters']);
    $this->assertSame(240, config('cms.public_rate_limits.pages_per_minute'));
    $this->assertSame('Existing Project', app(SystemSettings::class)->projectName());
    $this->assertSame(8, SystemSetting::whereIn('key', RequestSecuritySettings::keys())->count());
    $this->get('/webadmin/system/settings?tab=security')->assertOk()->assertSee('Saved custom limits are active.');
  }

  #[Test]
  public function invalid_limits_never_partially_replace_a_saved_configuration(): void
  {
    $this->saveLimits(['pages_per_minute' => 75]);
    $before = app(RequestSecuritySettings::class)->values();
    foreach ([['pages_per_hour', 0], ['pages_per_hour', -1], ['pages_per_hour', 1.5], ['pages_per_hour', []], ['pages_per_hour', 1000001], ['search_max_characters', 2001], ['search_max_words', 101]] as [$field, $invalid]) {
      $this->putJson('/webadmin/system/settings/security', ['mode' => 'custom'] + array_replace($this->defaults(), [$field => $invalid]))->assertUnprocessable()->assertJsonValidationErrors($field);
      $this->assertSame($before, app(RequestSecuritySettings::class)->values());
    }
    $payload = $this->defaults();
    unset($payload['search_per_hour']);
    $this->putJson('/webadmin/system/settings/security', ['mode' => 'custom'] + $payload)->assertUnprocessable()->assertJsonValidationErrors('search_per_hour');
    $this->putJson('/webadmin/system/settings/security', ['mode' => ['custom']])->assertUnprocessable()->assertJsonValidationErrors('mode');
    $this->assertSame($before, app(RequestSecuritySettings::class)->values());
  }

  #[Test]
  public function restoring_defaults_removes_only_the_security_overrides(): void
  {
    config(['cms.public_rate_limits.pages_per_minute' => 240]);
    app(SystemSettings::class)->save([SystemSettings::PROJECT_NAME => 'Existing Project']);
    $this->saveLimits(['pages_per_minute' => 75]);
    $this->put('/webadmin/system/settings/security', ['mode' => 'defaults', 'pages_per_minute' => 0, 'project_name' => 'Injected'])->assertRedirect();
    $this->assertFalse(app(RequestSecuritySettings::class)->hasOverrides());
    $this->assertSame(240, app(RequestSecuritySettings::class)->values()['pages_per_minute']);
    $this->assertSame(200, app(RequestSecuritySettings::class)->values()['search_max_characters']);
    $this->assertSame('Existing Project', app(SystemSettings::class)->projectName());
  }

  #[Test]
  public function invalid_persisted_values_fall_back_without_disabling_protection(): void
  {
    SystemSetting::create(['key' => RequestSecuritySettings::key('pages_per_minute'), 'value' => '0']);
    SystemSetting::create(['key' => RequestSecuritySettings::key('search_max_words'), 'value' => 'bad']);
    SystemSetting::create(['key' => RequestSecuritySettings::key('api_ip_per_minute'), 'value' => '1000001']);
    $this->assertSame($this->defaults(), app(RequestSecuritySettings::class)->values());
  }

  #[Test]
  public function raising_a_saved_page_limit_applies_immediately_and_keeps_existing_counters(): void
  {
    $this->saveLimits(['pages_per_minute' => 1]);
    $this->get('http://example.test/')->assertOk();
    $this->get('http://example.test/about')->assertStatus(429);
    $this->saveLimits(['pages_per_minute' => 3]);
    $this->get('http://example.test/about')->assertOk()->assertHeader('X-RateLimit-Remaining', '1');
    $this->get('http://example.test/')->assertOk();
    $this->get('http://example.test/')->assertStatus(429)->assertHeader('Retry-After');
  }

  #[Test]
  public function saved_hourly_page_limits_still_apply_after_the_minute_counter_resets(): void
  {
    $this->saveLimits(['pages_per_hour' => 2]);
    $this->get('http://example.test/')->assertOk();
    $this->get('http://example.test/about')->assertOk();
    $this->get('http://example.test/de/about')->assertStatus(429);
    $this->travel(61)->seconds();
    $this->get('http://example.test/')->assertStatus(429);
    $this->travel(3601)->seconds();
    $this->get('http://example.test/')->assertOk();
  }

  #[Test]
  public function saved_search_budgets_are_shared_by_html_json_and_locales(): void
  {
    $this->saveLimits(['search_per_minute' => 1, 'search_per_hour' => 2]);
    $this->getJson('http://example.test/search.json')->assertOk();
    $this->get('http://example.test/de/search')->assertStatus(429);
    $this->travel(61)->seconds();
    $this->get('http://example.test/search')->assertOk();
    $this->travel(61)->seconds();
    $this->getJson('http://example.test/de/search.json')->assertStatus(429);
    $this->get('http://example.test/')->assertOk();
  }

  #[Test]
  public function saved_api_ip_budget_cannot_be_bypassed_by_changing_tokens_or_prefixes(): void
  {
    $this->saveLimits(['api_per_minute' => 10, 'api_ip_per_minute' => 2]);
    $this->withHeader('Authorization', 'Bearer invalid-one')->getJson('/webadmin/api/openapi.json')->assertUnauthorized();
    $this->withHeader('Authorization', 'Bearer invalid-two')->getJson('/admin-api/sites')->assertUnauthorized();
    $this->withHeader('Authorization', 'Bearer invalid-three')->getJson('/webadmin/api/ai-guide')->assertStatus(429)->assertHeader('Retry-After');
    $metadata = app(InternalApiDiscoveryController::class)->openapi()->getData(true)['x-rate-limit'];
    $this->assertSame(10, $metadata['requests_per_minute']);
    $this->assertSame(2, $metadata['ip_requests_per_minute']);
    $this->assertStringContainsString('limited to 10 requests per minute per token and IP, with an independent backstop of 2 requests per minute per IP', app(InternalApiDiscoveryController::class)->aiGuide()->getData(true)['content']);
  }

  #[Test]
  public function saved_token_budget_preserves_whitespace_normalization_and_other_token_independence(): void
  {
    $this->saveLimits(['api_per_minute' => 1, 'api_ip_per_minute' => 10]);
    $issuer = app(CmsApiTokenIssuer::class);
    $first = $issuer->issue('Synthetic first');
    $second = $issuer->issue('Synthetic second');
    $this->withHeader('Authorization', 'Bearer '.$first->plainToken)->getJson('/probe-security-api')->assertOk();
    $this->withHeader('Authorization', 'Bearer '.$first->plainToken.' ')->getJson('/probe-security-api')->assertStatus(429);
    $this->withHeader('Authorization', 'Bearer '.$second->plainToken)->getJson('/probe-security-api')->assertOk();
  }

  #[Test]
  public function saved_query_limits_control_html_json_and_localized_validation_messages(): void
  {
    $this->saveLimits(['search_max_characters' => 10, 'search_max_words' => 2]);
    foreach (['/search', '/search.json', '/de/search', '/de/search.json'] as $path) {
      $this->get('http://example.test'.$path.'?q='.str_repeat('ü', 10))->assertOk();
      $this->get('http://example.test'.$path.'?q='.str_repeat('ü', 11))->assertStatus(422)->assertJsonValidationErrors('q');
      $this->get('http://example.test'.$path.'?q=a+b+c')->assertStatus(422)->assertJsonValidationErrors('q');
    }
    $this->get('http://example.test/de/search.json?q='.str_repeat('ü', 11))->assertStatus(422)->assertJsonPath('message', 'Die Suchanfrage darf höchstens 10 Zeichen enthalten.');
    $this->saveLimits(['search_max_characters' => 30, 'search_max_words' => 3]);
    $this->getJson('http://example.test/search.json?q=a+b+c')->assertOk();
    $this->saveLimits(['search_max_characters' => 2000, 'search_max_words' => 100]);
    $this->getJson('http://example.test/search.json?q='.str_repeat('ü', 2000))->assertOk();
    $this->getJson('http://example.test/search.json?q='.urlencode(implode(' ', range(1, 100))))->assertOk();
  }

  #[Test]
  public function every_admin_locale_has_security_labels(): void
  {
    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $catalog = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/admin.php';
      foreach (array_keys(RequestSecuritySettings::DEFINITIONS) as $field) {
        $this->assertNotEmpty($catalog['system_settings']['security_'.$field] ?? null, $locale.':'.$field);
      }
      $this->assertNotEmpty($catalog['system_settings']['security_restore_defaults'] ?? null);
    }
  }
}

class RequestSecuritySettingsUser extends AuthenticatableUser
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
