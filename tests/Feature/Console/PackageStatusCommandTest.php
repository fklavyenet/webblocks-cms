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
            ->expectsOutputToContain('Package route Composer readiness: yes (provider discovery plus guarded route files)')
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
            ->expectsOutputToContain('Root route compatibility state: root routes remain authoritative outside reserved package paths.')
            ->expectsOutputToContain('Package resources/views path present: yes')
            ->expectsOutputToContain('Package view files status: package files present (admin/runtime-status.blade.php, diagnostics/package-status.blade.php, public/runtime-status.blade.php)')
            ->expectsOutputToContain('Package view Composer readiness: yes (provider discovery plus package view namespace)')
            ->expectsOutputToContain('Package admin slice view exists: yes (webblocks-cms::admin.runtime-status)')
            ->expectsOutputToContain('Package public slice view exists: yes (webblocks-cms::public.runtime-status)')
            ->expectsOutputToContain('Root view compatibility state: root views remain authoritative outside the package namespace.')
            ->expectsOutputToContain('Package database/migrations path present: yes')
            ->expectsOutputToContain('Package migration boundary status: reserved boundary only')
            ->expectsOutputToContain('Package migration files status: reserved only')
            ->expectsOutputToContain('Package migration loading guard enabled: no (webblocks-cms.boundaries.load_migrations)')
            ->expectsOutputToContain('Package migrations loaded in active runtime: no')
            ->expectsOutputToContain('Legacy root migration compatibility state: yes (root database/migrations remains authoritative).')
            ->expectsOutputToContain('Future package migration Composer readiness: reserved boundary only (no schema-changing package migrations are active yet).')
            ->expectsOutputToContain('Package database/seeders path present: yes')
            ->expectsOutputToContain('Package seeder boundary status: pilot files present')
            ->expectsOutputToContain('Package seeder files status: package files present (CoreCatalogSeeder.php, IconCatalogSeeder.php, LayoutTypeSeeder.php, PageTypeSeeder.php, SlotTypeSeeder.php)')
            ->expectsOutputToContain('Package catalog seeders present: yes (CoreCatalogSeeder, IconCatalogSeeder, PageTypeSeeder, LayoutTypeSeeder, SlotTypeSeeder)')
            ->expectsOutputToContain('Root catalog seeder compatibility wrappers present: yes')
            ->expectsOutputToContain('Package public path present: yes')
            ->expectsOutputToContain('Package public asset boundary status: reserved boundary only')
            ->expectsOutputToContain('Package public assets status: reserved only')
            ->expectsOutputToContain('Package public asset publish readiness: no (tag webblocks-cms-assets remains inert until real package assets exist)')
            ->expectsOutputToContain('Legacy root public asset compatibility state: yes (root public/cms and install-owned public/site remain authoritative).')
            ->expectsOutputToContain('Future package public asset Composer readiness: reserved boundary only (current WebBlocks UI CDN pinning and root asset flow stay unchanged).')
            ->expectsOutputToContain('Package stubs path present: yes')
            ->expectsOutputToContain('Package stub boundary status: reserved boundary only')
            ->expectsOutputToContain('Package stubs status: reserved only')
            ->expectsOutputToContain('Package stub publish readiness: no (tag webblocks-cms-stubs remains inert until real package stubs exist)')
            ->expectsOutputToContain('Starter stub readiness: reserved only (no publishable starter stubs are intentionally shipped yet).')
            ->expectsOutputToContain('Package service provider loaded: yes')
            ->expectsOutputToContain('Package view namespace registered: yes (webblocks-cms)')
            ->expectsOutputToContain('Package diagnostic view exists: yes (webblocks-cms::diagnostics.package-status)')
            ->expectsOutputToContain('Package low-risk runtime support moves present: yes (AdminPagination, BlockTypeIndexState, MediaIndexState, PageIndexState)')
            ->expectsOutputToContain('Root runtime support compatibility wrappers present: yes')
            ->expectsOutputToContain('Package icon runtime moves present: yes (IconCatalogController, IconCatalogItemUpdateRequest, IconCatalog, WebBlocksIconManifestSyncer)')
            ->expectsOutputToContain('Root icon runtime compatibility wrappers present: yes')
            ->expectsOutputToContain('Package diagnostic view render check: not run (use --view-check)')
            ->expectsOutputToContain('Package Composer package name present: yes (fklavyenet/webblocks-cms)')
            ->expectsOutputToContain('Package Composer provider discovery present: yes (WebBlocks\\Cms\\WebBlocksCmsServiceProvider)')
            ->expectsOutputToContain('Package Composer seeder autoload present: yes (WebBlocks\\Cms\\Database\\Seeders\\)')
            ->expectsOutputToContain('Root Composer development path dependency present: yes (fklavyenet/webblocks-cms)')
            ->expectsOutputToContain('Root Composer path repository present: yes (packages/webblocks-cms)')
            ->expectsOutputToContain('Target Composer install flow: composer require fklavyenet/webblocks-cms (future starter or package-consumer target only; current root install flow remains authoritative).')
            ->expectsOutputToContain('Target Composer update flow: composer update fklavyenet/webblocks-cms followed by migrations, catalog sync, block-types:sync-core, cache clear, asset publish or sync when needed, package diagnostics, and installed-version sync when release state is real.')
            ->expectsOutputToContain('Starter foundation readiness: partial (package metadata, provider discovery, path-repository development wiring, and documented target install or update flow are present; fklavyenet/webblocks-cms-starter is intentionally not created yet).')
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
            ->expectsOutputToContain('Package catalog seeders present: yes (CoreCatalogSeeder, IconCatalogSeeder, PageTypeSeeder, LayoutTypeSeeder, SlotTypeSeeder)')
            ->expectsOutputToContain('Package low-risk runtime support moves present: yes (AdminPagination, BlockTypeIndexState, MediaIndexState, PageIndexState)')
            ->expectsOutputToContain('Package icon runtime moves present: yes (IconCatalogController, IconCatalogItemUpdateRequest, IconCatalog, WebBlocksIconManifestSyncer)')
            ->expectsOutputToContain('Starter foundation readiness: partial (package metadata, provider discovery, path-repository development wiring, and documented target install or update flow are present; fklavyenet/webblocks-cms-starter is intentionally not created yet).')
            ->expectsOutputToContain('Package diagnostic view render check: success')
            ->expectsOutputToContain('This command performs no publishing, migrations, cache clearing, file writes, database writes, install-state changes, or update-state changes.')
            ->assertExitCode(0);
    }
}
