<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\PageLayoutSlotRequest;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageLayoutSlot;
use WebBlocks\Cms\Models\SlotType;

class PageLayoutSlotController extends Controller
{
  public function create(Request $request, PageLayout $pageLayout): View
  {
    abort_unless($request->user()?->isSuperAdmin(), 403);

    return view('webblocks-cms::admin.page-layout-slots.create', [
      'pageLayout' => $pageLayout,
      'pageLayoutSlot' => new PageLayoutSlot([
        'is_active' => true,
        'html_element' => 'div',
        'sort_order' => (int) $pageLayout->layoutSlots()->max('sort_order') + 10,
      ]),
      'slotTypes' => SlotType::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get(),
    ]);
  }

  public function store(PageLayoutSlotRequest $request, PageLayout $pageLayout): RedirectResponse
  {
    DB::transaction(function () use ($request, $pageLayout): void {
      $pageLayout->layoutSlots()->create($request->validatedData());
    });

    return redirect()
      ->route('admin.page-layouts.edit', $pageLayout)
      ->with('status', 'Page Layout Slot created successfully.');
  }

  public function edit(Request $request, PageLayout $pageLayout, PageLayoutSlot $pageLayoutSlot): View
  {
    abort_unless($request->user()?->isSuperAdmin(), 403);
    abort_unless($pageLayoutSlot->page_layout_id === $pageLayout->id, 404);

    return view('webblocks-cms::admin.page-layout-slots.edit', [
      'pageLayout' => $pageLayout,
      'pageLayoutSlot' => $pageLayoutSlot,
      'slotTypes' => SlotType::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get(),
    ]);
  }

  public function update(PageLayoutSlotRequest $request, PageLayout $pageLayout, PageLayoutSlot $pageLayoutSlot): RedirectResponse
  {
    abort_unless($pageLayoutSlot->page_layout_id === $pageLayout->id, 404);

    DB::transaction(function () use ($request, $pageLayoutSlot): void {
      $pageLayoutSlot->update($request->validatedData());
    });

    return redirect()
      ->route('admin.page-layouts.edit', $pageLayout)
      ->with('status', 'Page Layout Slot updated successfully.');
  }

  public function destroy(Request $request, PageLayout $pageLayout, PageLayoutSlot $pageLayoutSlot): RedirectResponse
  {
    abort_unless($request->user()?->isSuperAdmin(), 403);
    abort_unless($pageLayoutSlot->page_layout_id === $pageLayout->id, 404);

    if ($pageLayoutSlot->is_system || $pageLayoutSlot->is_required) {
      return redirect()
        ->route('admin.page-layouts.edit', $pageLayout)
        ->withErrors(['page_layout_slot' => 'This Page Layout Slot cannot be deleted.']);
    }

    $pageLayoutSlot->delete();

    return redirect()
      ->route('admin.page-layouts.edit', $pageLayout)
      ->with('status', 'Page Layout Slot deleted successfully.');
  }
}
