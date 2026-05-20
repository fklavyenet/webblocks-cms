<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;

class PageRevisionController extends Controller
{
    public function __construct(
        private readonly AdminAuthorization $authorization,
        private readonly PageRevisionManager $revisionManager,
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

        return view('webblocks-cms::admin.pages.revisions.index', [
            'page' => $page->loadMissing('site'),
            'revisions' => $page->revisions()->with(['actor', 'createdByUser', 'restoredFrom'])->get(),
            'canRestoreRevisions' => $this->revisionManager->canRestore(request()->user(), $page),
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
