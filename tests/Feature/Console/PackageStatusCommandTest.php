<?php

namespace Tests\Feature\Console;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PackageStatusCommandTest extends TestCase
{
    #[Test]
    public function package_status_command_is_registered_and_reports_package_resource_boundary_state_read_only(): void
    {
        $this->artisan('list')
            ->expectsOutputToContain('webblocks:package-status')
            ->assertExitCode(0);

        $this->artisan('webblocks:package-status')
            ->expectsOutputToContain('Package: fklavyenet/webblocks-cms')
            ->expectsOutputToContain('Mode: read-only diagnostic only')
            ->expectsOutputToContain('Package resource boundary status')
            ->expectsOutputToContain('Package base path:')
            ->expectsOutputToContain('Package src path present: yes')
            ->expectsOutputToContain('Package config path present: yes')
            ->expectsOutputToContain('Package config files present: cms.php, contact.php, demo_media.php, webblocks-updates.php')
            ->expectsOutputToContain('Expected package config defaults:')
            ->expectsOutputToContain('- cms.php: package default=yes, root override=yes')
            ->expectsOutputToContain('- contact.php: package default=yes, root override=yes')
            ->expectsOutputToContain('- demo_media.php: package default=yes, root override=yes')
            ->expectsOutputToContain('- webblocks-updates.php: package default=yes, root override=yes')
            ->expectsOutputToContain('Package routes path present: yes')
            ->expectsOutputToContain('Package route files status: package files present (admin.php, diagnostics.php, public.php)')
            ->expectsOutputToContain('Expected package diagnostic route file exists: yes (diagnostics.php)')
            ->expectsOutputToContain('Package diagnostic route loading guard enabled: no (webblocks-cms.diagnostics.load_routes)')
            ->expectsOutputToContain('Package diagnostic route loaded in active runtime: no (webblocks-cms.diagnostics.package-status at /_webblocks-cms/diagnostics/package-status)')
            ->expectsOutputToContain('Expected package admin route file exists: yes (admin.php)')
            ->expectsOutputToContain('Package admin slice loading guard enabled: no (webblocks-cms.admin.load_routes)')
            ->expectsOutputToContain('Package admin slice loaded in active runtime: no (admin.webblocks-cms.runtime-status at /admin/_webblocks-cms/runtime-status)')
            ->expectsOutputToContain('Expected package public route file exists: yes (public.php)')
            ->expectsOutputToContain('Package public slice loading guard enabled: no (webblocks-cms.public.load_routes)')
            ->expectsOutputToContain('Package public slice loaded in active runtime: no (webblocks-cms.public.runtime-status at /_webblocks-cms/runtime-status)')
            ->expectsOutputToContain('Active runtime route loading remains root-authoritative: yes')
            ->expectsOutputToContain('Package resources/views path present: yes')
            ->expectsOutputToContain('Package view files status: package files present (admin/runtime-status.blade.php, diagnostics/package-status.blade.php, public/runtime-status.blade.php)')
            ->expectsOutputToContain('Package admin slice view exists: yes (webblocks-cms::admin.runtime-status)')
            ->expectsOutputToContain('Package public slice view exists: yes (webblocks-cms::public.runtime-status)')
            ->expectsOutputToContain('Package database/migrations path present: yes')
            ->expectsOutputToContain('Package migration boundary status: reserved boundary only')
            ->expectsOutputToContain('Package migration files status: reserved only')
            ->expectsOutputToContain('Package migration loading guard enabled: no (webblocks-cms.boundaries.load_migrations)')
            ->expectsOutputToContain('Package migrations loaded in active runtime: no')
            ->expectsOutputToContain('Package database/seeders path present: yes')
            ->expectsOutputToContain('Package seeder boundary status: pilot files present')
            ->expectsOutputToContain('Package seeder files status: package files present (IconCatalogSeeder.php, LayoutTypeSeeder.php, PageTypeSeeder.php, SlotTypeSeeder.php)')
            ->expectsOutputToContain('Package low-risk catalog seeders present: yes (IconCatalogSeeder, PageTypeSeeder, LayoutTypeSeeder, SlotTypeSeeder)')
            ->expectsOutputToContain('Root catalog seeder compatibility wrappers present: yes')
            ->expectsOutputToContain('Package public path present: yes')
            ->expectsOutputToContain('Package public asset boundary status: reserved boundary only')
            ->expectsOutputToContain('Package public assets status: reserved only')
            ->expectsOutputToContain('Package public asset publish readiness: no (tag webblocks-cms-assets remains inert until real package assets exist)')
            ->expectsOutputToContain('Package stubs path present: yes')
            ->expectsOutputToContain('Package stub boundary status: reserved boundary only')
            ->expectsOutputToContain('Package stubs status: reserved only')
            ->expectsOutputToContain('Package stub publish readiness: no (tag webblocks-cms-stubs remains inert until real package stubs exist)')
            ->expectsOutputToContain('Package service provider loaded: yes')
            ->expectsOutputToContain('Package view namespace registered: yes (webblocks-cms)')
            ->expectsOutputToContain('Package diagnostic view exists: yes (webblocks-cms::diagnostics.package-status)')
            ->expectsOutputToContain('Package low-risk runtime support moves present: yes (AdminPagination, BlockTypeIndexState, MediaIndexState, PageIndexState)')
            ->expectsOutputToContain('Root runtime support compatibility wrappers present: yes')
            ->expectsOutputToContain('Package diagnostic view render check: not run (use --view-check)')
            ->expectsOutputToContain('Package Composer seeder autoload present: yes (WebBlocks\\Cms\\Database\\Seeders\\)')
            ->expectsOutputToContain('Root Composer path repository present: yes (packages/webblocks-cms)')
            ->expectsOutputToContain('Composer-managed update target note: future Composer-managed package updates remain the target boundary, while current root Composer and runtime update flow stay authoritative.')
            ->expectsOutputToContain('Transition note: root runtime remains authoritative unless a resource has been intentionally moved and wired.')
            ->expectsOutputToContain('This command performs no publishing, migrations, cache clearing, file writes, database writes, install-state changes, or update-state changes.')
            ->assertExitCode(0);
    }

    #[Test]
    public function package_status_command_can_optionally_render_the_package_diagnostic_view_through_the_namespace(): void
    {
        $this->artisan('webblocks:package-status --view-check')
            ->expectsOutputToContain('Package diagnostic route loaded in active runtime: no (webblocks-cms.diagnostics.package-status at /_webblocks-cms/diagnostics/package-status)')
            ->expectsOutputToContain('Package admin slice loaded in active runtime: no (admin.webblocks-cms.runtime-status at /admin/_webblocks-cms/runtime-status)')
            ->expectsOutputToContain('Package public slice loaded in active runtime: no (webblocks-cms.public.runtime-status at /_webblocks-cms/runtime-status)')
            ->expectsOutputToContain('Package diagnostic view exists: yes (webblocks-cms::diagnostics.package-status)')
            ->expectsOutputToContain('Package low-risk catalog seeders present: yes (IconCatalogSeeder, PageTypeSeeder, LayoutTypeSeeder, SlotTypeSeeder)')
            ->expectsOutputToContain('Package low-risk runtime support moves present: yes (AdminPagination, BlockTypeIndexState, MediaIndexState, PageIndexState)')
            ->expectsOutputToContain('Package diagnostic view render check: success')
            ->expectsOutputToContain('This command performs no publishing, migrations, cache clearing, file writes, database writes, install-state changes, or update-state changes.')
            ->assertExitCode(0);
    }
}
