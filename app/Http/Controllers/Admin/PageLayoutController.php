<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageLayoutRequest;
use App\Models\PageLayout;
use App\Models\SlotType;
use App\Support\Admin\AdminPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageLayoutController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $totalCount = PageLayout::query()->count();
        $pageLayouts = PageLayout::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(AdminPagination::perPage());

        return view('admin.page-layouts.index', [
            'pageLayouts' => $pageLayouts,
            'totalCount' => $totalCount,
            'filteredCount' => $pageLayouts->total(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return view('admin.page-layouts.create', [
            'pageLayout' => new PageLayout([
                'is_active' => true,
                'sort_order' => (int) PageLayout::query()->max('sort_order') + 10,
                'body_class' => null,
            ]),
            'slotTypes' => SlotType::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(PageLayoutRequest $request): RedirectResponse
    {
        $pageLayout = PageLayout::query()->create($request->validatedData());

        return redirect()
            ->route('admin.page-layouts.edit', $pageLayout)
            ->with('status', 'Page Layout created successfully.');
    }

    public function edit(Request $request, PageLayout $pageLayout): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return view('admin.page-layouts.edit', [
            'pageLayout' => $pageLayout->load(['layoutSlots.slotType']),
            'slotTypes' => SlotType::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(PageLayoutRequest $request, PageLayout $pageLayout): RedirectResponse
    {
        $pageLayout->update($request->validatedData());

        return redirect()
            ->route('admin.page-layouts.edit', $pageLayout)
            ->with('status', 'Page Layout updated successfully.');
    }
}
