<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Http\Requests\Admin\PageImportRequest;
use WebBlocks\Cms\Support\Pages\PageIndexState;
use WebBlocks\Cms\Support\Pages\PageJsonImporter;

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
