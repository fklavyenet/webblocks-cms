<?php

namespace Tests\Feature\Admin;

use App\Models\Locale;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SiteCloneAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function clone_form_is_available_from_sites_area_and_can_run_a_dry_run(): void
    {
        $user = User::factory()->superAdmin()->create();
        $source = Site::query()->create([
            'name' => 'Source',
            'handle' => 'source',
            'domain' => 'source.example.test',
            'is_primary' => false,
        ]);

        $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
        $source->locales()->sync([$defaultLocale->id => ['is_enabled' => true]]);

        Page::query()->create([
            'site_id' => $source->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        $screen = $this->actingAs($user)->get(route('admin.sites.clone.prefill', $source));
        $screen->assertOk();
        $screen->assertSee('Clone Site');
        $screen->assertSee('Source site');

        $response = $this->actingAs($user)->post(route('admin.sites.clone.store'), [
            'source_site_id' => $source->id,
            'target_identifier' => 'target-preview',
            'target_handle' => 'target-preview',
            'target_name' => 'Target Preview',
            'with_navigation' => '1',
            'with_media' => '1',
            'with_translations' => '1',
            'dry_run' => '1',
        ]);

        $response->assertSessionHas('status');
        $this->assertSame(0, Site::query()->where('handle', 'target-preview')->count());
    }
}
