<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlockTypeRequest;
use App\Models\Block;
use App\Models\BlockType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\View\View;

class BlockTypeController extends Controller
{
    public function index(Request $request): View
    {
        $categories = $this->categoryOptions();
        $statuses = $this->statusOptions();
        $adminSupportedSlugs = $this->dedicatedAdminSupportedSlugs();
        $renderSupportedSlugs = $this->dedicatedRenderSupportedSlugs();

        $filters = [
            'search' => trim((string) $request->string('search')),
            'category' => $this->normalizedFilter((string) $request->string('category'), $categories),
            'status' => $this->normalizedFilter((string) $request->string('status'), $statuses),
            'support' => $this->normalizedSupportFilter((string) $request->string('support')),
        ];

        $supportedAdminSlugs = array_fill_keys($adminSupportedSlugs, true);
        $supportedRenderSlugs = array_fill_keys($renderSupportedSlugs, true);

        $blockTypes = $this->filteredBlockTypesQuery($filters, $adminSupportedSlugs, $renderSupportedSlugs)
            ->withCount('blocks')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.block-types.index', [
            'blockTypes' => $blockTypes,
            'filters' => $filters,
            'categories' => $categories,
            'statuses' => $statuses,
            'supportOptions' => $this->supportOptions(),
            'supportedAdminForms' => $blockTypes->getCollection()
                ->mapWithKeys(fn (BlockType $blockType) => [$blockType->id => isset($supportedAdminSlugs[$blockType->slug])]),
            'supportedPublicRenders' => $blockTypes->getCollection()
                ->mapWithKeys(fn (BlockType $blockType) => [$blockType->id => isset($supportedRenderSlugs[$blockType->slug])]),
        ]);
    }

    public function create(): View
    {
        return view('admin.block-types.create', ['blockType' => new BlockType]);
    }

    public function store(BlockTypeRequest $request): RedirectResponse
    {
        BlockType::create($request->validated() + ['is_system' => false]);

        return redirect()->route('admin.block-types.index')->with('status', 'Block type created successfully.');
    }

    public function edit(BlockType $blockType): View|RedirectResponse
    {
        if ($blockType->is_system) {
            return redirect()->route('admin.block-types.index')->with('status', 'Core block types are product-owned and read-only.');
        }

        return view('admin.block-types.edit', ['blockType' => $blockType]);
    }

    public function update(BlockTypeRequest $request, BlockType $blockType): RedirectResponse
    {
        if ($blockType->is_system) {
            return redirect()->route('admin.block-types.index')->with('status', 'Core block types are product-owned and read-only.');
        }

        $blockType->update($request->validated() + ['is_system' => false]);

        return redirect()->route('admin.block-types.index')->with('status', 'Block type updated successfully.');
    }

    public function destroy(BlockType $blockType): RedirectResponse
    {
        if ($blockType->is_system) {
            return redirect()->route('admin.block-types.index')->with('status', 'Core block types are product-owned and cannot be deleted from the admin.');
        }

        $blockType->delete();

        return redirect()->route('admin.block-types.index')->with('status', 'Block type deleted successfully.');
    }

    private function filteredBlockTypesQuery(array $filters, array $adminSupportedSlugs, array $renderSupportedSlugs): Builder
    {
        $query = BlockType::query();

        if ($filters['search'] !== '') {
            $query->where(function (Builder $builder) use ($filters): void {
                $builder
                    ->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('slug', 'like', '%'.$filters['search'].'%')
                    ->orWhere('description', 'like', '%'.$filters['search'].'%');
            });
        }

        if ($filters['category'] !== '') {
            $query->where('category', $filters['category']);
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        match ($filters['support']) {
            'system' => $query->where('is_system', true),
            'user' => $query->where('is_system', false),
            'container' => $query->where('is_container', true),
            'admin' => $this->applySupportedSlugFilter($query, $adminSupportedSlugs),
            'render' => $this->applySupportedSlugFilter($query, $renderSupportedSlugs),
            default => null,
        };

        return $query;
    }

    private function applySupportedSlugFilter(Builder $query, array $slugs): void
    {
        if ($slugs === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('slug', $slugs);
    }

    private function categoryOptions(): array
    {
        return BlockType::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->orderBy('category')
            ->distinct()
            ->pluck('category')
            ->all();
    }

    private function statusOptions(): array
    {
        return BlockType::query()
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->orderBy('status')
            ->distinct()
            ->pluck('status')
            ->all();
    }

    private function supportOptions(): array
    {
        return [
            'system' => 'System',
            'user' => 'User / install-specific',
            'container' => 'Container-capable',
            'admin' => 'Has admin support',
            'render' => 'Has render support',
        ];
    }

    private function normalizedFilter(string $value, array $allowedValues): string
    {
        return in_array($value, $allowedValues, true) ? $value : '';
    }

    private function normalizedSupportFilter(string $value): string
    {
        return array_key_exists($value, $this->supportOptions()) ? $value : '';
    }

    private function dedicatedAdminSupportedSlugs(): array
    {
        return BlockType::query()
            ->orderBy('slug')
            ->pluck('slug')
            ->filter(fn (?string $slug): bool => $slug !== null && ViewFacade::exists('admin.blocks.types.'.$slug))
            ->values()
            ->all();
    }

    private function dedicatedRenderSupportedSlugs(): array
    {
        return BlockType::query()
            ->orderBy('slug')
            ->pluck('slug')
            ->filter(fn (?string $slug): bool => $slug !== null && ViewFacade::exists('pages.partials.blocks.'.$slug))
            ->values()
            ->all();
    }
}
