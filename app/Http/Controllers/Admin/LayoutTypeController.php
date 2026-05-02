<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LayoutTypeRequest;
use App\Models\LayoutType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LayoutTypeController extends Controller
{
    public function index(): View
    {
        return view('admin.layout-types.index', [
            'layoutTypes' => LayoutType::query()
                ->withCount('layouts')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.layout-types.create', ['layoutType' => new LayoutType]);
    }

    public function store(LayoutTypeRequest $request): RedirectResponse
    {
        LayoutType::create($request->validated());

        return redirect()->route('admin.layout-types.index')->with('status', 'Layout type created successfully.');
    }

    public function edit(LayoutType $layoutType): View
    {
        return view('admin.layout-types.edit', ['layoutType' => $layoutType]);
    }

    public function update(LayoutTypeRequest $request, LayoutType $layoutType): RedirectResponse
    {
        $layoutType->update($request->validated());

        return redirect()->route('admin.layout-types.index')->with('status', 'Layout type updated successfully.');
    }

    public function destroy(LayoutType $layoutType): RedirectResponse
    {
        $layoutType->delete();

        return redirect()->route('admin.layout-types.index')->with('status', 'Layout type deleted successfully.');
    }
}
