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
