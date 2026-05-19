<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use App\Models\Page;
use App\Models\PageSlot;
use App\Support\Users\AdminAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Http\Requests\Admin\StorePageSlotRequest;
use WebBlocks\Cms\Http\Requests\Admin\SyncPageLayoutSlotsRequest;
use WebBlocks\Cms\Http\Requests\Admin\UpdatePageSlotSourceRequest;
use WebBlocks\Cms\Support\Pages\PageIndexState;
use WebBlocks\Cms\Support\Pages\PageLayoutSlotSyncer;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Pages\PageWorkflowManager;

class PageSlotController extends Controller
{
    public function __construct(
        private readonly PageRevisionManager $revisionManager,
        private readonly PageIndexState $pageIndexState,
        private readonly PageLayoutSlotSyncer $pageLayoutSlotSyncer,
        private readonly PageWorkflowManager $workflowManager,
        private readonly AdminAuthorization $authorization,
    ) {}

    public function syncLayoutSlots(SyncPageLayoutSlotsRequest $request, Page $page): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess($request->user(), $page);
        abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);

        $result = $this->pageLayoutSlotSyncer->syncMissingSlots($page, $request->user());

        if ($result['noop']) {
            return $this->redirectToEdit($page, 'This page already has all slots defined by the selected Page Layout.', $request->validatedReturnUrl(), 'layout-slots');
        }

        $addedCount = (int) $result['added_count'];

        return $this->redirectToEdit(
            $page,
            $addedCount === 1
                ? 'Added 1 missing Page Layout slot.'
                : 'Added '.$addedCount.' missing Page Layout slots.',
            $request->validatedReturnUrl(),
            'layout-slots',
        );
    }

    public function store(StorePageSlotRequest $request, Page $page): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess($request->user(), $page);
        abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);

        DB::transaction(function () use ($request, $page): void {
            $nextSortOrder = (int) $page->slots()->max('sort_order') + 1;

            $page->slots()->create([
                'slot_type_id' => (int) $request->validated('slot_type_id'),
                'sort_order' => $nextSortOrder,
            ]);

            $page->forceFill([
                'updated_by_user_id' => $request->user()?->id,
            ])->save();

            $this->revisionManager->capture(
                $page->fresh(),
                $request->user(),
                'Slot added',
                'Page slot structure was updated by adding a slot.',
                event: 'slot_changed',
            );
        });

        return $this->redirectToEdit($page, 'Slot added successfully.');
    }

    public function destroy(Page $page, PageSlot $slot): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess(request()->user(), $page);
        abort_unless($this->workflowManager->canEditContent(request()->user(), $page), 403);
        abort_unless($slot->page_id === $page->id, 404);

        if ($page->blocks()->where('slot_type_id', $slot->slot_type_id)->exists()) {
            return redirect()
                ->route('admin.pages.edit', $page)
                ->withErrors(['slot' => 'Slot cannot be deleted while it still contains blocks.']);
        }

        DB::transaction(function () use ($page, $slot): void {
            $slot->delete();
            $this->normalizeSortOrder($page);
            $page->forceFill(['updated_by_user_id' => request()->user()?->id])->save();

            $this->revisionManager->capture(
                $page->fresh(),
                request()->user(),
                'Slot deleted',
                'Page slot structure was updated by removing a slot.',
                event: 'slot_changed',
            );
        });

        return $this->redirectToEdit($page, 'Slot deleted successfully.');
    }

    public function moveUp(Page $page, PageSlot $slot): RedirectResponse
    {
        return $this->move($page, $slot, 'up');
    }

    public function moveDown(Page $page, PageSlot $slot): RedirectResponse
    {
        return $this->move($page, $slot, 'down');
    }

    public function updateSource(UpdatePageSlotSourceRequest $request, Page $page, PageSlot $slot): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess($request->user(), $page);
        abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);
        abort_unless($slot->page_id === $page->id, 404);

        DB::transaction(function () use ($request, $page, $slot): void {
            $slot->update($request->validatedData());
            $page->forceFill(['updated_by_user_id' => $request->user()?->id])->save();

            $this->revisionManager->capture(
                $page->fresh(),
                $request->user(),
                'Slot source updated',
                'Page slot source was updated.',
                event: 'slot_changed',
            );
        });

        return $this->redirectToEdit($page, 'Slot source updated successfully.');
    }

    private function move(Page $page, PageSlot $slot, string $direction): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess(request()->user(), $page);
        abort_unless($this->workflowManager->canEditContent(request()->user(), $page), 403);
        abort_unless($slot->page_id === $page->id, 404);

        $moved = DB::transaction(function () use ($page, $slot, $direction): bool {
            $slots = $page->slots()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->values();

            $currentIndex = $slots->search(fn (PageSlot $candidate) => $candidate->id === $slot->id);

            if (! is_int($currentIndex)) {
                return false;
            }

            $swapIndex = $direction === 'up'
                ? $currentIndex - 1
                : $currentIndex + 1;

            if ($swapIndex < 0 || $swapIndex >= $slots->count()) {
                return false;
            }

            $orderedSlots = $slots->all();
            $currentSlot = $orderedSlots[$currentIndex];
            $orderedSlots[$currentIndex] = $orderedSlots[$swapIndex];
            $orderedSlots[$swapIndex] = $currentSlot;

            foreach ($orderedSlots as $index => $orderedSlot) {
                if ($orderedSlot->sort_order === $index) {
                    continue;
                }

                $orderedSlot->update(['sort_order' => $index]);
            }

            $page->forceFill(['updated_by_user_id' => request()->user()?->id])->save();

            $this->revisionManager->capture(
                $page->fresh(),
                request()->user(),
                'Slot order updated',
                'Page slot order was changed.',
                event: 'slot_changed',
            );

            return true;
        });

        if (! $moved) {
            return $this->redirectToEdit($page, 'Slot is already at the edge of the page.');
        }

        return $this->redirectToEdit($page, 'Slot order updated successfully.');
    }

    private function normalizeSortOrder(Page $page): void
    {
        $page->slots()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->values()
            ->each(function (PageSlot $slot, int $index): void {
                if ($slot->sort_order === $index) {
                    return;
                }

                $slot->update(['sort_order' => $index]);
            });
    }

    private function redirectToEdit(Page $page, string $status, ?string $returnUrl = null, ?string $tab = null): RedirectResponse
    {
        $parameters = ['page' => $page];
        $safeReturnUrl = $returnUrl ?? $this->pageIndexState->safeReturnUrlFromRequest(request());

        if ($tab !== null && $tab !== '') {
            $parameters['tab'] = $tab;
        }

        if ($safeReturnUrl !== null && $safeReturnUrl !== '') {
            $parameters['return_url'] = $safeReturnUrl;
        }

        $redirect = redirect()
            ->route('admin.pages.edit', $parameters)
            ->with('status', $status);

        if ($page->isPublished() && $page->publicUrl()) {
            $redirect->with('status_action', [
                'label' => 'View page',
                'url' => $page->publicUrl(),
            ]);
        }

        return $redirect;
    }
}
