<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageImportRequest;
use App\Support\Pages\PageIndexState;
use App\Support\Pages\PageJsonImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class PageImportController extends Controller
{
    public function __construct(
        private readonly PageJsonImporter $importer,
        private readonly PageIndexState $pageIndexState,
    ) {}

    public function store(PageImportRequest $request): RedirectResponse
    {
        try {
            $page = $this->importer->import(
                targetSiteId: (int) $request->validated('site_id'),
                file: $request->file('json_file'),
                actor: $request->user(),
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('admin.pages.edit', [
                'page' => $page,
                'return_url' => $this->pageIndexState->safeReturnUrlFromRequest($request),
            ])
            ->with('status', 'Page imported as draft. Review the imported page before publishing.');
    }
}
