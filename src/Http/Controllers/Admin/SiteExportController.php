<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use WebBlocks\Cms\Http\Requests\Admin\BulkDeleteSiteExportsRequest;
use WebBlocks\Cms\Http\Requests\Admin\SiteExportRequest;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteExport;
use WebBlocks\Cms\Models\SiteImport;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteExportBulkDeleter;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteExportManager;

class SiteExportController extends Controller
{
  public function __construct(
    private readonly SiteExportManager $siteExportManager,
    private readonly SiteExportBulkDeleter $siteExportBulkDeleter,
  ) {}

  public function index(): View
  {
    $exports = SiteExport::query()
      ->with(['site', 'user'])
      ->latest()
      ->paginate(AdminPagination::perPage(), ['*'], 'exports_page')
      ->withQueryString();

    $imports = SiteImport::query()
      ->with(['targetSite', 'user'])
      ->latest()
      ->paginate(AdminPagination::perPage(), ['*'], 'imports_page')
      ->withQueryString();

    return view('webblocks-cms::admin.site-transfers.exports.index', [
      'exports' => $exports,
      'imports' => $imports,
      'sites' => Site::query()->primaryFirst()->orderBy('name')->get(),
      'totalExportsCount' => SiteExport::query()->count(),
      'filteredExportsCount' => $exports->total(),
      'totalImportsCount' => SiteImport::query()->count(),
      'filteredImportsCount' => $imports->total(),
    ]);
  }

  public function store(SiteExportRequest $request): RedirectResponse
  {
    try {
      $site = Site::query()->findOrFail($request->integer('site_id'));
      $siteExport = $this->createExport($site, $request);

      return redirect()
        ->route('admin.site-transfers.exports.show', $siteExport)
        ->with('status', 'Site export completed successfully.');
    } catch (Throwable $throwable) {
      return redirect()
        ->route('admin.site-transfers.exports.index')
        ->withErrors(['site_export' => $throwable->getMessage()]);
    }
  }

  public function storeFromSitesIndex(SiteExportRequest $request, Site $site): RedirectResponse
  {
    try {
      $this->createExport($site, $request);

      return redirect()
        ->route('admin.sites.index')
        ->with('status', 'Export package created for "'.$site->name.'".');
    } catch (Throwable $throwable) {
      return redirect()
        ->route('admin.sites.index', [
          'modal' => 'export-site',
          'export_site' => $site->id,
        ])
        ->withInput()
        ->withErrors(['site_export' => $throwable->getMessage()]);
    }
  }

  public function show(SiteExport $siteExport): View
  {
    return view('webblocks-cms::admin.site-transfers.exports.show', [
      'siteExport' => $siteExport->load(['site', 'user']),
    ]);
  }

  public function download(SiteExport $siteExport): BinaryFileResponse
  {
    return $this->siteExportManager->downloadResponse($siteExport);
  }

  public function destroy(SiteExport $siteExport): RedirectResponse
  {
    $this->siteExportManager->delete($siteExport);

    return redirect()
      ->route('admin.site-transfers.exports.index')
      ->with('status', 'Site export record deleted.');
  }

  public function bulkDestroy(BulkDeleteSiteExportsRequest $request): RedirectResponse
  {
    $result = $this->siteExportBulkDeleter->deleteSelected($request->validated('site_export_ids'));

    $redirect = redirect()
      ->route('admin.site-transfers.exports.index')
      ->with($result->deletedCount() > 0 ? 'status' : 'bulk_status', $result->message());

    if ($result->hasFailures()) {
      $redirect->withErrors(['site_exports' => implode(' ', $result->failureMessages())]);
    }

    return $redirect;
  }

  private function createExport(Site $site, SiteExportRequest $request): SiteExport
  {
    return $this->siteExportManager->export($site, $request->boolean('includes_media'), $request->user()?->id);
  }
}
