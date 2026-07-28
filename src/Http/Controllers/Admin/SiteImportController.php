<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;
use WebBlocks\Cms\Http\Requests\Admin\BulkDeleteSiteImportsRequest;
use WebBlocks\Cms\Http\Requests\Admin\SiteImportRunRequest;
use WebBlocks\Cms\Http\Requests\Admin\SiteImportStepRequest;
use WebBlocks\Cms\Http\Requests\Admin\SiteImportUploadRequest;
use WebBlocks\Cms\Models\SiteImport;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteImportBulkDeleter;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteImportManager;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteImportOptions;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteImportStepResult;
use WebBlocks\Cms\Support\Sites\SiteDeleteResult;

class SiteImportController extends Controller
{
  public function __construct(
    private readonly SiteImportManager $siteImportManager,
    private readonly SiteImportBulkDeleter $siteImportBulkDeleter,
  ) {}

  public function index(): View
  {
    return view('webblocks-cms::admin.site-transfers.imports.index');
  }

  public function create(): View
  {
    return view('webblocks-cms::admin.site-transfers.imports.create');
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
    return view('webblocks-cms::admin.site-transfers.imports.show', [
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

  /**
   * Advance a running import by one bounded step.
   *
   * The unit the progress modal drives. It answers with where the import got
   * to rather than a redirect, so the browser can keep asking without leaving
   * the page — and because each step commits, closing the tab pauses the
   * import instead of destroying it.
   */
  public function step(SiteImportStepRequest $request, SiteImport $siteImport): JsonResponse
  {
    if ($siteImport->isCompleted()) {
      return response()->json(SiteImportStepResult::fromImport($siteImport)->toArray());
    }

    // One step at a time per import. Two tabs polling the same import would
    // otherwise run the same slice twice, and the second one would collide on
    // whatever unique index the phase writes against.
    $lock = Cache::lock('wbcms-site-import-step:'.$siteImport->id, 120);

    if (! $lock->get()) {
      return response()->json([
        ...SiteImportStepResult::fromImport($siteImport)->toArray(),
        'busy' => true,
      ], 409);
    }

    try {
      // A resume has no form to read, so the options come from what the first
      // step already recorded rather than from this request.
      $options = $request->continuesExistingImport()
        ? $this->siteImportManager->resumeOptions($siteImport)
        : SiteImportOptions::fromArray($request->validated());

      $result = $this->siteImportManager->step($siteImport, $options);

      return response()->json($result->toArray());
    } catch (Throwable $throwable) {
      return response()->json([
        ...SiteImportStepResult::fromImport($siteImport->fresh())->toArray(),
        'failed' => true,
        'failure_message' => $throwable->getMessage(),
      ], 500);
    } finally {
      $lock->release();
    }
  }

  /**
   * Delete what an unfinished import wrote and reset it for a clean attempt.
   */
  public function discard(SiteImport $siteImport): RedirectResponse
  {
    $result = $this->siteImportManager->discardImportedSite($siteImport);

    if ($result instanceof SiteDeleteResult && ! $result->deleted) {
      return redirect()
        ->route('admin.site-transfers.imports.show', $siteImport)
        ->withErrors(['site_import' => $result->firstBlocker() ?? 'The partially imported site could not be removed.']);
    }

    return redirect()
      ->route('admin.site-transfers.imports.show', $siteImport)
      ->with('status', 'Removed the partially imported site. The package is ready to import again.');
  }

  public function destroy(SiteImport $siteImport): RedirectResponse
  {
    $this->siteImportManager->delete($siteImport);

    return redirect()
      ->route('admin.site-transfers.exports.index')
      ->with('status', 'Site import record deleted. Imported site content remains intact.');
  }

  public function bulkDestroy(BulkDeleteSiteImportsRequest $request): RedirectResponse
  {
    $result = $this->siteImportBulkDeleter->deleteSelected($request->validated('site_import_ids'));

    $redirect = redirect()
      ->route('admin.site-transfers.exports.index')
      ->with($result->deletedCount() > 0 ? 'status' : 'bulk_status', $result->message());

    if ($result->hasFailures()) {
      $redirect->withErrors(['site_imports' => implode(' ', $result->failureMessages())]);
    }

    return $redirect;
  }
}
