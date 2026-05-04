<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePageSlotRequest;
use App\Models\Page;
use App\Models\PageSlot;
use App\Support\Pages\PageRevisionManager;
use App\Support\Pages\PageWorkflowManager;
use App\Support\Users\AdminAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PageSlotController extends Controller
{
    public function __construct(
        private readonly PageRevisionManager $revisionManager,
        private readonly PageWorkflowManager $workflowManager,
        private readonly AdminAuthorization $authorization,
    ) {}

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

            $this->revisionManager->capture(
                $page->fresh(),
                $request->user(),
                'Slot added',
                'Page slot structure was updated by adding a slot.',
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

            $this->revisionManager->capture(
                $page->fresh(),
                request()->user(),
                'Slot deleted',
                'Page slot structure was updated by removing a slot.',
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

            $this->revisionManager->capture(
                $page->fresh(),
                request()->user(),
                'Slot order updated',
                'Page slot order was changed.',
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

    private function redirectToEdit(Page $page, string $status): RedirectResponse
    {
        $redirect = redirect()
            ->route('admin.pages.edit', $page)
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
