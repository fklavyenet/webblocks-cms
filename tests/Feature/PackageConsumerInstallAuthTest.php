<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\SlotType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Http\Controllers\Admin\SystemUpdateController as PackageSystemUpdateController;

class PackageConsumerInstallAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('webblocks:install', [
            '--name' => 'Auth Admin',
            '--email' => 'auth-admin@example.com',
            '--password' => 'secret-password',
            '--site-name' => 'Auth Site',
            '--site-handle' => 'auth-site',
            '--no-interaction' => true,
            '--force' => true,
        ])->assertExitCode(0);
    }

    #[Test]
    public function package_login_route_and_view_exist_after_install(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('login');
        $response->assertSee('email');
    }

    #[Test]
    public function admin_route_redirects_guests_to_the_login_page(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function first_super_admin_can_log_in_and_reach_the_admin_dashboard(): void
    {
        $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();

        $loginResponse = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $loginResponse->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);

        $dashboard = $this->actingAs($user)->get(route('admin.dashboard'));

        $dashboard->assertOk();
        $dashboard->assertSee('Dashboard');
    }

    #[Test]
    public function public_home_route_renders_after_install(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Install WebBlocks CMS');
    }

    #[Test]
    public function admin_sites_and_navigation_routes_render_after_install(): void
    {
        $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();

        $this->actingAs($user)->get(route('admin.sites.index'))
            ->assertOk();

        $this->actingAs($user)->get(route('admin.navigation.index'))
            ->assertOk();
    }

    #[Test]
    public function admin_users_index_and_create_routes_render_after_install(): void
    {
        $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();

        $this->actingAs($user)->get(route('admin.users.index'))
            ->assertOk();

        $this->actingAs($user)->get(route('admin.users.create'))
            ->assertOk();
    }

    #[Test]
    public function broader_package_routed_admin_surfaces_render_after_install(): void
    {
        $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();

        foreach ([
            'admin.dashboard',
            'admin.sites.index',
            'admin.sites.create',
            'admin.users.index',
            'admin.users.create',
            'admin.locales.index',
            'admin.pages.index',
            'admin.blocks.index',
            'admin.media.index',
            'admin.navigation.index',
            'admin.shared-slots.index',
            'admin.system.settings.edit',
            'admin.block-types.index',
            'admin.page-layouts.index',
            'admin.slot-types.index',
            'admin.system.icons.index',
            'admin.system.search.index',
            'admin.system.backups.index',
            'admin.site-transfers.exports.index',
            'admin.system.updates.index',
            'admin.reports.visitors.index',
            'admin.contact-messages.index',
        ] as $routeName) {
            $this->actingAs($user)->get(route($routeName))->assertOk();
        }
    }

    #[Test]
    public function representative_package_owned_admin_routes_render_after_install_without_root_admin_view_dependencies(): void
    {
        $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();

        foreach ([
            'admin.dashboard',
            'admin.pages.index',
            'admin.pages.create',
            'admin.blocks.index',
            'admin.media.index',
            'admin.navigation.index',
            'admin.shared-slots.index',
            'admin.block-types.index',
            'admin.page-layouts.index',
            'admin.slot-types.index',
            'admin.system.settings.edit',
        ] as $routeName) {
            $this->actingAs($user)->get(route($routeName))->assertOk();
        }
    }

    #[Test]
    public function system_updates_route_renders_after_install_through_the_package_view_boundary(): void
    {
        $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();
        $route = app('router')->getRoutes()->getByName('admin.system.updates.index');
        $controller = (string) $route?->getAction('controller');
        $controllerSource = (string) file_get_contents(base_path('packages/webblocks-cms/src/Http/Controllers/Admin/SystemUpdateController.php'));

        $this->assertNotNull($route);
        $this->assertStringStartsWith(PackageSystemUpdateController::class.'@', $controller);
        $this->assertStringContainsString("view('webblocks-cms::admin.system.updates'", $controllerSource);
        $this->assertStringNotContainsString("view('admin.system.updates'", $controllerSource);
        $this->assertStringNotContainsString("View::make('admin.system.updates'", $controllerSource);
        $this->assertStringNotContainsString("response()->view('admin.system.updates'", $controllerSource);

        $this->actingAs($user)->get(route('admin.system.updates.index'))
            ->assertOk()
            ->assertSee('System Updates')
            ->assertSee('Update Summary')
            ->assertSee('Actions');
    }

    #[Test]
    public function fallback_block_type_admin_form_renders_after_install_through_the_package_view_boundary(): void
    {
        $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();
        $page = Page::query()->firstOrFail();
        $slotType = SlotType::query()->where('slug', 'main')->firstOrFail();
        $pageSlot = PageSlot::query()->where('page_id', $page->id)->where('slot_type_id', $slotType->id)->firstOrFail();
        $blockType = BlockType::query()->create([
            'name' => 'Consumer Fallback Block',
            'slug' => 'consumer-fallback-block',
            'category' => 'content',
            'description' => 'Uses the generic fallback admin form.',
            'source_type' => 'static',
            'status' => 'published',
            'sort_order' => 9990,
            'is_system' => false,
            'is_container' => false,
        ]);
        $block = new Block([
            'type' => $blockType->slug,
            'block_type_id' => $blockType->id,
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'slot' => $slotType->slug,
            'source_type' => 'static',
            'status' => 'draft',
        ]);

        $this->assertSame('webblocks-cms::admin.blocks.types.fallback', $block->adminFormView());

        $this->actingAs($user)->get(route('admin.pages.slots.blocks', [
            'page' => $page,
            'slot' => $pageSlot,
            'picker' => 1,
            'block_type_id' => $blockType->id,
        ]))
            ->assertOk()
            ->assertSee('Add Block: Consumer Fallback Block')
            ->assertSee('Generic Block Form')
            ->assertSee('The safe fallback form is being used.')
            ->assertSee('name="title"', false)
            ->assertSee('name="content"', false);
    }

    #[Test]
    public function inline_fallback_block_type_admin_form_renders_after_install_through_the_package_view_boundary(): void
    {
        $page = Page::query()->firstOrFail();
        $blockType = BlockType::query()->create([
            'name' => 'Consumer Inline Fallback Block',
            'slug' => 'consumer-inline-fallback-block',
            'category' => 'content',
            'description' => 'Uses the generic fallback inline admin form.',
            'source_type' => 'static',
            'status' => 'published',
            'sort_order' => 9991,
            'is_system' => false,
            'is_container' => false,
        ]);
        $slotTypes = SlotType::query()->orderBy('sort_order')->get();
        $block = new Block([
            'type' => $blockType->slug,
            'block_type_id' => $blockType->id,
            'page_id' => $page->id,
            'slot_type_id' => $slotTypes->firstOrFail()->id,
            'slot' => $slotTypes->firstOrFail()->slug,
            'source_type' => 'static',
            'status' => 'draft',
        ]);

        $rendered = View::make('webblocks-cms::admin.pages.partials.inline-block-fields', [
            'block' => $block,
            'index' => 0,
            'selectedBlockType' => $blockType,
            'slotTypes' => $slotTypes,
        ])->render();

        $this->assertStringContainsString('Generic Block Form', $rendered);
        $this->assertStringContainsString('The safe fallback form is being used.', $rendered);
        $this->assertStringContainsString('name="blocks[0][title]"', $rendered);
        $this->assertStringContainsString('name="blocks[0][content]"', $rendered);
    }
}
