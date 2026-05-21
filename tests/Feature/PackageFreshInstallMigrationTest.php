<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PackageFreshInstallMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array<string, array{reason: string, why: string}>>
     */
    private function packageBoundaryAllowlist(): array
    {
        return [
            'packages/webblocks-cms/src/Models/Block.php' => [
                "'admin.blocks.types." => [
                    'reason' => 'root compatibility fallback after package-first lookup',
                    'why' => 'Block admin form resolution checks the package namespaced block type view first and uses the historical root admin block type view only as an explicit compatibility fallback for existing install-specific overrides.',
                ],
            ],
            'packages/webblocks-cms/src/Http/Controllers/Admin/BlockTypeController.php' => [
                "'admin.blocks.types." => [
                    'reason' => 'custom install-specific/root override support',
                    'why' => 'The block type contract index reports dedicated admin-form support when either the package-owned admin block type view or an intentional install-specific root override exists, without making the root path the primary package runtime target.',
                ],
            ],
            'packages/webblocks-cms/resources/views/admin/pages/partials/inline-block-fields.blade.php' => [
                "'admin.blocks.types." => [
                    'reason' => 'root compatibility fallback after package-first lookup',
                    'why' => 'Inline block field rendering resolves the package inline view and package fallback first, then keeps the legacy root block type path only as an explicit compatibility fallback for installs that still provide root-only inline block admin overrides.',
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function packageFiles(string $path, string $extension): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo || ! $file->isFile() || $file->getExtension() !== $extension) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function packageBoundaryAuditPatterns(): array
    {
        return [
            'php-root-view' => [
                "view('admin.",
                "view('admin/",
                'View::make(\'admin.',
                'View::make(\'admin/',
                "response()->view('admin.",
                "response()->view('admin/",
                "component('admin.",
                "'layouts.admin'",
                "'admin.blocks.types.",
            ],
            'blade-root-view' => [
                "@include('admin.",
                "@includeIf('admin.",
                "@extends('layouts.admin')",
                '<x-admin.',
                '<x-auth-password-field',
                "component('admin.",
                "'admin.blocks.types.",
            ],
            'routes-root-view' => [
                "view('admin.",
                "view('admin/",
                'View::make(\'admin.',
                'View::make(\'admin/',
                "response()->view('admin.",
                "response()->view('admin/",
                "component('admin.",
                "'layouts.admin'",
                "'admin.blocks.types.",
            ],
        ];
    }

    private function assertPackageBoundaryAuditPasses(string $path, string $extension, array $patterns): void
    {
        $allowlist = $this->packageBoundaryAllowlist();
        $this->addToAssertionCount(1);

        foreach ($this->packageFiles(base_path($path), $extension) as $file) {
            $relativePath = str_replace(base_path().'/', '', $file);
            $contents = (string) file_get_contents($file);

            foreach ($patterns as $pattern) {
                if (! str_contains($contents, $pattern)) {
                    continue;
                }

                $allowedPattern = $allowlist[$relativePath][$pattern] ?? null;

                $this->assertNotNull(
                    $allowedPattern,
                    sprintf('Unexpected package boundary reference [%s] in %s', $pattern, $relativePath)
                );
                $this->assertIsArray($allowedPattern, sprintf('Allowlist entry must be an array for [%s] in %s', $pattern, $relativePath));
                /** @var array{reason: string, why: string} $allowedPattern */
                $this->assertArrayHasKey('reason', $allowedPattern, sprintf('Allowlist reason missing for [%s] in %s', $pattern, $relativePath));
                $this->assertArrayHasKey('why', $allowedPattern, sprintf('Allowlist why missing for [%s] in %s', $pattern, $relativePath));
                $this->assertNotSame('', trim($allowedPattern['reason']), sprintf('Allowlist reason must be non-empty for [%s] in %s', $pattern, $relativePath));
                $this->assertNotSame('', trim($allowedPattern['why']), sprintf('Allowlist why must be non-empty for [%s] in %s', $pattern, $relativePath));
            }
        }
    }

    #[Test]
    public function shared_slot_revision_restored_from_foreign_key_uses_an_explicit_short_name(): void
    {
        $migration = (string) file_get_contents(base_path('packages/webblocks-cms/database/migrations/fresh/2026_05_20_120000_create_webblocks_cms_fresh_install_schema.php'));

        $this->assertStringContainsString("foreign('restored_from_shared_slot_revision_id', 'ss_revisions_restored_from_fk')", $migration);
        $this->assertStringNotContainsString('shared_slot_revisions_restored_from_shared_slot_revision_id_foreign', $migration);
    }

    #[Test]
    public function fresh_install_schema_keeps_site_variables_runtime_columns_and_query_contract(): void
    {
        $this->assertTrue(Schema::hasTable('site_variables'));
        $this->assertTrue(Schema::hasColumn('site_variables', 'site_id'));
        $this->assertTrue(Schema::hasColumn('site_variables', 'key'));
        $this->assertTrue(Schema::hasColumn('site_variables', 'label'));
        $this->assertTrue(Schema::hasColumn('site_variables', 'value'));
        $this->assertTrue(Schema::hasColumn('site_variables', 'sort_order'));
        $this->assertTrue(Schema::hasColumn('site_variables', 'is_enabled'));

        $siteId = DB::table('sites')->insertGetId([
            'name' => 'Fresh Schema Site',
            'handle' => 'fresh-schema-site',
            'domain' => null,
            'is_primary' => true,
            'display_name' => null,
            'tagline' => null,
            'favicon_media_id' => null,
            'social_image_media_id' => null,
            'seo_title' => null,
            'seo_description' => null,
            'seo_keywords' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('site_variables')->insert([
            [
                'site_id' => $siteId,
                'key' => 'disabled_variable',
                'label' => 'Disabled Variable',
                'value' => 'disabled',
                'sort_order' => 5,
                'is_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $siteId,
                'key' => 'first_enabled',
                'label' => 'First Enabled',
                'value' => 'first',
                'sort_order' => 10,
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'site_id' => $siteId,
                'key' => 'second_enabled',
                'label' => 'Second Enabled',
                'value' => 'second',
                'sort_order' => 20,
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $rows = DB::table('site_variables')
            ->where('site_id', $siteId)
            ->whereNotNull('site_id')
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame(['first_enabled', 'second_enabled'], $rows->pluck('key')->all());
    }

    #[Test]
    public function fresh_site_variables_schema_declares_the_historical_runtime_indexes(): void
    {
        $migration = (string) file_get_contents(base_path('packages/webblocks-cms/database/migrations/fresh/2026_05_20_120000_create_webblocks_cms_fresh_install_schema.php'));
        $historicalMigration = (string) file_get_contents(base_path('packages/webblocks-cms/database/migrations/2026_05_09_000001_create_site_variables_table.php'));

        foreach ([
            '$table->unsignedInteger(\'sort_order\')->default(0);',
            '$table->boolean(\'is_enabled\')->default(true);',
            '$table->unique([\'site_id\', \'key\']);',
            '$table->index([\'site_id\', \'sort_order\', \'id\']);',
            '$table->index([\'site_id\', \'is_enabled\']);',
        ] as $expectedFragment) {
            $this->assertStringContainsString($expectedFragment, $historicalMigration);
            $this->assertStringContainsString($expectedFragment, $migration);
        }
    }

    #[Test]
    public function fresh_page_translation_schema_matches_site_scoped_foreign_key_contract(): void
    {
        $migration = (string) file_get_contents(base_path('packages/webblocks-cms/database/migrations/fresh/2026_05_20_120000_create_webblocks_cms_fresh_install_schema.php'));
        $historicalMigration = (string) file_get_contents(base_path('packages/webblocks-cms/database/migrations/2026_04_25_102716_harden_page_translation_site_integrity.php'));
        $updateMigration = (string) file_get_contents(base_path('packages/webblocks-cms/database/migrations/updates/2026_05_21_213000_ensure_pages_site_parent_key.php'));

        foreach ([
            "\$table->unique(['id', 'site_id'], 'pages_id_site_id_unique');",
            "\$table->unique(['site_id', 'locale_id', 'slug'], 'page_translations_site_locale_slug_unique');",
            "\$table->unique(['site_id', 'locale_id', 'path'], 'page_translations_site_locale_path_unique');",
            "\$table->index(['site_id', 'page_id'], 'page_translations_site_id_page_id_index');",
            "\$table->index(['locale_id', 'site_id'], 'page_translations_locale_id_site_id_index');",
            "\$table->foreign(['page_id', 'site_id'], 'page_translations_page_id_site_id_foreign')",
            "->references(['id', 'site_id'])",
            "->on('pages')",
            "->cascadeOnDelete();",
        ] as $expectedFragment) {
            $this->assertStringContainsString($expectedFragment, $migration);
        }

        $this->assertStringContainsString("\$table->unique(['id', 'site_id'], 'pages_id_site_id_unique');", $historicalMigration);
        $this->assertStringContainsString("\$table->foreign(['page_id', 'site_id'], 'page_translations_page_id_site_id_foreign')", $historicalMigration);
        $this->assertStringContainsString("\$table->unique(['id', 'site_id'], self::INDEX_NAME);", $updateMigration);
        $this->assertStringContainsString("private const INDEX_NAME = 'pages_id_site_id_unique';", $updateMigration);
    }

    #[Test]
    public function package_owned_views_do_not_reference_root_admin_components_or_includes(): void
    {
        $this->assertPackageBoundaryAuditPasses(
            'packages/webblocks-cms/resources/views',
            'php',
            $this->packageBoundaryAuditPatterns()['blade-root-view']
        );
    }

    #[Test]
    public function package_owned_admin_php_renderers_do_not_use_root_admin_view_names(): void
    {
        $this->assertPackageBoundaryAuditPasses(
            'packages/webblocks-cms/src',
            'php',
            $this->packageBoundaryAuditPatterns()['php-root-view']
        );
    }

    #[Test]
    public function package_owned_routes_do_not_reference_root_admin_view_names(): void
    {
        $this->assertPackageBoundaryAuditPasses(
            'packages/webblocks-cms/routes',
            'php',
            $this->packageBoundaryAuditPatterns()['routes-root-view']
        );
    }

    #[Test]
    public function package_owned_block_admin_runtime_prefers_package_block_type_views_and_fallbacks(): void
    {
        $blockModel = (string) file_get_contents(base_path('packages/webblocks-cms/src/Models/Block.php'));
        $inlineFields = (string) file_get_contents(base_path('packages/webblocks-cms/resources/views/admin/pages/partials/inline-block-fields.blade.php'));
        $fallbackView = (string) file_get_contents(base_path('packages/webblocks-cms/resources/views/admin/blocks/types/fallback.blade.php'));
        $fallbackInlineView = (string) file_get_contents(base_path('packages/webblocks-cms/resources/views/admin/blocks/types/fallback-inline.blade.php'));

        $this->assertStringContainsString("WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.blocks.types.'", $blockModel);
        $this->assertStringContainsString("return \$this->fallbackAdminFormView('fallback');", $blockModel);
        $this->assertStringNotContainsString("return View::exists(\$view) ? \$view : 'admin.blocks.types.fallback';", $blockModel);
        $this->assertStringContainsString("'webblocks-cms::admin.blocks.types.fallback-inline'", $inlineFields);
        $this->assertStringNotContainsString("@include(view()->exists(\$inlineView) ? \$inlineView : 'admin.blocks.types.fallback-inline'", $inlineFields);
        $this->assertStringContainsString("@include('webblocks-cms::admin.media.asset-picker-panel'", $fallbackView);
        $this->assertStringContainsString('Generic Block Form', $fallbackView);
        $this->assertStringContainsString('Generic Block Form', $fallbackInlineView);
    }

    #[Test]
    public function package_owned_system_admin_renderers_use_package_view_names_for_system_screens(): void
    {
        $expectations = [
            'packages/webblocks-cms/src/Http/Controllers/Admin/SystemUpdateController.php' => [
                "::admin.system.updates'",
                "view('admin.system.updates'",
                "View::make('admin.system.updates'",
                "response()->view('admin.system.updates'",
            ],
            'packages/webblocks-cms/src/Http/Controllers/Admin/SystemBackupController.php' => [
                "::admin.system.backups.index'",
                "view('admin.system.backups",
                "View::make('admin.system.backups",
                "response()->view('admin.system.backups",
            ],
            'packages/webblocks-cms/src/Http/Controllers/Admin/SystemSearchController.php' => [
                "::admin.system.search'",
                "view('admin.system.search'",
                "View::make('admin.system.search'",
                "response()->view('admin.system.search'",
            ],
            'packages/webblocks-cms/src/Http/Controllers/Admin/SystemSettingsController.php' => [
                "::admin.system.settings'",
                "view('admin.system.settings'",
                "View::make('admin.system.settings'",
                "response()->view('admin.system.settings'",
            ],
            'packages/webblocks-cms/src/Http/Controllers/Admin/IconCatalogController.php' => [
                "::admin.system.icons.index'",
                "view('admin.system.icons",
                "View::make('admin.system.icons",
                "response()->view('admin.system.icons",
            ],
            'packages/webblocks-cms/src/Http/Controllers/Admin/BlockTypeController.php' => [
                "::admin.block-types.index'",
                "view('admin.system.block-types'",
                "View::make('admin.system.block-types'",
                "response()->view('admin.system.block-types'",
            ],
            'packages/webblocks-cms/src/Http/Controllers/Admin/PageLayoutController.php' => [
                "::admin.page-layouts.index'",
                "view('admin.system.page-layouts'",
                "View::make('admin.system.page-layouts'",
                "response()->view('admin.system.page-layouts'",
            ],
            'packages/webblocks-cms/src/Http/Controllers/Admin/SlotTypeController.php' => [
                "::admin.slot-types.index'",
                "view('admin.system.slot-types'",
                "View::make('admin.system.slot-types'",
                "response()->view('admin.system.slot-types'",
            ],
        ];

        foreach ($expectations as $path => [$expectedView, $unexpectedView, $unexpectedMake, $unexpectedResponseView]) {
            $contents = (string) file_get_contents(base_path($path));

            $this->assertStringContainsString($expectedView, $contents, $path);
            $this->assertStringNotContainsString($unexpectedView, $contents, $path);
            $this->assertStringNotContainsString($unexpectedMake, $contents, $path);
            $this->assertStringNotContainsString($unexpectedResponseView, $contents, $path);
        }
    }

    #[Test]
    public function package_owned_system_admin_views_keep_package_layout_and_include_boundaries(): void
    {
        $views = [
            'packages/webblocks-cms/resources/views/admin/system/updates.blade.php',
            'packages/webblocks-cms/resources/views/admin/system/backups/index.blade.php',
            'packages/webblocks-cms/resources/views/admin/system/backups/show.blade.php',
            'packages/webblocks-cms/resources/views/admin/system/backups/upload.blade.php',
            'packages/webblocks-cms/resources/views/admin/system/search.blade.php',
            'packages/webblocks-cms/resources/views/admin/system/settings.blade.php',
            'packages/webblocks-cms/resources/views/admin/system/icons/index.blade.php',
            'packages/webblocks-cms/resources/views/admin/block-types/index.blade.php',
            'packages/webblocks-cms/resources/views/admin/page-layouts/index.blade.php',
            'packages/webblocks-cms/resources/views/admin/slot-types/index.blade.php',
        ];

        foreach ($views as $view) {
            $contents = (string) file_get_contents(base_path($view));

            $this->assertStringContainsString("@extends('webblocks-cms::layouts.admin'", $contents, $view);
            $this->assertStringNotContainsString("@extends('layouts.admin'", $contents, $view);
            $this->assertStringNotContainsString("@include('admin.", $contents, $view);
            $this->assertStringNotContainsString("@includeIf('admin.", $contents, $view);
            $this->assertStringNotContainsString('<x-admin.', $contents, $view);
            $this->assertStringNotContainsString('<x-auth-password-field', $contents, $view);
        }
    }

    #[Test]
    public function package_owned_site_transfer_admin_renderers_use_package_view_names(): void
    {
        $expectations = [
            'packages/webblocks-cms/src/Http/Controllers/Admin/SiteExportController.php' => [
                "view('webblocks-cms::admin.site-transfers.exports.index'",
                "view('webblocks-cms::admin.site-transfers.exports.show'",
            ],
            'packages/webblocks-cms/src/Http/Controllers/Admin/SiteImportController.php' => [
                "view('webblocks-cms::admin.site-transfers.imports.index'",
                "view('webblocks-cms::admin.site-transfers.imports.create'",
                "view('webblocks-cms::admin.site-transfers.imports.show'",
            ],
            'packages/webblocks-cms/src/Http/Controllers/Admin/SitePromotionController.php' => [
                "view('webblocks-cms::admin.sites.promote'",
            ],
        ];

        foreach ($expectations as $path => $expectedViews) {
            $contents = (string) file_get_contents(base_path($path));

            foreach ($expectedViews as $expectedView) {
                $this->assertStringContainsString($expectedView, $contents, $path);
            }

            foreach ([
                "view('admin/site-transfers",
                "view('admin.site-transfers",
                "view('admin/site-exports",
                "view('admin.site-exports",
                "view('admin/site-imports",
                "view('admin.site-imports",
                "view('admin/site-promotions",
                "view('admin.site-promotions",
            ] as $unexpectedView) {
                $this->assertStringNotContainsString($unexpectedView, $contents, $path);
            }
        }
    }

    #[Test]
    public function package_owned_site_transfer_admin_views_keep_package_layout_and_include_boundaries(): void
    {
        $views = [
            'packages/webblocks-cms/resources/views/admin/site-transfers/exports/index.blade.php',
            'packages/webblocks-cms/resources/views/admin/site-transfers/exports/show.blade.php',
            'packages/webblocks-cms/resources/views/admin/site-transfers/imports/index.blade.php',
            'packages/webblocks-cms/resources/views/admin/site-transfers/imports/create.blade.php',
            'packages/webblocks-cms/resources/views/admin/site-transfers/imports/show.blade.php',
            'packages/webblocks-cms/resources/views/admin/sites/promote.blade.php',
        ];

        foreach ($views as $view) {
            $contents = (string) file_get_contents(base_path($view));

            $this->assertStringContainsString("@extends('webblocks-cms::layouts.admin'", $contents, $view);
            $this->assertStringNotContainsString("@extends('layouts.admin'", $contents, $view);
            $this->assertStringNotContainsString("@include('admin.", $contents, $view);
            $this->assertStringNotContainsString("@include('admin/", $contents, $view);
            $this->assertStringNotContainsString("@includeIf('admin.", $contents, $view);
            $this->assertStringNotContainsString("@includeIf('admin/", $contents, $view);
            $this->assertStringNotContainsString('<x-admin.', $contents, $view);
            $this->assertStringNotContainsString('<x-auth-password-field', $contents, $view);
        }
    }

    #[Test]
    public function users_package_runtime_does_not_reference_root_admin_users_views(): void
    {
        $controller = (string) file_get_contents(base_path('packages/webblocks-cms/src/Http/Controllers/Admin/UserController.php'));
        $indexView = (string) file_get_contents(base_path('packages/webblocks-cms/resources/views/admin/users/index.blade.php'));
        $formView = (string) file_get_contents(base_path('packages/webblocks-cms/resources/views/admin/users/form.blade.php'));

        $this->assertStringNotContainsString("view('admin.users.", $controller);
        $this->assertStringContainsString("view('webblocks-cms::admin.users.index'", $controller);
        $this->assertStringContainsString("view('webblocks-cms::admin.users.form'", $controller);
        $this->assertStringNotContainsString("@include('admin.users.", $indexView);
        $this->assertStringNotContainsString("@include('admin.users.", $formView);
        $this->assertStringNotContainsString('<x-auth-password-field', $formView);
        $this->assertStringContainsString("@extends('webblocks-cms::layouts.admin'", $indexView);
        $this->assertStringContainsString("@extends('webblocks-cms::layouts.admin'", $formView);
    }
}
