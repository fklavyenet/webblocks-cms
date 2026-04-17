<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LayoutRequest;
use App\Models\Layout;
use App\Models\LayoutType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LayoutController extends Controller
{
    public function index(): View
    {
        return view('admin.layouts.index', [
            'layouts' => Layout::query()
                ->with('layoutType')
                ->withCount('pages')
                ->latest()
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.layouts.create', [
            'layout' => new Layout,
            'layoutTypes' => LayoutType::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(LayoutRequest $request): RedirectResponse
    {
        Layout::create($request->validated());

        return redirect()
            ->route('admin.layouts.index')
            ->with('status', 'Layout created successfully.');
    }

    public function edit(Layout $layout): View
    {
        return view('admin.layouts.edit', [
            'layout' => $layout,
            'layoutTypes' => LayoutType::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(LayoutRequest $request, Layout $layout): RedirectResponse
    {
        $layout->update($request->validated());

        return redirect()
            ->route('admin.layouts.index')
            ->with('status', 'Layout updated successfully.');
    }

    public function destroy(Layout $layout): RedirectResponse
    {
        $layout->delete();

        return redirect()
            ->route('admin.layouts.index')
            ->with('status', 'Layout deleted successfully.');
    }
}
