<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\BlockTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Http\Controllers\Admin\LocaleController as PackageLocaleController;
use WebBlocks\Cms\Http\Controllers\Admin\SiteController as PackageSiteController;
use WebBlocks\Cms\Http\Controllers\Admin\SiteDomainController as PackageSiteDomainController;
use WebBlocks\Cms\Http\Controllers\Admin\SiteVariableController as PackageSiteVariableController;
use WebBlocks\Cms\Http\Middleware\UseCmsAuthenticationRedirect;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockTextTranslation;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media as Asset;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteVariable;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;

class SiteLocaleManagementTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function site_and_locale_admin_runtime_routes_use_package_controllers_and_views_without_root_app_wrappers(): void
  {
    $this->assertRouteUsesPackageController('admin.sites.index', PackageSiteController::class);
    $this->assertRouteUsesPackageController('admin.sites.edit', PackageSiteController::class);
    $this->assertRouteUsesPackageController('admin.sites.domains.index', PackageSiteDomainController::class);
    $this->assertRouteUsesPackageController('admin.sites.variables.store', PackageSiteVariableController::class);
    $this->assertRouteUsesPackageController('admin.locales.index', PackageLocaleController::class);

    foreach (['admin.sites.index', 'admin.sites.edit', 'admin.locales.index'] as $routeName) {
      $middleware = app('router')->getRoutes()->getByName($routeName)?->gatherMiddleware() ?? [];

      $this->assertContains('web', $middleware);
      $this->assertContains('install.required', $middleware);
      $this->assertContains(UseCmsAuthenticationRedirect::class, $middleware);
      $this->assertContains('admin.access', $middleware);
    }

    $this->assertTrue(view()->exists('webblocks-cms::admin.sites.index'));
    $this->assertTrue(view()->exists('webblocks-cms::admin.sites.form'));
    $this->assertTrue(view()->exists('webblocks-cms::admin.sites.domains.index'));
    $this->assertTrue(view()->exists('webblocks-cms::admin.locales.index'));
    $this->assertStringContainsString(
      'webblocks-cms::admin.sites.index',
      file_get_contents(resource_path('views/admin/sites/index.blade.php')),
    );
    $this->assertStringContainsString(
      'webblocks-cms::admin.locales.index',
      file_get_contents(resource_path('views/admin/locales/index.blade.php')),
    );

    $this->assertFalse(class_exists('App\\Http\\Controllers\\Admin\\SiteController'));
    $this->assertFalse(class_exists('App\\Http\\Controllers\\Admin\\LocaleController'));
    $this->assertFalse(class_exists('App\\Models\\SiteVariable'));
    $this->assertFalse(class_exists('App\\Models\\SiteLocale'));
    $this->assertFalse(class_exists('App\\Support\\Sites\\SiteDomainManager'));
    $this->assertFalse(class_exists('App\\Support\\Locales\\LocaleLifecycleGuard'));
  }

  #[Test]
  public function sites_index_renders_primary_and_locale_context(): void
  {
    $user = User::factory()->superAdmin()->create();
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
  public function sites_index_page_count_excludes_hidden_shared_slot_source_pages(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->create([
      'name' => 'Docs',
      'handle' => 'docs',
      'domain' => 'docs.example.test',
      'is_primary' => false,
    ]);
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
    $site->locales()->sync([$defaultLocale->id => ['is_enabled' => true]]);

    $ordinaryPages = collect(range(1, 3))->map(function (int $index) use ($site): Page {
      return Page::query()->create([
        'site_id' => $site->id,
        'title' => 'Page '.$index,
        'slug' => 'page-'.$index,
        'status' => Page::STATUS_PUBLISHED,
      ]);
    });

    $sharedSlots = collect(['docs-header', 'docs-sidebar'])->map(function (string $handle) use ($site): SharedSlot {
      return SharedSlot::query()->create([
        'site_id' => $site->id,
        'name' => str($handle)->headline()->toString(),
        'handle' => $handle,
        'slot_name' => str($handle)->contains('sidebar') ? 'sidebar' : 'header',
        'public_shell' => 'docs',
        'is_active' => true,
      ]);
    });

    $sourcePages = $sharedSlots->map(fn (SharedSlot $sharedSlot) => app(SharedSlotSourcePageManager::class)->ensureFor($sharedSlot));
    $expectedVisiblePageCount = $ordinaryPages->count();

    $response = $this->actingAs($user)->get(route('admin.sites.index'));

    $response->assertOk();
    $response->assertSee('<tr data-site-id="'.$site->id.'">', false);
    $response->assertSee('<td data-column="pages">'.$expectedVisiblePageCount.'</td>', false);

    $this->assertSame($expectedVisiblePageCount, Page::query()->where('site_id', $site->id)->visibleInAdmin()->count());
    $this->assertCount(2, $sourcePages);
    $this->assertSame(2, Page::query()->where('site_id', $site->id)->where('page_type', Page::TYPE_SHARED_SLOT_SOURCE)->count());
    $this->assertSame($expectedVisiblePageCount + $sourcePages->count(), Page::query()->where('site_id', $site->id)->count());

    $pagesIndexResponse = $this->actingAs($user)->get(route('admin.pages.index', ['site' => $site->id]));

    $pagesIndexResponse->assertOk();

    foreach ($sourcePages as $sourcePage) {
      $pagesIndexResponse->assertDontSee($sourcePage->slug);
    }
  }

  #[Test]
  public function sites_index_still_loads_when_block_gallery_item_translations_table_is_missing(): void
  {
    $user = User::factory()->superAdmin()->create();

    Schema::dropIfExists('block_gallery_item_translations');

    $response = $this->actingAs($user)->get(route('admin.sites.index'));

    $response->assertOk();
    $response->assertSee('Sites');
  }

  #[Test]
  public function locales_index_renders_default_and_enabled_context(): void
  {
    $user = User::factory()->superAdmin()->create();
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
  public function locales_index_shows_lifecycle_actions_and_explanations(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();

    $inUseLocale = Locale::query()->create([
      'code' => 'tr',
      'name' => 'Turkish',
      'is_default' => false,
      'is_enabled' => true,
    ]);
    $site->locales()->syncWithoutDetaching([$inUseLocale->id => ['is_enabled' => true]]);

    $disabledLocale = Locale::query()->create([
      'code' => 'de',
      'name' => 'German',
      'is_default' => false,
      'is_enabled' => false,
    ]);

    $response = $this->actingAs($user)->get(route('admin.locales.index'));

    $response->assertOk();
    $response->assertSee('Default locale cannot be disabled or deleted.');
    $response->assertSee('Cannot delete because this locale is in use.');
    $response->assertSee('Disabled locale keeps translation data until deleted.');
    $response->assertSee(route('admin.locales.disable', $inUseLocale), false);
    $response->assertSee(route('admin.locales.enable', $disabledLocale), false);
    $response->assertSee(route('admin.locales.destroy', $disabledLocale), false);
  }

  #[Test]
  public function site_domains_are_normalized_and_default_locale_is_preserved_on_save(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
    $favicon = $this->imageAsset('sites/favicon.png', 'favicon.png');
    $socialImage = $this->imageAsset('sites/social.png', 'social.png');

    $response = $this->actingAs($user)->put(route('admin.sites.update', $site), [
      'name' => $site->name,
      'handle' => 'Default Site',
      'domain' => 'https://PRIMARY.EXAMPLE.TEST/some/path',
      'is_primary' => 1,
      'display_name' => 'Primary Public Site',
      'tagline' => 'Public facing tagline',
      'favicon_asset_id' => $favicon->id,
      'contact_recipient_email' => 'forms@example.com',
      'seo_title' => 'Primary SEO Title',
      'seo_description' => 'Primary SEO Description',
      'seo_keywords' => 'alpha,beta',
      'social_image_asset_id' => $socialImage->id,
      'locale_ids' => [$defaultLocale->id],
    ]);

    $response->assertRedirect(route('admin.sites.edit', ['site' => $site, 'tab' => 'site']));
    $this->assertSame('default-site', $site->fresh()->handle);
    $this->assertSame('primary.example.test', $site->fresh()->domain);
    $this->assertSame('Primary Public Site', $site->fresh()->display_name);
    $this->assertSame('Public facing tagline', $site->fresh()->tagline);
    $this->assertSame($favicon->id, $site->fresh()->favicon_asset_id);
    $this->assertSame('forms@example.com', $site->fresh()->contact_recipient_email);
    $this->assertSame('Primary SEO Title', $site->fresh()->seo_title);
    $this->assertSame('Primary SEO Description', $site->fresh()->seo_description);
    $this->assertSame('alpha,beta', $site->fresh()->seo_keywords);
    $this->assertSame($socialImage->id, $site->fresh()->social_image_asset_id);
    $this->assertTrue($site->fresh()->hasEnabledLocale($defaultLocale));
  }

  #[Test]
  public function creating_site_without_handle_generates_canonical_hyphenated_handle_from_name(): void
  {
    $user = User::factory()->superAdmin()->create();
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();

    $response = $this->actingAs($user)->post(route('admin.sites.store'), [
      'name' => 'ui.webblocksui.com',
      'handle' => '',
      'domain' => '',
      'is_primary' => 0,
      'locale_ids' => [$defaultLocale->id],
    ]);

    $site = Site::query()->where('name', 'ui.webblocksui.com')->firstOrFail();

    $response->assertRedirect(route('admin.sites.edit', $site));
    $this->assertSame('ui-webblocksui-com', $site->handle);
  }

  #[Test]
  public function manually_supplied_valid_handle_is_preserved_on_create(): void
  {
    $user = User::factory()->superAdmin()->create();
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();

    $this->actingAs($user)->post(route('admin.sites.store'), [
      'name' => 'WebBlocks UI',
      'handle' => 'docs-site',
      'domain' => '',
      'is_primary' => 0,
      'locale_ids' => [$defaultLocale->id],
    ])->assertRedirect();

    $this->assertDatabaseHas('sites', [
      'name' => 'WebBlocks UI',
      'handle' => 'docs-site',
    ]);
  }

  #[Test]
  public function editing_name_does_not_mutate_existing_handle_when_handle_is_unchanged(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();

    $this->actingAs($user)->put(route('admin.sites.update', $site), [
      'name' => 'Docs Site',
      'handle' => $site->handle,
      'domain' => $site->domain,
      'is_primary' => 1,
      'locale_ids' => [$defaultLocale->id],
    ])->assertRedirect(route('admin.sites.edit', ['site' => $site, 'tab' => 'site']));

    $this->assertSame('default', $site->fresh()->handle);
  }

  #[Test]
  public function canonical_handle_normalization_collapses_separator_runs_to_single_hyphens(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();

    $this->actingAs($user)->put(route('admin.sites.update', $site), [
      'name' => $site->name,
      'handle' => '___Docs...///Site___',
      'domain' => $site->domain,
      'is_primary' => 1,
      'locale_ids' => [$defaultLocale->id],
    ])->assertRedirect(route('admin.sites.edit', ['site' => $site, 'tab' => 'site']));

    $this->assertSame('docs-site', $site->fresh()->handle);
  }

  #[Test]
  public function site_create_form_exposes_handle_autosuggest_only_for_new_sites(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();

    $createResponse = $this->actingAs($user)->get(route('admin.sites.create'));
    $editResponse = $this->actingAs($user)->get(route('admin.sites.edit', $site));

    $createResponse->assertOk();
    $createResponse->assertSee('data-site-name-input', false);
    $createResponse->assertSee('data-site-handle-input', false);
    $createResponse->assertSee('data-site-handle-autosuggest="on"', false);

    $editResponse->assertOk();
    $editResponse->assertSee('data-site-handle-autosuggest="off"', false);
  }

  #[Test]
  public function site_can_be_saved_without_explicit_locale_ids_and_preserves_default_locale(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();

    $response = $this->actingAs($user)->put(route('admin.sites.update', $site), [
      'name' => $site->name,
      'handle' => $site->handle,
      'domain' => 'imported.example.test',
      'is_primary' => 1,
    ]);

    $response->assertRedirect(route('admin.sites.edit', ['site' => $site, 'tab' => 'site']));
    $this->assertSame('imported.example.test', $site->fresh()->domain);
    $this->assertTrue($site->fresh()->hasEnabledLocale($defaultLocale));
  }

  #[Test]
  public function site_update_with_additional_locale_keeps_default_locale_attached(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
    $turkish = Locale::query()->create([
      'code' => 'tr',
      'name' => 'Turkish',
      'is_default' => false,
      'is_enabled' => true,
    ]);

    $response = $this->actingAs($user)->put(route('admin.sites.update', $site), [
      'name' => $site->name,
      'handle' => $site->handle,
      'domain' => $site->domain,
      'is_primary' => 1,
      'locale_ids' => [$turkish->id],
    ]);

    $response->assertRedirect(route('admin.sites.edit', ['site' => $site, 'tab' => 'site']));
    $this->assertTrue($site->fresh()->hasEnabledLocale($defaultLocale));
    $this->assertTrue($site->fresh()->hasEnabledLocale($turkish));
  }

  #[Test]
  public function site_edit_form_renders_forced_default_locale_hidden_input(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.sites.edit', $site));

    $response->assertOk();
    $response->assertSee('name="locale_ids[]" value="'.$defaultLocale->id.'"', false);
    $response->assertSee('disabled', false);
    $response->assertSee('Branding');
    $response->assertSee('SEO Defaults');
    $response->assertSee('Contact');
    $response->assertSee('Variables');
    $response->assertSee('Theme');
    $response->assertDontSee('<strong>Domains</strong>', false);
    $response->assertSee('Public display name');
    $response->assertSee('Default meta title');
    $response->assertSee('Default recipient email');
  }

  #[Test]
  public function site_edit_theme_tab_renders_public_theme_controls_after_variables(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.sites.edit', ['site' => $site, 'tab' => 'theme']));

    $response->assertOk();
    $response->assertSeeInOrder(['Variables', 'Theme'], false);
    $response->assertSee('<strong>Public Theme</strong>', false);
    $response->assertSee('name="public_theme_preset"', false);
    foreach (Site::PUBLIC_THEME_PRESETS as $preset) {
      $response->assertSee('value="'.$preset.'"', false);
    }
    $response->assertSeeInOrder(['Canvas', 'Atlas', 'Pulse', 'Prism', 'Graphite', 'Horizon']);
    $response->assertSee('data-wb-public-theme-preview="canvas"', false);
    $response->assertSee('Public visual tones are design roles', false);
    $response->assertSee('data-wb-public-theme="canvas"', false);
  }

  #[Test]
  public function site_theme_preset_can_be_saved_and_unknown_presets_are_rejected(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();

    $this->actingAs($user)->put(route('admin.sites.update', $site), [
      'name' => $site->name,
      'handle' => $site->handle,
      'domain' => $site->domain,
      'is_primary' => 1,
      'public_theme_preset' => 'prism',
      'locale_ids' => [$defaultLocale->id],
      '_site_tab' => 'theme',
    ])->assertRedirect(route('admin.sites.edit', ['site' => $site, 'tab' => 'theme']));

    $this->assertSame('prism', $site->fresh()->public_theme_preset);

    $response = $this->actingAs($user)->get(route('admin.sites.edit', ['site' => $site, 'tab' => 'theme']));

    $response->assertOk();
    $response->assertSee('value="prism" selected', false);
    $response->assertSee('data-wb-public-theme-preview="prism"', false);
    $response->assertSee('data-wb-public-theme="prism"', false);

    $this->actingAs($user)->put(route('admin.sites.update', $site), [
      'name' => $site->name,
      'handle' => $site->handle,
      'domain' => $site->domain,
      'is_primary' => 1,
      'public_theme_preset' => 'Prism',
      'locale_ids' => [$defaultLocale->id],
      '_site_tab' => 'theme',
    ])->assertRedirect(route('admin.sites.edit', ['site' => $site, 'tab' => 'theme']));

    $this->assertSame('prism', $site->fresh()->public_theme_preset);

    $this->actingAs($user)->from(route('admin.sites.edit', ['site' => $site, 'tab' => 'theme']))
      ->put(route('admin.sites.update', $site), [
        'name' => $site->name,
        'handle' => $site->handle,
        'domain' => $site->domain,
        'is_primary' => 1,
        'public_theme_preset' => 'unknown',
        'locale_ids' => [$defaultLocale->id],
        '_site_tab' => 'theme',
      ])
      ->assertRedirect(route('admin.sites.edit', ['site' => $site, 'tab' => 'theme']))
      ->assertSessionHasErrors('public_theme_preset');

    $this->assertSame('prism', $site->fresh()->public_theme_preset);
  }

  #[Test]
  public function site_scoped_admin_can_save_theme_only_for_assigned_site(): void
  {
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
    $user = User::factory()->siteAdmin()->create();
    $user->sites()->sync([$site->id]);

    $this->actingAs($user)->put(route('admin.sites.update', $site), [
      'name' => $site->name,
      'handle' => $site->handle,
      'domain' => $site->domain,
      'is_primary' => 1,
      'public_theme_preset' => 'horizon',
      'locale_ids' => [$defaultLocale->id],
      '_site_tab' => 'theme',
    ])->assertRedirect(route('admin.sites.edit', ['site' => $site, 'tab' => 'theme']));

    $this->assertSame('horizon', $site->fresh()->public_theme_preset);

    $otherSite = Site::query()->create([
      'name' => 'Blocked Theme',
      'handle' => 'blocked-theme',
      'domain' => null,
      'is_primary' => false,
    ]);
    $otherSite->locales()->syncWithoutDetaching([$defaultLocale->id => ['is_enabled' => true]]);

    $this->actingAs($user)->put(route('admin.sites.update', $otherSite), [
      'name' => $otherSite->name,
      'handle' => $otherSite->handle,
      'domain' => $otherSite->domain,
      'is_primary' => 0,
      'public_theme_preset' => 'graphite',
      'locale_ids' => [$defaultLocale->id],
      '_site_tab' => 'theme',
    ])->assertForbidden();

    $this->assertNull($otherSite->fresh()->public_theme_preset);
  }

  #[Test]
  public function site_edit_form_renders_actions_in_site_settings_card_footer_in_standard_order(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.sites.edit', $site));

    $response->assertOk();
    $response->assertSeeInOrder([
      '<div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">',
      '<strong>Site Settings</strong>',
      '<div class="wb-card-footer">',
      'data-admin-form-actions',
      'data-admin-form-actions-main',
      'Save Changes',
      'Cancel',
      'data-admin-form-actions-danger',
      'Delete',
    ], false);
    $response->assertDontSee('Save Site', false);
  }

  #[Test]
  public function site_edit_form_cancel_action_targets_sites_index_and_not_pages_index(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.sites.edit', $site));

    $response->assertOk();
    $response->assertSee('href="'.route('admin.sites.index').'" class="wb-btn wb-btn-secondary">Cancel</a>', false);
    $response->assertDontSee('href="'.route('admin.pages.index', ['site' => $site->id]).'" class="wb-btn wb-btn-secondary">Cancel</a>', false);
  }

  #[Test]
  public function site_admin_can_manage_site_variables_for_assigned_site(): void
  {
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $user = User::factory()->siteAdmin()->create();
    $user->sites()->sync([$site->id]);

    $createResponse = $this->actingAs($user)->post(route('admin.sites.variables.store', $site), [
      'key' => 'Support Email',
      'label' => 'Support Email',
      'value' => 'help@example.test',
      'sort_order' => 2,
      'is_enabled' => 1,
      '_site_tab' => 'variables',
    ]);

    $createResponse->assertRedirect(route('admin.sites.edit', ['site' => $site, 'tab' => 'variables']));
    $siteVariable = SiteVariable::query()->where('site_id', $site->id)->where('key', 'support_email')->firstOrFail();

    $this->actingAs($user)->put(route('admin.sites.variables.update', ['site' => $site, 'site_variable' => $siteVariable]), [
      'key' => 'support_email',
      'label' => 'Support Inbox',
      'value' => 'support@example.test',
      'sort_order' => 3,
      'is_enabled' => 0,
      '_site_tab' => 'variables',
    ])->assertRedirect(route('admin.sites.edit', ['site' => $site, 'tab' => 'variables']));

    $this->assertDatabaseHas('site_variables', [
      'id' => $siteVariable->id,
      'label' => 'Support Inbox',
      'value' => 'support@example.test',
      'sort_order' => 3,
      'is_enabled' => false,
    ]);

    $this->actingAs($user)
      ->delete(route('admin.sites.variables.destroy', ['site' => $site, 'site_variable' => $siteVariable]))
      ->assertRedirect(route('admin.sites.edit', ['site' => $site, 'tab' => 'variables']));

    $this->assertDatabaseMissing('site_variables', ['id' => $siteVariable->id]);
  }

  #[Test]
  public function site_admin_can_manage_contact_recipient_for_assigned_site(): void
  {
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
    $user = User::factory()->siteAdmin()->create();
    $user->sites()->sync([$site->id]);

    $response = $this->actingAs($user)->put(route('admin.sites.update', $site), [
      'name' => $site->name,
      'handle' => $site->handle,
      'domain' => $site->domain,
      'is_primary' => 1,
      'contact_recipient_email' => 'forms@example.com',
      'locale_ids' => [$defaultLocale->id],
      '_site_tab' => 'contact',
    ]);

    $response->assertRedirect(route('admin.sites.edit', ['site' => $site, 'tab' => 'contact']));
    $this->assertSame('forms@example.com', $site->fresh()->contact_recipient_email);

    $otherSite = Site::query()->create([
      'name' => 'Blocked',
      'handle' => 'blocked',
      'domain' => null,
      'is_primary' => false,
    ]);
    $otherSite->locales()->syncWithoutDetaching([$defaultLocale->id => ['is_enabled' => true]]);

    $this->actingAs($user)->put(route('admin.sites.update', $otherSite), [
      'name' => $otherSite->name,
      'handle' => $otherSite->handle,
      'domain' => $otherSite->domain,
      'is_primary' => 0,
      'contact_recipient_email' => 'blocked@example.com',
      'locale_ids' => [$defaultLocale->id],
      '_site_tab' => 'contact',
    ])->assertForbidden();

    $this->assertNull($otherSite->fresh()->contact_recipient_email);
  }

  #[Test]
  public function editor_can_view_site_variables_but_cannot_mutate_site_settings(): void
  {
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $site->siteVariables()->create([
      'key' => 'support_email',
      'label' => 'Support Email',
      'value' => 'help@example.test',
      'sort_order' => 0,
      'is_enabled' => true,
    ]);
    $editor = User::factory()->editor()->create();
    $editor->sites()->sync([$site->id]);

    $viewResponse = $this->actingAs($editor)->get(route('admin.sites.edit', ['site' => $site, 'tab' => 'variables']));

    $viewResponse->assertOk();
    $viewResponse->assertSee('Read only');
    $viewResponse->assertSee('{{ site.support_email }}');
    $viewResponse->assertDontSee('Add Variable');
    $viewResponse->assertDontSee('Manage Domains');

    $this->actingAs($editor)
      ->put(route('admin.sites.update', $site), [
        'name' => 'Blocked update',
        'handle' => $site->handle,
        'domain' => $site->domain,
        'is_primary' => 1,
      ])
      ->assertForbidden();

    $this->actingAs($editor)
      ->post(route('admin.sites.variables.store', $site), [
        'key' => 'blocked_key',
        'value' => 'blocked',
      ])
      ->assertForbidden();
  }

  private function imageAsset(string $path, string $filename): Asset
  {
    return Asset::query()->create([
      'disk' => 'public',
      'path' => $path,
      'filename' => $filename,
      'original_name' => $filename,
      'extension' => 'png',
      'mime_type' => 'image/png',
      'size' => 1024,
      'kind' => Asset::KIND_IMAGE,
      'visibility' => 'public',
      'width' => 64,
      'height' => 64,
    ]);
  }

  private function assertRouteUsesPackageController(string $routeName, string $controllerClass): void
  {
    $route = app('router')->getRoutes()->getByName($routeName);

    $this->assertNotNull($route, 'Route '.$routeName.' should be registered.');
    $this->assertStringStartsWith($controllerClass.'@', (string) $route->getAction('controller'));
  }

  #[Test]
  public function site_domain_must_be_unique_after_normalization(): void
  {
    $user = User::factory()->superAdmin()->create();
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

  #[Test]
  public function default_locale_cannot_be_disabled(): void
  {
    $user = User::factory()->superAdmin()->create();
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();

    $response = $this->actingAs($user)->post(route('admin.locales.disable', $defaultLocale));

    $response->assertRedirect(route('admin.locales.index'));
    $response->assertSessionHasErrors('locale_lifecycle');
    $this->assertTrue($defaultLocale->fresh()->is_enabled);
  }

  #[Test]
  public function non_default_locale_can_be_disabled_and_enabled_again(): void
  {
    $user = User::factory()->superAdmin()->create();
    $locale = Locale::query()->create([
      'code' => 'fr',
      'name' => 'French',
      'is_default' => false,
      'is_enabled' => true,
    ]);

    $disable = $this->actingAs($user)->post(route('admin.locales.disable', $locale));
    $disable->assertRedirect(route('admin.locales.index'));
    $this->assertFalse($locale->fresh()->is_enabled);

    $enable = $this->actingAs($user)->post(route('admin.locales.enable', $locale));
    $enable->assertRedirect(route('admin.locales.index'));
    $this->assertTrue($locale->fresh()->is_enabled);
  }

  #[Test]
  public function default_locale_cannot_be_deleted(): void
  {
    $user = User::factory()->superAdmin()->create();
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();

    $response = $this->actingAs($user)->delete(route('admin.locales.destroy', $defaultLocale));

    $response->assertRedirect(route('admin.locales.index'));
    $response->assertSessionHasErrors('locale_lifecycle');
    $this->assertDatabaseHas('locales', ['id' => $defaultLocale->id]);
  }

  #[Test]
  public function locale_assigned_to_a_site_cannot_be_deleted(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $locale = Locale::query()->create([
      'code' => 'tr',
      'name' => 'Turkish',
      'is_default' => false,
      'is_enabled' => false,
    ]);
    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);

    $response = $this->actingAs($user)->delete(route('admin.locales.destroy', $locale));

    $response->assertRedirect(route('admin.locales.index'));
    $response->assertSessionHasErrors('locale_lifecycle');
    $this->assertDatabaseHas('locales', ['id' => $locale->id]);
  }

  #[Test]
  public function locale_with_page_translations_cannot_be_deleted(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $locale = Locale::query()->create([
      'code' => 'it',
      'name' => 'Italian',
      'is_default' => false,
      'is_enabled' => false,
    ]);

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'About',
      'slug' => 'about',
      'status' => 'published',
    ]);

    DB::table('page_translations')->insert([
      'page_id' => $page->id,
      'site_id' => $site->id,
      'locale_id' => $locale->id,
      'name' => 'Chi Siamo',
      'slug' => 'chi-siamo',
      'path' => '/chi-siamo',
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->delete(route('admin.locales.destroy', $locale));

    $response->assertRedirect(route('admin.locales.index'));
    $response->assertSessionHasErrors('locale_lifecycle');
    $this->assertDatabaseHas('locales', ['id' => $locale->id]);
  }

  #[Test]
  public function locale_with_block_translation_rows_cannot_be_deleted(): void
  {
    $this->seed(BlockTypeSeeder::class);

    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
    $locale = Locale::query()->create([
      'code' => 'es',
      'name' => 'Spanish',
      'is_default' => false,
      'is_enabled' => false,
    ]);

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'About',
      'slug' => 'about',
      'status' => 'published',
    ]);

    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'header',
      'block_type_id' => BlockType::query()->where('slug', 'header')->value('id'),
      'slot' => 'main',
      'sort_order' => 0,
      'status' => 'published',
      'title' => 'About',
      'variant' => 'h2',
    ]);

    BlockTextTranslation::query()->create([
      'block_id' => $block->id,
      'locale_id' => $defaultLocale->id,
      'title' => 'About',
    ]);

    DB::table('block_text_translations')->insert([
      'block_id' => $block->id,
      'locale_id' => $locale->id,
      'title' => 'Acerca de',
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->delete(route('admin.locales.destroy', $locale));

    $response->assertRedirect(route('admin.locales.index'));
    $response->assertSessionHasErrors('locale_lifecycle');
    $this->assertDatabaseHas('locales', ['id' => $locale->id]);
  }

  #[Test]
  public function fully_unused_disabled_non_default_locale_can_be_deleted(): void
  {
    $user = User::factory()->superAdmin()->create();
    $locale = Locale::query()->create([
      'code' => 'nl',
      'name' => 'Dutch',
      'is_default' => false,
      'is_enabled' => false,
    ]);

    $response = $this->actingAs($user)->delete(route('admin.locales.destroy', $locale));

    $response->assertRedirect(route('admin.locales.index'));
    $this->assertDatabaseMissing('locales', ['id' => $locale->id]);
  }

  #[Test]
  public function disabled_locale_is_not_treated_as_enabled_in_locale_resolution(): void
  {
    $resolver = app(LocaleResolver::class);
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();

    $locale = Locale::query()->create([
      'code' => 'sv',
      'name' => 'Swedish',
      'is_default' => false,
      'is_enabled' => true,
    ]);

    $this->assertSame($locale->id, $resolver->enabled('sv')?->id);

    $locale->forceFill(['is_enabled' => false])->save();

    $this->assertNull($resolver->enabled('sv'));
    $this->assertSame($defaultLocale->id, $resolver->current(request()->create('/sv'))->id);
  }
}
