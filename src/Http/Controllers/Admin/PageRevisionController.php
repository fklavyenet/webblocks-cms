<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use RuntimeException;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageRevisionCandidate;
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

    $revisions = $page->revisions()->with(['actor', 'createdByUser', 'restoredFrom'])->get();
    $revisions->each(function (PageRevision $revision, int $index) use ($revisions): void {
      $revision->setAttribute('display_summary', $this->revisionInspector->listSummary($revision, $revisions->get($index + 1)));
    });

    return view('webblocks-cms::admin.pages.revisions.index', [
      'page' => $page->loadMissing('site'),
      'revisions' => $revisions,
      'canRestoreRevisions' => $this->revisionManager->canRestore(request()->user(), $page),
    ]);
  }

  public function show(Page $page, PageRevision $revision): View
  {
    $this->authorization->abortUnlessSiteAccess(request()->user(), $page);
    abort_unless($revision->page_id === $page->id, 404);
    abort_unless($this->revisionManager->canView(request()->user(), $page), 403);

    $inspection = $this->revisionInspector->inspect($page, $revision);

    return view('webblocks-cms::admin.pages.revisions.show', [
      'page' => $page->loadMissing('site'),
      'revision' => $revision->loadMissing(['actor', 'createdByUser', 'restoredFrom']),
      'inspection' => $inspection,
      'canRestoreRevisions' => $this->revisionManager->canRestore(request()->user(), $page)
        && $inspection['health']['status'] !== 'blocked',
      'candidate' => $this->candidateManager->readyFor($page, $revision),
    ]);
  }

  public function prepareCandidate(Page $page, PageRevision $revision): RedirectResponse
  {
    $this->authorization->abortUnlessSiteAccess(request()->user(), $page);
    abort_unless($revision->page_id === $page->id, 404);
    abort_unless($this->revisionManager->canRestore(request()->user(), $page), 403);

    if (! $this->revisionManager->revisionsTableExists() || ! $this->candidateManager->tableExists()) {
      return redirect()
        ->route('admin.pages.revisions.show', [$page, $revision])
        ->withErrors(['revision' => 'Page version candidates are not ready yet. Run the latest migrations.']);
    }

    if ($this->revisionInspector->inspect($page, $revision)['health']['status'] === 'blocked') {
      return redirect()
        ->route('admin.pages.revisions.show', [$page, $revision])
        ->withErrors(['revision' => 'This version cannot be restored because required referenced records are missing.']);
    }

    $candidate = $this->candidateManager->create($page, $revision, request()->user());

    return redirect()
      ->route('admin.pages.revisions.show', [$page, $revision])
      ->with('status', 'Restore candidate prepared. Preview it before applying it to the current page.')
      ->with('status_action', [
        'label' => 'Preview candidate',
        'url' => route('admin.pages.preview', $candidate->candidatePage),
      ]);
  }

  public function applyCandidate(Request $request, Page $page, PageRevisionCandidate $candidate): RedirectResponse
  {
    $this->authorization->abortUnlessSiteAccess($request->user(), $page);
    abort_unless($candidate->page_id === $page->id, 404);
    abort_unless($this->revisionManager->canRestore($request->user(), $page), 403);
    $request->validate(['confirm_apply_candidate' => ['accepted']]);

    try {
      $page = $this->candidateManager->apply($candidate, $request->user());
    } catch (RuntimeException $exception) {
      return redirect()
        ->route('admin.pages.revisions.show', [$page, $candidate->page_revision_id])
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
      ->route('admin.pages.revisions.show', [$page, $revisionId])
      ->with('status', 'Restore candidate discarded. The current page was not changed.');
  }
}
