<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Support\Pages\PageRevisionInspector;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class PageRevisionController extends Controller
{
  public function __construct(
    private readonly AdminAuthorization $authorization,
    private readonly PageRevisionManager $revisionManager,
    private readonly PageRevisionInspector $revisionInspector,
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
    ]);
  }

  public function restore(Page $page, PageRevision $revision): RedirectResponse
  {
    $this->authorization->abortUnlessSiteAccess(request()->user(), $page);
    abort_unless($revision->page_id === $page->id, 404);
    abort_unless($this->revisionManager->canRestore(request()->user(), $page), 403);

    if (! $this->revisionManager->revisionsTableExists()) {
      return redirect()
        ->route('admin.pages.edit', $page)
        ->withErrors(['revisions' => 'Page revisions are not ready yet. Run the latest migrations before restoring revisions.']);
    }

    if ($this->revisionInspector->inspect($page, $revision)['health']['status'] === 'blocked') {
      return redirect()
        ->route('admin.pages.revisions.show', [$page, $revision])
        ->withErrors(['revision' => 'This version cannot be restored because required referenced records are missing.']);
    }

    $this->revisionManager->restore($page, $revision, request()->user());
    $page = $page->fresh();

    $redirect = redirect()
      ->route('admin.pages.edit', $page)
      ->with('status', 'Page revision restored successfully.');

    if ($page->isPublished() && $page->publicUrl()) {
      $redirect->with('status_action', [
        'label' => 'View page',
        'url' => $page->publicUrl(),
      ]);
    }

    return $redirect;
  }
}
