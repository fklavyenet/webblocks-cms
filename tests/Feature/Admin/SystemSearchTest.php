<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PublicSearchIndex;
use WebBlocks\Cms\Models\Site;

class SystemSearchTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function super_admin_can_view_search_status_screen(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.search.index'));

    $response->assertOk();
    $response->assertSee('Search Index');
    $response->assertSee('Rebuild Search Index');
    $response->assertSee('Search Index Status');
    $response->assertSee('Overview');
    $response->assertSee('Total indexed rows');
    $response->assertSee('Coverage by Site');
    $response->assertSee('Coverage by Locale');
    $response->assertSee('<table class="wb-table wb-table-striped">', false);
    $response->assertSee('<table class="wb-table wb-table-striped wb-table-hover">', false);
    $this->assertStringNotContainsString('wb-settings-row', $this->searchIndexStatusCard($response->getContent()));
  }

  #[Test]
  public function search_status_screen_keeps_index_breakdown_data_visible(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $locale = Locale::query()->where('is_default', true)->firstOrFail();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Searchable Page',
      'slug' => 'searchable-page',
      'status' => Page::STATUS_PUBLISHED,
    ]);

    $site->update(['domain' => 'example.test']);

    PublicSearchIndex::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $locale->id],
      [
        'site_id' => $site->id,
        'title' => 'Searchable Page',
        'excerpt' => 'Example excerpt',
        'url' => '/p/searchable-page',
        'content' => 'Example searchable body',
        'indexed_at' => now()->subMinute(),
      ],
    );

    $response = $this->actingAs($user)->get(route('admin.system.search.index'));

    $response->assertOk();
    $response->assertSee('Search Index Status');
    $response->assertSee('Total indexed rows');
    $response->assertSee('1');
    $response->assertSee($site->name);
    $response->assertSee($site->fresh()->domain);
    $response->assertSee($site->handle);
    $response->assertSee($locale->name);
    $response->assertSee(strtoupper($locale->code));
    $response->assertSee('<th>Site</th>', false);
    $response->assertSee('<th class="wb-text-end">Indexed rows</th>', false);
    $response->assertSee('<th>Locale</th>', false);
  }

  #[Test]
  public function non_super_admin_cannot_access_search_status_screen(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $user = User::factory()->siteAdmin()->create();

    $this->actingAs($user)->get(route('admin.system.search.index'))->assertForbidden();
  }

  #[Test]
  public function rebuild_action_returns_success_message(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
      ->post(route('admin.system.search.rebuild'))
      ->assertRedirect(route('admin.system.search.index'));

    $this->assertSame(0, PublicSearchIndex::query()->count());
  }

  private function searchIndexStatusCard(string $content): string
  {
    $start = strpos($content, '<div class="wb-card-header"><strong>Search Index Status</strong></div>');

    $this->assertIsInt($start);

    $end = strpos($content, '</main>', $start);

    $this->assertIsInt($end);

    return substr($content, $start, $end - $start);
  }
}
