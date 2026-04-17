<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlockTypeRequest;
use App\Models\Block;
use App\Models\BlockType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BlockTypeController extends Controller
{
    public function index(): View
    {
        $blockTypes = BlockType::query()
            ->withCount('blocks')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.block-types.index', [
            'blockTypes' => $blockTypes,
            'supportedAdminForms' => $blockTypes->getCollection()
                ->mapWithKeys(fn (BlockType $blockType) => [$blockType->id => Block::supportsAdminForm($blockType->slug)]),
            'supportedPublicRenders' => $blockTypes->getCollection()
                ->mapWithKeys(fn (BlockType $blockType) => [$blockType->id => Block::supportsPublicRender($blockType->slug)]),
        ]);
    }

    public function create(): View
    {
        return view('admin.block-types.create', ['blockType' => new BlockType]);
    }

    public function store(BlockTypeRequest $request): RedirectResponse
    {
        BlockType::create($request->validated());

        return redirect()->route('admin.block-types.index')->with('status', 'Block type created successfully.');
    }

    public function edit(BlockType $blockType): View
    {
        return view('admin.block-types.edit', ['blockType' => $blockType]);
    }

    public function update(BlockTypeRequest $request, BlockType $blockType): RedirectResponse
    {
        $blockType->update($request->validated());

        return redirect()->route('admin.block-types.index')->with('status', 'Block type updated successfully.');
    }

    public function destroy(BlockType $blockType): RedirectResponse
    {
        $blockType->delete();

        return redirect()->route('admin.block-types.index')->with('status', 'Block type deleted successfully.');
    }
}
