<?php

namespace Tests\Feature\Admin;

use App\Models\Locale;
use App\Models\SiteExport;
use App\Models\SiteImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsCloneableSite;
use Tests\TestCase;

class SiteExportImportAdminTest extends TestCase
{
    use BuildsCloneableSite;
    use RefreshDatabase;

    #[Test]
    public function admin_export_action_creates_downloadable_package(): void
    {
        Storage::fake('site-exports');
        Storage::fake('public');
        [$site] = $this->seedCloneableSite(withFile: true);
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->post(route('admin.site-transfers.exports.store'), [
            'site_id' => $site->id,
            'includes_media' => '1',
        ]);

        $siteExport = SiteExport::query()->latest()->first();

        $response->assertRedirect(route('admin.site-transfers.exports.show', $siteExport));
        $this->assertNotNull($siteExport);
        $this->assertSame('completed', $siteExport->status);
        $this->assertStringNotContainsString('/', (string) $siteExport->archive_path);
        Storage::disk('site-exports')->assertExists($siteExport->archive_path);

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
    public function admin_export_action_uses_clean_filename_without_random_prefix(): void
    {
        Storage::fake('site-exports');
        [$site] = $this->seedCloneableSite();
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->post(route('admin.site-transfers.exports.store'), [
            'site_id' => $site->id,
        ]);

        $siteExport = SiteExport::query()->latest()->first();

        $response->assertRedirect(route('admin.site-transfers.exports.show', $siteExport));
        $this->assertNotNull($siteExport);
        $this->assertSame($siteExport->archive_name, $siteExport->archive_path);
        $this->assertStringStartsWith('webblocks-cms-site-export-', (string) $siteExport->archive_path);
        $this->assertFalse((bool) preg_match('/^[a-z0-9]{8}-/', (string) $siteExport->archive_path));
        $this->assertTrue(Str::endsWith((string) $siteExport->archive_path, '.zip'));
    }

    #[Test]
    public function admin_import_upload_validates_and_completes(): void
    {
        Storage::fake('site-exports');
        Storage::fake('site-transfers');
        Storage::fake('public');
        [$site] = $this->seedCloneableSite(withFile: true);
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)->post(route('admin.site-transfers.exports.store'), [
            'site_id' => $site->id,
            'includes_media' => '1',
        ]);

        $siteExport = SiteExport::query()->latest()->firstOrFail();
        $uploadResponse = $this->actingAs($user)->post(route('admin.site-transfers.imports.inspect'), [
            'archive' => new UploadedFile(Storage::disk('site-exports')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true),
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
    }

    #[Test]
    public function imported_site_can_be_saved_after_import_when_only_domain_changes(): void
    {
        Storage::fake('site-exports');
        Storage::fake('site-transfers');
        Storage::fake('public');
        [$site] = $this->seedCloneableSite(withFile: true);
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)->post(route('admin.site-transfers.exports.store'), [
            'site_id' => $site->id,
            'includes_media' => '1',
        ]);

        $siteExport = SiteExport::query()->latest()->firstOrFail();
        $this->actingAs($user)->post(route('admin.site-transfers.imports.inspect'), [
            'archive' => new UploadedFile(Storage::disk('site-exports')->path($siteExport->archive_path), $siteExport->archive_name, 'application/zip', null, true),
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

        $updateResponse->assertRedirect(route('admin.sites.edit', $importedSite));
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
