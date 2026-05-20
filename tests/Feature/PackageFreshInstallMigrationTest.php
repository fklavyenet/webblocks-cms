<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PackageFreshInstallMigrationTest extends TestCase
{
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

    #[Test]
    public function shared_slot_revision_restored_from_foreign_key_uses_an_explicit_short_name(): void
    {
        $migration = (string) file_get_contents(base_path('packages/webblocks-cms/database/migrations/fresh/2026_05_20_120000_create_webblocks_cms_fresh_install_schema.php'));

        $this->assertStringContainsString("foreign('restored_from_shared_slot_revision_id', 'ss_revisions_restored_from_fk')", $migration);
        $this->assertStringNotContainsString('shared_slot_revisions_restored_from_shared_slot_revision_id_foreign', $migration);
    }

    #[Test]
    public function package_owned_views_do_not_reference_root_admin_components_or_includes(): void
    {
        $views = $this->packageFiles(base_path('packages/webblocks-cms/resources/views'), 'php');

        foreach ($views as $view) {
            $contents = (string) file_get_contents($view);

            $this->assertStringNotContainsString('<x-admin.', $contents, $view);
            $this->assertStringNotContainsString('@include(\'admin.', $contents, $view);
            $this->assertStringNotContainsString('@includeIf(\'admin.', $contents, $view);
            $this->assertStringNotContainsString('@component(\'admin.', $contents, $view);
            $this->assertStringNotContainsString('@extends(\'layouts.admin\'', $contents, $view);
            $this->assertStringNotContainsString('<x-auth-password-field', $contents, $view);
        }
    }

    #[Test]
    public function package_owned_admin_php_renderers_do_not_use_root_admin_view_names(): void
    {
        $phpFiles = $this->packageFiles(base_path('packages/webblocks-cms/src'), 'php');

        foreach ($phpFiles as $file) {
            $contents = (string) file_get_contents($file);

            $this->assertStringNotContainsString("view('admin.", $contents, $file);
            $this->assertStringNotContainsString("View::make('admin.", $contents, $file);
            $this->assertStringNotContainsString("response()->view('admin.", $contents, $file);
        }
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
