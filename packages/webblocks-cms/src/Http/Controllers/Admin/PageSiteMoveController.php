<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\MovePageSiteRequest;
use WebBlocks\Cms\Support\Pages\PageSiteMover;

class PageSiteMoveController extends Controller
{
    public function __construct(
        private readonly AdminAuthorization $authorization,
        private readonly PageSiteMover $mover,
    ) {}

    public function create(Request $request, Page $page): View
    {
        $this->authorizeMove($request, $page);

        $page->loadMissing(['site', 'translations.locale', 'slots.sharedSlot', 'slots.slotType', 'navigationItems']);

        $sites = $this->authorization->scopeSitesForUser(Site::query(), $request->user())
            ->whereKeyNot($page->site_id)
            ->primaryFirst()
            ->orderBy('name')
            ->get();

        return view('webblocks-cms::admin.pages.move-site', [
            'page' => $page,
            'sites' => $sites,
        ]);
    }

    public function store(MovePageSiteRequest $request, Page $page): RedirectResponse
    {
        $this->authorizeMove($request, $page);

        $targetSite = Site::query()->findOrFail((int) $request->validated('target_site_id'));

        try {
            $result = $this->mover->move($page, $targetSite, $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        $status = 'Page moved to "'.$result->targetSite->name.'".';

        if ($result->navigationReferenceCount > 0) {
            $status .= ' Review navigation on the target site.';
        }

        return redirect()
            ->route('admin.pages.edit', $result->page)
            ->with('status', $status);
    }

    private function authorizeMove(Request $request, Page $page): void
    {
        $this->authorization->abortUnlessSiteAccess($request->user(), $page);
        abort_unless($request->user()?->isSuperAdmin() || $request->user()?->isSiteAdmin(), 403);
    }
}
