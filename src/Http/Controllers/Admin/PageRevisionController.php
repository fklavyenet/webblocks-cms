<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use RuntimeException;
use WebBlocks\Cms\Http\Requests\Admin\ApplyPageRevisionCandidateRequest;
use WebBlocks\Cms\Http\Requests\Admin\PageRevisionScopeRequest;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageRevisionCandidate;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\Support\Pages\PageLocaleRestoreException;
use WebBlocks\Cms\Support\Pages\PageLocaleRestorePlanner;
use WebBlocks\Cms\Support\Pages\PageRevisionCandidateManager;
use WebBlocks\Cms\Support\Pages\PageRevisionInspector;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class PageRevisionController extends Controller
{
  public function __construct(
    private readonly AdminAuthorization $authorization,
    private readonly PageRevisionManager $revisionManager,
    private readonly PageRevisionInspector $revisionInspector,
    private readonly PageRevisionCandidateManager $candidateManager,
  ) {}

  public function index(Page $page): View
  {
    $this->authorization->abortUnlessSiteAccess(request()->user(), $page);
    abort_unless($this->revisionManager->canView(request()->user(), $page), 403);

    if (! $this->revisionManager->revisionsTableExists()) {
      return redirect()
        ->route('admin.pages.edit', $page)
        ->withErrors(['revisions' => 'Page revisions are not ready yet. Run the latest migrations before opening revision history.'])
        ->throwResponse();
    }

    $revisions = $page->revisions()->with(['actor', 'createdByUser', 'restoredFrom'])->paginate(AdminPagination::perPage());
    $items = $revisions->getCollection();
    $nextOlder = $revisions->hasMorePages()
      ? $page->revisions()->skip($revisions->currentPage() * $revisions->perPage())->first()
      : null;
    $items->each(function (PageRevision $revision, int $index) use ($items, $nextOlder): void {
      $revision->setAttribute('display_summary', $this->revisionInspector->listSummary($revision, $items->get($index + 1) ?? $nextOlder));
    });

    return view('webblocks-cms::admin.pages.revisions.index', [
      'page' => $page->loadMissing('site'),
      'revisions' => $revisions,
      'canRestoreRevisions' => $this->revisionManager->canRestore(request()->user(), $page),
    ]);
  }

  public function show(PageRevisionScopeRequest $request, Page $page, PageRevision $revision): View
  {
    $this->authorization->abortUnlessSiteAccess(request()->user(), $page);
    abort_unless($revision->page_id === $page->id, 404);
    abort_unless($this->revisionManager->canView(request()->user(), $page), 403);

    $inspection = $this->revisionInspector->inspect($page, $revision);
    $scope = $request->restoreScope();
    $localeId = $request->restoreLocaleId();
    $candidate = $this->candidateManager->readyFor($page, $revision, $scope, $localeId);
    $localePlan = $scope === 'full_page' ? null : ($candidate ? app(PageLocaleRestorePlanner::class)->reviewCandidate($candidate) : app(PageLocaleRestorePlanner::class)->publicPlan(app(PageLocaleRestorePlanner::class)->plan($page, $revision, $scope, $localeId)));
    if ($localePlan !== null) {
      $inspection['translation_removals'] = [];
    }

    return view('webblocks-cms::admin.pages.revisions.show', [
      'page' => $page->loadMissing('site'),
      'revision' => $revision->loadMissing(['actor', 'createdByUser', 'restoredFrom']),
      'inspection' => $inspection,
      'restoreScope' => $scope,
      'restoreLocaleId' => $localeId,
      'localePlan' => $localePlan,
      'restoreLocales' => $page->site->enabledLocales()->get(),
      'canRestoreRevisions' => $this->revisionManager->canRestore(request()->user(), $page),
      'candidate' => $candidate,
    ]);
  }

  public function prepareCandidate(PageRevisionScopeRequest $request, Page $page, PageRevision $revision): RedirectResponse
  {
    $this->authorization->abortUnlessSiteAccess(request()->user(), $page);
    abort_unless($revision->page_id === $page->id, 404);
    abort_unless($this->revisionManager->canRestore(request()->user(), $page), 403);

    if (! $this->revisionManager->revisionsTableExists() || ! $this->candidateManager->tableExists()) {
      return redirect()
        ->route('admin.pages.revisions.show', [$page, $revision])
        ->withErrors(['revision' => 'Page version candidates are not ready yet. Run the latest migrations.']);
    }

    if ($request->restoreScope() === 'full_page' && $this->revisionInspector->inspect($page, $revision)['health']['status'] === 'blocked') {
      return redirect()
        ->route('admin.pages.revisions.show', [$page, $revision])
        ->withErrors(['revision' => 'This version cannot be restored because required referenced records are missing.']);
    }

    try {
      $candidate = $this->candidateManager->create($page, $revision, $request->user(), $request->restoreScope(), $request->restoreLocaleId());
    } catch (PageLocaleRestoreException $exception) {
      return redirect()->route('admin.pages.revisions.show', [$page, $revision, 'restore_scope' => $request->restoreScope(), 'locale_id' => $request->restoreLocaleId()])->withErrors(['candidate' => $exception->getMessage()]);
    }

    return redirect()
      ->route('admin.pages.revisions.show', [$page, $revision, 'restore_scope' => $request->restoreScope(), 'locale_id' => $request->restoreLocaleId()])
      ->with('status', 'Restore candidate prepared. Preview it before applying it to the current page.')
      ->with('status_action', [
        'label' => 'Preview candidate',
        'url' => $candidate->previewUrl(),
      ]);
  }

  public function applyCandidate(ApplyPageRevisionCandidateRequest $request, Page $page, PageRevisionCandidate $candidate): RedirectResponse
  {
    $this->authorization->abortUnlessSiteAccess($request->user(), $page);
    abort_unless($candidate->page_id === $page->id, 404);
    abort_unless($this->revisionManager->canRestore($request->user(), $page), 403);

    try {
      $page = $this->candidateManager->apply($candidate, $request->user(), $request->confirmedTranslationRemovalLocaleIds(), $request->boolean('confirm_locale_restore'));
    } catch (RuntimeException $exception) {
      return redirect()
        ->route('admin.pages.revisions.show', [$page, $candidate->page_revision_id, 'restore_scope' => $candidate->restore_scope, 'locale_id' => $candidate->restore_locale_id])
        ->withErrors(['candidate' => $exception->getMessage()]);
    }

    return redirect()
      ->route('admin.pages.edit', $page)
      ->with('status', 'The reviewed page version was applied successfully. A safety version of the previous state was retained.');
  }

  public function discardCandidate(Page $page, PageRevisionCandidate $candidate): RedirectResponse
  {
    $this->authorization->abortUnlessSiteAccess(request()->user(), $page);
    abort_unless($candidate->page_id === $page->id, 404);
    abort_unless($this->revisionManager->canRestore(request()->user(), $page), 403);
    $revisionId = $candidate->page_revision_id;
    $this->candidateManager->discard($candidate);

    return redirect()
      ->route('admin.pages.revisions.show', [$page, $revisionId, 'restore_scope' => $candidate->restore_scope, 'locale_id' => $candidate->restore_locale_id])
      ->with('status', 'Restore candidate discarded. The current page was not changed.');
  }
}
