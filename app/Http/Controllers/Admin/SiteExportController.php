<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteExportRequest;
use App\Models\Site;
use App\Models\SiteExport;
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
            'exports' => SiteExport::query()->with(['site', 'user'])->latest()->paginate(20),
            'sites' => Site::query()->primaryFirst()->orderBy('name')->get(),
        ]);
    }

    public function store(SiteExportRequest $request): RedirectResponse
    {
        try {
            $site = Site::query()->findOrFail($request->integer('site_id'));
            $siteExport = $this->siteExportManager->export($site, $request->boolean('includes_media'), $request->user()?->id);

            return redirect()
                ->route('admin.site-transfers.exports.show', $siteExport)
                ->with('status', 'Site export completed successfully.');
        } catch (Throwable $throwable) {
            return redirect()
                ->route('admin.site-transfers.exports.index')
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
}
