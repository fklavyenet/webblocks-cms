<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageTypeRequest;
use App\Models\PageType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PageTypeController extends Controller
{
    public function index(): View
    {
        return view('admin.page-types.index', [
            'pageTypes' => PageType::query()
                ->withCount('pages')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.page-types.create', ['pageType' => new PageType]);
    }

    public function store(PageTypeRequest $request): RedirectResponse
    {
        PageType::create($request->validated());

        return redirect()->route('admin.page-types.index')->with('status', 'Page type created successfully.');
    }

    public function edit(PageType $pageType): View
    {
        return view('admin.page-types.edit', ['pageType' => $pageType]);
    }

    public function update(PageTypeRequest $request, PageType $pageType): RedirectResponse
    {
        $pageType->update($request->validated());

        return redirect()->route('admin.page-types.index')->with('status', 'Page type updated successfully.');
    }

    public function destroy(PageType $pageType): RedirectResponse
    {
        $pageType->delete();

        return redirect()->route('admin.page-types.index')->with('status', 'Page type deleted successfully.');
    }
}
