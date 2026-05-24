<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsCloneableSite;
use Tests\TestCase;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\MediaFolder;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteExport;
use WebBlocks\Cms\Models\SiteImport;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteTransferDisk;

class SiteExportImportAdminTest extends TestCase
{
    use BuildsCloneableSite;
    use RefreshDatabase;

    #[Test]
    public function admin_export_action_creates_downloadable_package(): void
    {
        Storage::fake(SiteTransferDisk::DISK);
        Storage::fake('public');
        [$site, $heroAsset] = $this->seedCloneableSite(withFile: true);
        $user = User::factory()->superAdmin()->create();
        $folder = MediaFolder::query()->create([
            'name' => 'Branding',
            'slug' => 'branding',
        ]);

        $heroAsset->update(['folder_id' => $folder->id]);

        $response = $this->actingAs($user)->post(route('admin.site-transfers.exports.store'), [
            'site_id' => $site->id,
            'includes_media' => '1',
        ]);

        $siteExport = SiteExport::query()->latest()->first();

        $this->assertNotNull($siteExport);
        $response->assertRedirect(route('admin.site-transfers.exports.show', $siteExport));
        $this->assertSame('completed', $siteExport->status);
        $this->assertStringNotContainsString('/', (string) $siteExport->archive_path);
        $this->assertSame(SiteTransferDisk::DISK, $siteExport->archive_disk);
        Storage::disk(SiteTransferDisk::DISK)->assertExists($siteExport->archive_path);

        $download = $this->actingAs($user)->get(route('admin.site-transfers.exports.download', $siteExport));
        $download->assertOk();
        $download->assertDownload($siteExport->archive_name);
    }

    #[Test]
    public function export_form_checks_include_media_by_default(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.site-transfers.exports.index'));

        $response->assertOk();
        $response->assertSee('name="includes_media" value="1" checked', false);
    }

    #[Test]
    public function export_form_keeps_include_media_checked_after_validation_redirect_when_not_provided(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)
            ->from(route('admin.site-transfers.exports.index'))
            ->post(route('admin.site-transfers.exports.store'), []);

        $response->assertRedirect(route('admin.site-transfers.exports.index'));

        $page = $this->actingAs($user)->get(route('admin.site-transfers.exports.index'));

        $page->assertOk();
        $page->assertSee('name="includes_media" value="1" checked', false);
    }

    #[Test]
    public function sites_index_shows_export_action_for_super_admin_and_modal_context(): void
    {
        [$site] = $this->seedCloneableSite();
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.sites.index', [
            'modal' => 'export-site',
            'export_site' => $site->id,
        ]));

        $response->assertOk();
        $response->assertSee('Export Site');
        $response->assertSee($site->name);
        $response->assertSee($site->handle);
        $response->assertSee('Include media files');
        $response->assertSee('aria-controls="siteIndexExportModal"', false);
        $response->assertSee('modal=export-site');
        $response->assertSee('export_site='.$site->id);
        $response->assertSee('action="'.route('admin.sites.export', $site).'"', false);
        $response->assertSeeInOrder(['Export Site', 'Cancel'], false);
        $response->assertSee('<div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap" data-admin-form-actions>', false);
        $response->assertSee('data-admin-form-actions-main', false);
        $response->assertDontSee('wb-justify-end', false);
    }

    #[Test]
    public function sites_index_uses_manage_dropdown_and_simplified_header_actions(): void
    {
        [$site] = $this->seedCloneableSite();
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.sites.index'));

        $response->assertOk();
        $response->assertDontSee('>Clone Site<', false);
        $response->assertDontSee('>Promote<', false);
        $response->assertSee('>Add Site<', false);
        $response->assertSee('<th>Actions</th>', false);
        $response->assertSee('Manage', false);
        $response->assertSee('Manage '.$site->name, false);
        $response->assertSee('View details');
        $response->assertSee('Edit site');
        $response->assertSee('Manage domains');
        $response->assertSee('Clone site');
        $response->assertSee('Export site');
        $response->assertSee('Promote to this site');
        $response->assertSee('Delete site');
        $response->assertSee('details_site='.$site->id);
        $response->assertSee('modal=site-details');
        $response->assertSee(route('admin.sites.clone.prefill', $site), false);
        $response->assertSee(route('admin.sites.promote', ['target_site_id' => $site->id]), false);
        $response->assertSee(route('admin.sites.delete', $site), false);
        $response->assertDontSee('wb-action-group', false);
    }

    #[Test]
    public function sites_index_does_not_show_export_action_for_non_super_admin(): void
    {
        [$site] = $this->seedCloneableSite();
        $user = User::factory()->siteAdmin()->create();
        $user->sites()->sync([$site->id]);

        $response = $this->actingAs($user)->post(route('admin.sites.export', $site), [
            'includes_media' => '1',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function sites_index_export_creates_package_and_redirects_back_with_status(): void
    {
        Storage::fake(SiteTransferDisk::DISK);
        Storage::fake('public');
        [$site, $heroAsset] = $this->seedCloneableSite(withFile: true);
        $user = User::factory()->superAdmin()->create();
        $folder = MediaFolder::query()->create([
            'name' => 'Branding',
            'slug' => 'branding',
        ]);

        $heroAsset->update(['folder_id' => $folder->id]);

        $response = $this->actingAs($user)
            ->from(route('admin.sites.index', ['modal' => 'export-site', 'export_site' => $site->id]))
            ->post(route('admin.sites.export', $site), [
                'includes_media' => '1',
            ]);

        $siteExport = SiteExport::query()->latest()->first();

        $this->assertNotNull($siteExport);
        $this->assertSame($site->id, $siteExport->site_id);
        $this->assertTrue($siteExport->includes_media);
        $response->assertRedirect(route('admin.sites.index'));
        $response->assertSessionHas('status', 'Export package created for "'.$site->name.'".');
    }

    #[Test]
    public function unauthorized_users_cannot_export_from_sites_index(): void
    {
        [$site] = $this->seedCloneableSite();
        $user = User::factory()->siteAdmin()->create();
        $user->sites()->sync([$site->id]);

        $response = $this->actingAs($user)->post(route('admin.sites.export', $site), [
            'includes_media' => '1',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('site_exports', 0);
    }

    #[Test]
    public function admin_export_action_uses_clean_filename_without_random_prefix(): void
    {
        Storage::fake(SiteTransferDisk::DISK);
        [$site] = $this->seedCloneableSite();
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->post(route('admin.site-transfers.exports.store'), [
            'site_id' => $site->id,
        ]);

        $siteExport = SiteExport::query()->latest()->first();

        $this->assertNotNull($siteExport);
        $response->assertRedirect(route('admin.site-transfers.exports.show', $siteExport));
        $this->assertSame($siteExport->archive_name, $siteExport->archive_path);
        $this->assertStringStartsWith('webblocks-cms-site-export-', (string) $siteExport->archive_path);
        $this->assertFalse((bool) preg_match('/^[a-z0-9]{8}-/', (string) $siteExport->archive_path));
        $this->assertTrue(Str::endsWith((string) $siteExport->archive_path, '.zip'));
    }

    #[Test]
    public function admin_import_upload_validates_and_completes(): void
    {
        Storage::fake(SiteTransferDisk::DISK);
        Storage::fake('public');
        [$site, $heroAsset] = $this->seedCloneableSite(withFile: true);
        $user = User::factory()->superAdmin()->create();
        $folder = MediaFolder::query()->create([
            'name' => 'Branding',
            'slug' => 'branding',
        ]);

        $heroAsset->update(['folder_id' => $folder->id]);

        $this->actingAs($user)->post(route('admin.site-transfers.exports.store'), [
            'site_id' => $site->id,
            'includes_media' => '1',
        ]);

        $siteExport = SiteExport::query()->latest()->firstOrFail();
        $uploadResponse = $this->actingAs($user)->post(route('admin.site-transfers.imports.inspect'), [
            'archive' => new UploadedFile(Storage::disk(SiteTransferDisk::DISK)->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true),
        ]);

        $siteImport = SiteImport::query()->latest()->firstOrFail();
        $uploadResponse->assertRedirect(route('admin.site-transfers.imports.show', $siteImport));

        $runResponse = $this->actingAs($user)->post(route('admin.site-transfers.imports.run', $siteImport), [
            'site_name' => 'Imported Site',
            'site_handle' => 'imported-site',
            'site_domain' => '',
        ]);

        $runResponse->assertRedirect(route('admin.site-transfers.imports.show', $siteImport));
        $this->assertDatabaseHas('sites', ['handle' => 'imported-site']);
        $this->assertGreaterThanOrEqual(2, MediaFolder::query()->where('slug', 'branding')->count());
    }

    #[Test]
    public function export_and_import_bootstrap_site_transfer_disk_when_consumer_config_is_missing(): void
    {
        $originalDisks = config('filesystems.disks');
        $user = User::factory()->superAdmin()->create();
        [$site] = $this->seedCloneableSite();

        config()->set('filesystems.disks', collect($originalDisks)->except(SiteTransferDisk::DISK)->all());
        Storage::forgetDisk(SiteTransferDisk::DISK);

        $exportResponse = $this->actingAs($user)->post(route('admin.site-transfers.exports.store'), [
            'site_id' => $site->id,
        ]);

        $siteExport = SiteExport::query()->latest()->firstOrFail();

        $exportResponse->assertRedirect(route('admin.site-transfers.exports.show', $siteExport));
        $this->assertSame(SiteTransferDisk::DISK, $siteExport->archive_disk);
        $this->assertSame(storage_path('app/site-transfers'), config('filesystems.disks.'.SiteTransferDisk::DISK.'.root'));
        $exportArchivePath = Storage::disk(SiteTransferDisk::DISK)->path($siteExport->archive_path);
        $this->assertFileExists($exportArchivePath);

        config()->set('filesystems.disks', collect($originalDisks)->except(SiteTransferDisk::DISK)->all());
        Storage::forgetDisk(SiteTransferDisk::DISK);

        $importResponse = $this->actingAs($user)->post(route('admin.site-transfers.imports.inspect'), [
            'archive' => new UploadedFile($exportArchivePath, $siteExport->archive_name, 'application/zip', null, true),
        ]);

        $siteImport = SiteImport::query()->latest()->firstOrFail();

        $importResponse->assertRedirect(route('admin.site-transfers.imports.show', $siteImport));
        $this->assertSame(SiteTransferDisk::DISK, $siteImport->archive_disk);
        Storage::disk(SiteTransferDisk::DISK)->delete($siteExport->archive_path);
        Storage::disk(SiteTransferDisk::DISK)->delete($siteImport->archive_path);
        config()->set('filesystems.disks', $originalDisks);
        Storage::forgetDisk(SiteTransferDisk::DISK);
    }

    #[Test]
    public function import_upload_reports_controlled_error_when_site_transfer_storage_is_not_writable(): void
    {
        $rootCollision = storage_path('framework/testing/site-transfer-root-collision-'.Str::uuid());
        $user = User::factory()->superAdmin()->create();

        File::ensureDirectoryExists(dirname($rootCollision));
        File::put($rootCollision, 'not a directory');
        config()->set('filesystems.disks.'.SiteTransferDisk::DISK, [
            'driver' => 'local',
            'root' => $rootCollision,
            'throw' => false,
        ]);

        try {
            $response = $this->actingAs($user)->post(route('admin.site-transfers.imports.inspect'), [
                'archive' => UploadedFile::fake()->create('package.zip', 1, 'application/zip'),
            ]);

            $response->assertRedirect(route('admin.site-transfers.imports.create'));
            $response->assertSessionHasErrors([
                'site_import' => 'Site transfer storage is not ready. Check the site-transfers filesystem disk and make sure storage/app/site-transfers is writable.',
            ]);
            $this->assertStringNotContainsString('configured driver', (string) session('errors')->first('site_import'));
        } finally {
            File::delete($rootCollision);
        }
    }

    #[Test]
    public function combined_transfer_screen_renders_exports_and_imports_sections_and_actions(): void
    {
        $user = User::factory()->superAdmin()->create();

        $exportSite = Site::query()->create([
            'name' => 'Export Source',
            'handle' => 'export-source',
            'domain' => 'export-source.test',
        ]);

        $importTarget = Site::query()->create([
            'name' => 'Import Target',
            'handle' => 'import-target',
            'domain' => 'import-target.test',
        ]);

        $siteExport = SiteExport::query()->create([
            'site_id' => $exportSite->id,
            'user_id' => $user->id,
            'status' => SiteExport::STATUS_COMPLETED,
            'includes_media' => true,
            'archive_disk' => SiteTransferDisk::DISK,
            'archive_path' => 'example-export.zip',
            'archive_name' => 'example-export.zip',
            'archive_size_bytes' => 2048,
        ]);

        $siteImport = SiteImport::query()->create([
            'user_id' => $user->id,
            'status' => SiteImport::STATUS_COMPLETED,
            'source_archive_name' => 'example-import.zip',
            'archive_disk' => SiteTransferDisk::DISK,
            'archive_path' => 'example-import.zip',
            'target_site_id' => $importTarget->id,
            'imported_site_handle' => $importTarget->handle,
        ]);

        $response = $this->actingAs($user)->get(route('admin.site-transfers.exports.index'));

        $response->assertOk();
        $response->assertSee('Site Exports');
        $response->assertSee('Site Imports');
        $response->assertSee('Run Export');
        $response->assertSee('Run Import');
        $response->assertDontSee('Back to Imports');
        $response->assertDontSee('href="'.route('admin.site-transfers.imports.index').'" class="wb-btn wb-btn-secondary">Imports</a>', false);
        $response->assertSee($exportSite->name);
        $response->assertSee($siteImport->source_archive_name);
        $response->assertSee(route('admin.site-transfers.exports.show', $siteExport), false);
        $response->assertSee(route('admin.site-transfers.exports.download', $siteExport), false);
        $response->assertSee(route('admin.site-transfers.exports.destroy', $siteExport), false);
        $response->assertSee(route('admin.site-transfers.imports.show', $siteImport), false);
        $response->assertSee(route('admin.site-transfers.imports.destroy', $siteImport), false);
    }

    #[Test]
    public function imports_index_renders_package_owned_combined_transfer_pointer(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.site-transfers.imports.index'));

        $response->assertOk();
        $response->assertSee('Export / Import');
        $response->assertSee(route('admin.site-transfers.exports.index'), false);
    }

    #[Test]
    public function imported_site_can_be_saved_after_import_when_only_domain_changes(): void
    {
        Storage::fake(SiteTransferDisk::DISK);
        Storage::fake('public');
        [$site] = $this->seedCloneableSite(withFile: true);
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)->post(route('admin.site-transfers.exports.store'), [
            'site_id' => $site->id,
            'includes_media' => '1',
        ]);

        $siteExport = SiteExport::query()->latest()->firstOrFail();
        $this->actingAs($user)->post(route('admin.site-transfers.imports.inspect'), [
            'archive' => new UploadedFile(Storage::disk(SiteTransferDisk::DISK)->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true),
        ]);

        $siteImport = SiteImport::query()->latest()->firstOrFail();
        $this->actingAs($user)->post(route('admin.site-transfers.imports.run', $siteImport), [
            'site_name' => 'Imported Site',
            'site_handle' => 'imported-site',
            'site_domain' => '',
        ]);

        $importedSite = $siteImport->fresh()->targetSite;
        $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();

        $updateResponse = $this->actingAs($user)->put(route('admin.sites.update', $importedSite), [
            'name' => $importedSite->name,
            'handle' => $importedSite->handle,
            'domain' => 'imported.example.test',
            'is_primary' => 0,
        ]);

        $updateResponse->assertRedirect(route('admin.sites.edit', ['site' => $importedSite, 'tab' => 'site']));
        $this->assertSame('imported.example.test', $importedSite->fresh()->domain);
        $this->assertTrue($importedSite->fresh()->hasEnabledLocale($defaultLocale));
    }

    #[Test]
    public function site_transfer_admin_routes_require_authentication(): void
    {
        $response = $this->get(route('admin.site-transfers.exports.index'));

        $response->assertRedirect(route('login'));
    }
}
