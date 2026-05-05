<?php

namespace Tests\Feature\Admin;

use App\Models\BlockType;
use App\Models\User;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlockTypesIndexTest extends TestCase
{
    use RefreshDatabase;

    private function seedFoundation(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);
    }

    #[Test]
    public function index_shows_filter_form_and_listing_actions(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.block-types.index'));

        $response->assertOk();
        $response->assertSee('Search');
        $response->assertSee('Category');
        $response->assertSee('Status');
        $response->assertSee('Support');
        $response->assertSee('data-admin-listing-filters', false);
        $response->assertSee('data-admin-listing-filters-search', false);
        $response->assertSee('data-admin-listing-filters-fields', false);
        $response->assertSee('data-admin-listing-filters-actions', false);
        $response->assertSee('id="block_types_search"', false);
        $response->assertSee('id="block_types_category"', false);
        $response->assertSee('id="block_types_status"', false);
        $response->assertSee('id="block_types_support"', false);
        $response->assertSee('Apply filters');
        $response->assertSee('Search block types...');
        $response->assertSee('New Custom Block Type');
        $response->assertSee('Edit block type');
    }

    #[Test]
    public function search_filters_by_name_slug_and_description(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.block-types.index', ['search' => 'rich']));

        $response->assertOk();
        $response->assertSee('Rich Text');
        $response->assertDontSee('Sidebar Navigation');

        $response = $this->actingAs($user)->get(route('admin.block-types.index', ['search' => 'breadcrumb']));

        $response->assertOk();
        $response->assertSee('Breadcrumb');
        $response->assertDontSee('Plain Text');
    }

    #[Test]
    public function category_filter_limits_results_to_matching_category(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.block-types.index', ['category' => 'layout']));

        $response->assertOk();
        $response->assertSee('Section');
        $response->assertSee('Container');
        $response->assertDontSee('Breadcrumb');
        $response->assertDontSee('Rich Text');
    }

    #[Test]
    public function status_filter_limits_results_to_matching_status(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.block-types.index', ['status' => 'draft']));

        $response->assertOk();
        $response->assertSee('Accordion');
        $response->assertSee('legacy');
        $response->assertDontSee('Breadcrumb');
    }

    #[Test]
    public function support_filters_cover_system_user_container_admin_and_render_options(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();

        $systemResponse = $this->actingAs($user)->get(route('admin.block-types.index', ['support' => 'system']));
        $systemResponse->assertOk();
        $systemResponse->assertSee('Breadcrumb');
        $systemResponse->assertDontSee('Section');

        $userResponse = $this->actingAs($user)->get(route('admin.block-types.index', ['support' => 'user']));
        $userResponse->assertOk();
        $userResponse->assertSee('Section');
        $userResponse->assertDontSee('Breadcrumb');

        $containerResponse = $this->actingAs($user)->get(route('admin.block-types.index', ['support' => 'container']));
        $containerResponse->assertOk();
        $containerResponse->assertSee('Section');
        $containerResponse->assertDontSee('Breadcrumb');

        $adminResponse = $this->actingAs($user)->get(route('admin.block-types.index', ['support' => 'admin']));
        $adminResponse->assertOk();
        $adminResponse->assertSee('Rich Text');
        $adminResponse->assertDontSee('Map');

        $renderResponse = $this->actingAs($user)->get(route('admin.block-types.index', ['support' => 'render']));
        $renderResponse->assertOk();
        $renderResponse->assertSee('Rich Text');
        $renderResponse->assertDontSee('Textarea');
    }

    #[Test]
    public function pagination_preserves_filters_and_uses_webblocks_pagination_contract(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();

        foreach (range(1, 35) as $index) {
            BlockType::query()->create([
                'name' => 'Filterable Pattern '.$index,
                'slug' => 'filterable-pattern-'.$index,
                'description' => 'Pattern testing block type '.$index,
                'category' => 'pattern',
                'source_type' => 'static',
                'is_system' => false,
                'is_container' => false,
                'sort_order' => 200 + $index,
                'status' => 'published',
            ]);
        }

        $response = $this->actingAs($user)->get(route('admin.block-types.index', [
            'search' => 'Pattern',
            'category' => 'pattern',
            'status' => 'published',
            'support' => 'user',
        ]));

        $response->assertOk();
        $response->assertSee('data-admin-pagination', false);
        $response->assertSee('class="wb-pagination wb-pagination-compact"', false);
        $response->assertSee('aria-label="Block types pagination"', false);
        $response->assertSee('class="wb-pagination-list"', false);
        $response->assertSee('aria-current="page">1</span>', false);
        $response->assertSee('data-admin-pagination-summary', false);
        $response->assertSee('1-15/35', false);
        $response->assertDontSee('Showing 1-15 of 35', false);
        $response->assertSee('search=Pattern&amp;category=pattern&amp;status=published&amp;support=user&amp;page=2', false);
        $response->assertSee('<span class="wb-pagination-link">Previous</span>', false);

        $pageTwo = $this->actingAs($user)->get(route('admin.block-types.index', [
            'search' => 'Pattern',
            'category' => 'pattern',
            'status' => 'published',
            'support' => 'user',
            'page' => 2,
        ]));

        $pageTwo->assertOk();
        $pageTwo->assertSee('aria-current="page">2</span>', false);
        $pageTwo->assertSee('16-30/35', false);
        $pageTwo->assertSee('search=Pattern&amp;category=pattern&amp;status=published&amp;support=user&amp;page=1', false);
        $pageTwo->assertSee('search=Pattern&amp;category=pattern&amp;status=published&amp;support=user&amp;page=3', false);
    }

    #[Test]
    public function empty_results_show_filter_reset_state_without_breaking_table_card_flow(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.block-types.index', ['search' => 'does-not-exist']));

        $response->assertOk();
        $response->assertSee('No block types found.');
        $response->assertSee('Try changing your filters.');
        $response->assertSee('>Reset<', false);
        $response->assertDontSee('<table class="wb-table', false);
    }
}
