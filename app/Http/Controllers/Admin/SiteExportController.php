<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteExportRequest;
use App\Models\Site;
use App\Models\SiteExport;
use App\Models\SiteImport;
use App\Support\Sites\ExportImport\SiteExportManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SiteExportController extends Controller
{
    public function __construct(
        private readonly SiteExportManager $siteExportManager,
    ) {}

    public function index(): View
    {
        return view('admin/site-transfers/exports/index', [
            'exports' => SiteExport::query()
                ->with(['site', 'user'])
                ->latest()
                ->paginate(20, ['*'], 'exports_page')
                ->withQueryString(),
            'imports' => SiteImport::query()
                ->with(['targetSite', 'user'])
                ->latest()
                ->paginate(20, ['*'], 'imports_page')
                ->withQueryString(),
            'sites' => Site::query()->primaryFirst()->orderBy('name')->get(),
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
        return view('admin/site-transfers/exports/show', [
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

    private function createExport(Site $site, SiteExportRequest $request): SiteExport
    {
        return $this->siteExportManager->export($site, $request->boolean('includes_media'), $request->user()?->id);
    }
}
