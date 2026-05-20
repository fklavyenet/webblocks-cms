<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Throwable;
use WebBlocks\Cms\Http\Requests\Admin\SiteImportRunRequest;
use WebBlocks\Cms\Http\Requests\Admin\SiteImportUploadRequest;
use WebBlocks\Cms\Models\SiteImport;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteImportManager;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteImportOptions;

class SiteImportController extends Controller
{
    public function __construct(
        private readonly SiteImportManager $siteImportManager,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.site-transfers.exports.index');
    }

    public function create(): View
    {
        return view('admin/site-transfers/imports/create');
    }

    public function inspect(SiteImportUploadRequest $request): RedirectResponse
    {
        try {
            $siteImport = $this->siteImportManager->inspectUpload($request->file('archive'), $request->user()?->id);

            return redirect()->route('admin.site-transfers.imports.show', $siteImport);
        } catch (Throwable $throwable) {
            return redirect()
                ->route('admin.site-transfers.imports.create')
                ->withErrors(['site_import' => $throwable->getMessage()]);
        }
    }

    public function show(SiteImport $siteImport): View
    {
        return view('admin/site-transfers/imports/show', [
            'siteImport' => $siteImport->load(['targetSite', 'user']),
            'manifest' => $siteImport->manifest_json ?? [],
            'counts' => $siteImport->summary_json ?? [],
        ]);
    }

    public function run(SiteImportRunRequest $request, SiteImport $siteImport): RedirectResponse
    {
        try {
            $siteImport = $this->siteImportManager->import($siteImport, SiteImportOptions::fromArray($request->validated()));

            return redirect()
                ->route('admin.site-transfers.imports.show', $siteImport)
                ->with('status', 'Site import completed successfully.');
        } catch (Throwable $throwable) {
            return redirect()
                ->route('admin.site-transfers.imports.show', $siteImport)
                ->withErrors(['site_import' => $throwable->getMessage()]);
        }
    }

    public function destroy(SiteImport $siteImport): RedirectResponse
    {
        $this->siteImportManager->delete($siteImport);

        return redirect()
            ->route('admin.site-transfers.exports.index')
            ->with('status', 'Site import record deleted. Imported site content remains intact.');
    }
}
