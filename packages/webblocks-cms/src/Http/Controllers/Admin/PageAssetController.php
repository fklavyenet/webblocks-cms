<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use App\Models\Page;
use App\Models\PageAsset;
use App\Support\Audit\CurrentActorResolver;
use App\Support\Users\AdminAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Http\Requests\Admin\PageAssetRequest;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Pages\PageWorkflowManager;

class PageAssetController extends Controller
{
    public function __construct(
        private readonly AdminAuthorization $authorization,
        private readonly CurrentActorResolver $currentActorResolver,
        private readonly PageRevisionManager $revisionManager,
        private readonly PageWorkflowManager $workflowManager,
    ) {}

    public function store(PageAssetRequest $request, Page $page, string $type): RedirectResponse
    {
        $this->guardMutation($request, $page);

        DB::transaction(function () use ($request, $page): void {
            $page->pageAssets()->create($request->assetData());
            $this->touchPageAudit($page, $request);
            $this->captureRevision($page, $request, 'Page asset added', 'Page assets were updated from the Page Assets tab.');
        });

        return redirect()
            ->to($request->input('_page_asset_close_url', route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets'])))
            ->with('status', strtoupper($request->assetType()).' asset added.');
    }

    public function update(PageAssetRequest $request, Page $page, PageAsset $pageAsset): RedirectResponse
    {
        $this->guardMutation($request, $page, $pageAsset);

        DB::transaction(function () use ($request, $page, $pageAsset): void {
            $pageAsset->update($request->assetData());
            $this->touchPageAudit($page, $request);
            $this->captureRevision($page, $request, 'Page asset updated', 'Page assets were updated from the Page Assets tab.');
        });

        return redirect()
            ->to($request->input('_page_asset_close_url', route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets'])))
            ->with('status', strtoupper($pageAsset->type).' asset updated.');
    }

    public function destroy(Request $request, Page $page, PageAsset $pageAsset): RedirectResponse
    {
        $this->guardMutation($request, $page, $pageAsset);

        DB::transaction(function () use ($request, $page, $pageAsset): void {
            $pageAsset->delete();
            $this->touchPageAudit($page, $request);
            $this->captureRevision($page, $request, 'Page asset deleted', 'Page assets were updated from the Page Assets tab.');
        });

        return redirect()
            ->to((string) ($request->input('_page_asset_close_url') ?: route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets'])))
            ->with('status', strtoupper($pageAsset->type).' asset deleted.');
    }

    private function guardMutation(Request $request, Page $page, ?PageAsset $pageAsset = null): void
    {
        $this->authorization->abortUnlessSiteAccess($request->user(), $page);
        abort_unless($request->user()?->isSuperAdmin(), 403);
        abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);

        if ($pageAsset !== null) {
            abort_unless($pageAsset->page_id === $page->id, 404);
        }
    }

    private function touchPageAudit(Page $page, Request $request): void
    {
        $page->forceFill([
            'updated_by_user_id' => $this->currentActorResolver->resolve($request->user())['user_id'],
            'updated_at' => now(),
        ])->save();
    }

    private function captureRevision(Page $page, Request $request, string $label, string $description): void
    {
        $this->revisionManager->capture(
            $page->fresh(),
            $request->user(),
            $label,
            $description,
            event: 'page_updated',
        );
    }
}
