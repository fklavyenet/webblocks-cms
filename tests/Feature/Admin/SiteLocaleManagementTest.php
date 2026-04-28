<?php

namespace Tests\Feature\Admin;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\BlockTextTranslation;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\User;
use App\Support\Locales\LocaleResolver;
use Database\Seeders\BlockTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SiteLocaleManagementTest extends TestCase
{
    use RefreshDatabase;

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

        $response->assertRedirect(route('admin.sites.edit', $site));
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

        $response->assertRedirect(route('admin.sites.edit', $site));
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
