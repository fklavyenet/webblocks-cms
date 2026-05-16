<?php

namespace Tests\Feature\Admin;

use App\Models\Block;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageAsset;
use App\Models\PageSlot;
use App\Models\SharedSlot;
use App\Models\Site;
use App\Models\SlotType;
use App\Models\User;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageImportTest extends TestCase
{
    use RefreshDatabase;

    private function seedFoundation(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);
    }

    private function defaultSite(): Site
    {
        return Site::query()->where('is_primary', true)->firstOrFail();
    }

    private function defaultLocale(): Locale
    {
        return Locale::query()->where('is_default', true)->firstOrFail();
    }

    private function slotType(string $slug, string $name, int $sortOrder): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => true],
        );
    }

    private function pageImportFile(array $payload, string $name = 'page-import.json'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function validPayload(): array
    {
        return [
            'schema' => 'webblocks.cms.page.v1',
            'page' => [
                'public_shell' => 'docs',
            ],
            'translations' => [
                'en' => [
                    'name' => 'Imported Docs Page',
                    'slug' => 'imported-docs-page',
                    'seo_title' => 'Imported Docs Page SEO',
                ],
            ],
            'slots' => [
                [
                    'slot' => 'main',
                    'source_type' => 'page',
                    'sort_order' => 0,
                ],
            ],
            'blocks' => [
                [
                    'id' => 'hero',
                    'slot' => 'main',
                    'type' => 'plain_text',
                    'status' => 'published',
                    'sort_order' => 0,
                    'translations' => [
                        'en' => [
                            'title' => 'Imported Body',
                            'content' => 'Imported body copy',
                        ],
                    ],
                ],
            ],
            'page_assets' => [
                [
                    'type' => 'css',
                    'path' => '/site/default-site/pages/imported-docs-page/page.css',
                ],
            ],
        ];
    }

    #[Test]
    public function import_page_button_appears_in_pages_listing_card_header_for_authorized_users(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.pages.index', ['site' => $site->id]));

        $response->assertOk();
        $response->assertSee('Import Page');
        $response->assertSee('modal=page-import', false);
    }

    #[Test]
    public function unauthorized_users_cannot_import_into_inaccessible_sites(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $otherSite = Site::query()->create([
            'name' => 'Other Site',
            'handle' => 'other-site',
            'domain' => 'other.example.test',
            'is_primary' => false,
        ]);
        $otherSite->locales()->syncWithoutDetaching([$this->defaultLocale()->id => ['is_enabled' => true]]);

        $editor = User::factory()->editor()->create();
        $editor->sites()->sync([$site->id]);

        $response = $this->actingAs($editor)->from(route('admin.pages.index', ['site' => $site->id]))->post(route('admin.pages.import.store'), [
            'site_id' => $otherSite->id,
            'import_as_draft' => 1,
            '_page_import_modal' => 'page-import-modal',
            'json_file' => $this->pageImportFile($this->validPayload()),
        ]);

        $response->assertRedirect(route('admin.pages.index', ['site' => $site->id]));
        $response->assertSessionHasErrors('site_id');
    }

    #[Test]
    public function valid_minimal_json_creates_a_draft_page_with_translation_slot_block_and_assets(): void
    {
        $this->seedFoundation();

        $this->slotType('main', 'Main', 1);
        $site = $this->defaultSite();
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->post(route('admin.pages.import.store'), [
            'site_id' => $site->id,
            'import_as_draft' => 1,
            'return_url' => route('admin.pages.index', ['site' => $site->id]),
            '_page_import_modal' => 'page-import-modal',
            'json_file' => $this->pageImportFile($this->validPayload()),
        ]);

        $page = Page::query()->where('site_id', $site->id)->latest('id')->firstOrFail();

        $response->assertRedirect(route('admin.pages.edit', ['page' => $page, 'return_url' => route('admin.pages.index', ['site' => $site->id])]));
        $this->assertSame(Page::STATUS_DRAFT, $page->status);
        $this->assertDatabaseHas('page_translations', [
            'page_id' => $page->id,
            'site_id' => $site->id,
            'name' => 'Imported Docs Page',
            'slug' => 'imported-docs-page',
            'path' => '/p/imported-docs-page',
        ]);
        $this->assertDatabaseHas('page_slots', [
            'page_id' => $page->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
        ]);

        $block = Block::query()->where('page_id', $page->id)->firstOrFail();
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $block->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Imported Body',
            'content' => 'Imported body copy',
        ]);
        $this->assertDatabaseHas('page_assets', [
            'page_id' => $page->id,
            'type' => PageAsset::TYPE_CSS,
            'path' => '/site/default-site/pages/imported-docs-page/page.css',
        ]);
    }

    #[Test]
    public function path_conflict_blocks_import_cleanly(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $this->slotType('main', 'Main', 1);
        $user = User::factory()->superAdmin()->create();

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Existing',
            'slug' => 'imported-docs-page',
            'status' => Page::STATUS_DRAFT,
        ]);

        $this->assertNotNull($page->defaultTranslation());

        $response = $this->actingAs($user)->from(route('admin.pages.index', ['site' => $site->id, 'modal' => 'page-import']))->post(route('admin.pages.import.store'), [
            'site_id' => $site->id,
            'import_as_draft' => 1,
            '_page_import_modal' => 'page-import-modal',
            'json_file' => $this->pageImportFile($this->validPayload()),
        ]);

        $response->assertRedirect(route('admin.pages.index', ['site' => $site->id, 'modal' => 'page-import']));
        $response->assertSessionHasErrors('translations');
    }

    #[Test]
    public function invalid_schema_is_rejected(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $this->slotType('main', 'Main', 1);
        $user = User::factory()->superAdmin()->create();

        $payload = $this->validPayload();
        $payload['schema'] = 'webblocks.cms.page.v999';

        $response = $this->actingAs($user)->from(route('admin.pages.index', ['site' => $site->id, 'modal' => 'page-import']))->post(route('admin.pages.import.store'), [
            'site_id' => $site->id,
            'import_as_draft' => 1,
            '_page_import_modal' => 'page-import-modal',
            'json_file' => $this->pageImportFile($payload),
        ]);

        $response->assertRedirect(route('admin.pages.index', ['site' => $site->id, 'modal' => 'page-import']));
        $response->assertSessionHasErrors('json_file');
    }

    #[Test]
    public function page_assets_reject_unsafe_paths(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $this->slotType('main', 'Main', 1);
        $user = User::factory()->superAdmin()->create();

        $payload = $this->validPayload();
        $payload['page_assets'][0]['path'] = 'https://example.com/evil.css';

        $response = $this->actingAs($user)->from(route('admin.pages.index', ['site' => $site->id, 'modal' => 'page-import']))->post(route('admin.pages.import.store'), [
            'site_id' => $site->id,
            'import_as_draft' => 1,
            '_page_import_modal' => 'page-import-modal',
            'json_file' => $this->pageImportFile($payload),
        ]);

        $response->assertRedirect(route('admin.pages.index', ['site' => $site->id, 'modal' => 'page-import']));
        $response->assertSessionHasErrors('json_file');
    }

    #[Test]
    public function shared_slot_handle_missing_or_incompatible_payload_is_rejected(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $this->slotType('main', 'Main', 1);
        $user = User::factory()->superAdmin()->create();

        $payload = $this->validPayload();
        $payload['slots'][0] = [
            'slot' => 'main',
            'source_type' => 'shared_slot',
            'shared_slot_handle' => 'missing-shared-slot',
        ];

        $response = $this->actingAs($user)->from(route('admin.pages.index', ['site' => $site->id, 'modal' => 'page-import']))->post(route('admin.pages.import.store'), [
            'site_id' => $site->id,
            'import_as_draft' => 1,
            '_page_import_modal' => 'page-import-modal',
            'json_file' => $this->pageImportFile($payload),
        ]);

        $response->assertRedirect(route('admin.pages.index', ['site' => $site->id, 'modal' => 'page-import']));
        $response->assertSessionHasErrors('slots');

        SharedSlot::query()->create([
            'site_id' => $site->id,
            'name' => 'Header Only',
            'handle' => 'header-only',
            'slot_name' => 'header',
            'public_shell' => 'docs',
            'is_active' => true,
        ]);

        $payload['slots'][0]['shared_slot_handle'] = 'header-only';

        $response = $this->actingAs($user)->from(route('admin.pages.index', ['site' => $site->id, 'modal' => 'page-import']))->post(route('admin.pages.import.store'), [
            'site_id' => $site->id,
            'import_as_draft' => 1,
            '_page_import_modal' => 'page-import-modal',
            'json_file' => $this->pageImportFile($payload),
        ]);

        $response->assertRedirect(route('admin.pages.index', ['site' => $site->id, 'modal' => 'page-import']));
        $response->assertSessionHasErrors('slots');
    }

    #[Test]
    public function successful_import_preserves_pages_listing_return_url(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $this->slotType('main', 'Main', 1);
        $user = User::factory()->superAdmin()->create();
        $returnUrl = route('admin.pages.index', ['site' => $site->id, 'search' => 'Docs', 'status' => 'draft']);

        $response = $this->actingAs($user)->post(route('admin.pages.import.store'), [
            'site_id' => $site->id,
            'import_as_draft' => 1,
            'return_url' => $returnUrl,
            '_page_import_modal' => 'page-import-modal',
            'json_file' => $this->pageImportFile($this->validPayload()),
        ]);

        $page = Page::query()->latest('id')->firstOrFail();

        $response->assertRedirect(route('admin.pages.edit', ['page' => $page, 'return_url' => $returnUrl]));
    }

    #[Test]
    public function pages_listing_filters_and_actions_remain_intact_with_import_modal(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $this->slotType('main', 'Main', 1);
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.pages.index', [
            'site' => $site->id,
            'search' => 'Pattern',
            'status' => 'draft',
            'sort' => 'title',
            'direction' => 'asc',
            'modal' => 'page-import',
        ]));

        $response->assertOk();
        $response->assertSee('id="pages_search"', false);
        $response->assertSee('id="pages_status"', false);
        $response->assertSee('id="pages_sort"', false);
        $response->assertSee('id="pages_direction"', false);
        $response->assertSee('New Page');
        $response->assertSee('Import Page');
        $response->assertSee('page-import-modal', false);
        $response->assertSee('data-wb-admin-close-url=', false);
        $response->assertSee('data-wb-admin-dirty-form', false);
    }
}
