<?php

namespace Tests\Feature\Admin;

use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class SharedAdminPartialPackageViewTest extends TestCase
{
    #[Test]
    public function shared_admin_partials_resolve_from_package_and_root_compatibility_paths(): void
    {
        $packagePrefix = WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::';

        $this->assertTrue(view()->exists($packagePrefix.'admin.partials.page-header'));
        $this->assertTrue(view()->exists($packagePrefix.'admin.partials.flash'));
        $this->assertTrue(view()->exists($packagePrefix.'admin.partials.listing-filters'));
        $this->assertTrue(view()->exists($packagePrefix.'admin.partials.page-actions'));
        $this->assertTrue(view()->exists($packagePrefix.'admin.partials.pagination'));
        $this->assertTrue(view()->exists($packagePrefix.'admin.partials.audit-actor'));
        $this->assertTrue(view()->exists($packagePrefix.'components.admin.form-actions'));

        $this->assertTrue(view()->exists('admin.partials.page-header'));
        $this->assertTrue(view()->exists('admin.partials.flash'));
        $this->assertTrue(view()->exists('admin.partials.listing-filters'));
        $this->assertTrue(view()->exists('admin.partials.page-actions'));
        $this->assertTrue(view()->exists('admin.partials.pagination'));
        $this->assertTrue(view()->exists('admin.partials.audit-actor'));

        $packageHeader = view($packagePrefix.'admin.partials.page-header', [
            'title' => 'Package Header',
            'count' => 12,
            'description' => 'Rendered from package partial.',
        ])->render();

        $rootHeader = view('admin.partials.page-header', [
            'title' => 'Package Header',
            'count' => 12,
            'description' => 'Rendered from package partial.',
        ])->render();

        $this->assertSame($packageHeader, $rootHeader);
        $this->assertStringContainsString('wb-page-header-title', $packageHeader);
        $this->assertStringContainsString('data-admin-page-count', $packageHeader);
    }

    #[Test]
    public function flash_partial_keeps_status_action_and_named_error_messages_through_package_and_root_views(): void
    {
        session()->flash('status', 'Saved successfully.');
        session()->flash('status_action', [
            'url' => '/preview',
            'label' => 'View preview',
        ]);

        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag([
            'locale_lifecycle' => ['Locale cannot be disabled.'],
        ]));

        $packageHtml = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.partials.flash', [
            'errors' => $errors,
        ])->render();

        $rootHtml = view('admin.partials.flash', [
            'errors' => $errors,
        ])->render();

        $this->assertSame($packageHtml, $rootHtml);
        $this->assertStringContainsString('Saved successfully.', $packageHtml);
        $this->assertStringContainsString('href="/preview"', $packageHtml);
        $this->assertStringContainsString('View preview', $packageHtml);
        $this->assertStringContainsString('Locale Action Blocked', $packageHtml);
        $this->assertStringContainsString('Locale cannot be disabled.', $packageHtml);
    }

    #[Test]
    public function page_actions_keep_public_link_and_details_modal_trigger(): void
    {
        $page = new class
        {
            public function isPublished(): bool
            {
                return true;
            }

            public function publicUrl(): string
            {
                return '/published-page';
            }
        };

        $packageHtml = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.partials.page-actions', [
            'page' => $page,
        ])->render();

        $rootHtml = view('admin.partials.page-actions', [
            'page' => $page,
        ])->render();

        $this->assertSame($packageHtml, $rootHtml);
        $this->assertStringContainsString('href="/published-page"', $packageHtml);
        $this->assertStringContainsString('target="_blank"', $packageHtml);
        $this->assertStringContainsString('aria-controls="pageDetailsModal"', $packageHtml);
        $this->assertStringContainsString('wb-icon-panel-right', $packageHtml);
        $this->assertStringContainsString('details=1', $packageHtml);
    }

    #[Test]
    public function package_owned_page_builder_view_renders_moved_page_actions_and_flash_partials(): void
    {
        session()->flash('status', 'Page builder saved.');

        $page = new class implements UrlRoutable
        {
            public int $id = 42;

            public string $title = 'Docs';

            public ?object $pageType = null;

            public ?object $layout = null;

            public function publicPath(): string
            {
                return '/docs';
            }

            public function workflowBadgeClass(): string
            {
                return 'wb-status-success';
            }

            public function workflowLabel(): string
            {
                return 'Published';
            }

            public function isPublished(): bool
            {
                return true;
            }

            public function publicUrl(): string
            {
                return '/docs';
            }

            public function getRouteKey()
            {
                return $this->id;
            }

            public function getRouteKeyName()
            {
                return 'id';
            }

            public function resolveRouteBinding($value, $field = null)
            {
                return null;
            }

            public function resolveChildRouteBinding($childType, $value, $field = null)
            {
                return null;
            }
        };

        $html = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.pages.show', [
            'page' => $page,
            'blockSummary' => ['total' => 3, 'published' => 2, 'draft' => 1],
            'outline' => collect(),
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertStringContainsString('Page Builder: Docs', $html);
        $this->assertStringContainsString('Page builder saved.', $html);
        $this->assertStringContainsString('href="/docs" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">View Page</a>', $html);
        $this->assertStringContainsString('aria-controls="pageDetailsModal"', $html);
        $this->assertStringContainsString('No starter content found', $html);
        $this->assertStringContainsString('Manage Slots', $html);
    }

    #[Test]
    public function listing_filters_keep_search_first_and_actions_last_through_package_view(): void
    {
        $html = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.partials.listing-filters', [
            'action' => '/cms/example',
            'search' => [
                'id' => 'filter-search',
                'label' => 'Search',
                'name' => 'search',
                'value' => 'alpha',
                'placeholder' => 'Search items',
            ],
            'selects' => [
                [
                    'id' => 'filter-status',
                    'label' => 'Status',
                    'name' => 'status',
                    'selected' => 'draft',
                    'options' => ['draft' => 'Draft', 'published' => 'Published'],
                ],
            ],
            'showReset' => true,
            'resetUrl' => '/cms/example',
        ])->render();

        $searchPosition = strpos($html, 'data-admin-listing-filters-search');
        $fieldsPosition = strpos($html, 'data-admin-listing-filters-fields');
        $actionsPosition = strpos($html, 'data-admin-listing-filters-actions');

        $this->assertNotFalse($searchPosition);
        $this->assertNotFalse($fieldsPosition);
        $this->assertNotFalse($actionsPosition);
        $this->assertLessThan($fieldsPosition, $searchPosition);
        $this->assertLessThan($actionsPosition, $fieldsPosition);
        $this->assertStringContainsString('name="search"', $html);
        $this->assertStringContainsString('<button type="submit" class="wb-btn wb-btn-primary">Apply</button>', $html);
        $this->assertStringContainsString('<a href="/cms/example" class="wb-btn wb-btn-secondary">Reset</a>', $html);
    }

    #[Test]
    public function pagination_keeps_compact_summary_and_appended_query_strings(): void
    {
        $paginator = new LengthAwarePaginator(
            collect(range(16, 30)),
            45,
            15,
            2,
            ['path' => '/cms/example']
        );

        $paginator->appends(['search' => 'alpha', 'status' => 'draft']);

        $html = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.partials.pagination', [
            'paginator' => $paginator,
            'ariaLabel' => 'Example pagination',
            'compact' => true,
        ])->render();

        $this->assertStringContainsString('wb-admin-pagination-compact', $html);
        $this->assertStringContainsString('16-30/45', $html);
        $this->assertStringContainsString('search=alpha', $html);
        $this->assertStringContainsString('status=draft', $html);
        $this->assertStringContainsString('page=3', $html);
    }

    #[Test]
    public function package_and_root_form_actions_components_keep_primary_cancel_and_destructive_ordering(): void
    {
        $packageHtml = Blade::render(
            '<x-webblocks-cms::admin.form-actions cancel-url="/cancel" submit-label="Save Now" delete-form-action="/delete" delete-label="Delete Now" />'
        );

        $rootHtml = Blade::render(
            '<x-admin.form-actions cancel-url="/cancel" submit-label="Save Now" delete-form-action="/delete" delete-label="Delete Now" />'
        );

        $this->assertSame($packageHtml, $rootHtml);
        $this->assertStringContainsString('data-admin-form-actions', $packageHtml);
        $this->assertStringContainsString('data-admin-form-actions-main', $packageHtml);
        $this->assertStringContainsString('data-admin-form-actions-danger', $packageHtml);
        $this->assertStringContainsString('>Save Now</button>', $packageHtml);
        $this->assertStringContainsString('href="/cancel" class="wb-btn wb-btn-secondary"', $packageHtml);
        $this->assertStringContainsString('action="/delete"', $packageHtml);
        $this->assertStringContainsString('>Delete Now</button>', $packageHtml);

        $this->assertLessThan(strpos($packageHtml, 'href="/cancel"'), strpos($packageHtml, '>Save Now</button>'));
        $this->assertLessThan(strpos($packageHtml, '>Delete Now</button>'), strpos($packageHtml, 'href="/cancel"'));
    }

    #[Test]
    public function audit_actor_keeps_fallback_text(): void
    {
        $html = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.partials.audit-actor', [
            'actor' => null,
        ])->render();

        $this->assertStringContainsString('Not recorded', $html);
    }
}
