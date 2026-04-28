@php
    $search = strtolower(trim((string) request('block_type_search')));
    $recommendedSlugs = collect(['section', 'heading', 'rich-text', 'callout', 'button']);
    $excludedSlugs = collect(['column_item', 'feature-item', 'link-list-item', 'menu']);

    $availableBlockTypes = $blockTypes
        ->reject(fn ($blockType) => $excludedSlugs->contains($blockType->slug) && $block->parent_id === null)
        ->filter(function ($blockType) use ($search) {
            if ($search === '') {
                return true;
            }

            return str_contains(strtolower($blockType->name), $search)
                || str_contains(strtolower((string) $blockType->description), $search)
                || str_contains(strtolower($blockType->slug), $search);
        })
        ->sortBy([fn ($blockType) => $blockType->sort_order, fn ($blockType) => $blockType->name])
        ->values();

    $groups = $availableBlockTypes->groupBy(fn ($blockType) => $blockType->is_system ? 'System Blocks' : 'Content Blocks');

    $labelMap = [
        'heading' => 'Hero',
        'callout' => 'CTA',
        'gallery' => 'Features',
        'section' => 'Section',
        'rich-text' => 'Rich Text',
        'download' => 'Download',
    ];
@endphp

<div class="wb-card wb-card-muted">
    <div class="wb-card-header">
        <strong>Add a Block</strong>
    </div>
    <div class="wb-card-body">
        <form method="GET" action="{{ $action }}" class="wb-stack wb-gap-3">
            @if ($block->page_id)
                <input type="hidden" name="page_id" value="{{ $block->page_id }}">
            @endif

            @if ($block->parent_id)
                <input type="hidden" name="parent_id" value="{{ $block->parent_id }}">
            @endif

            <div class="wb-stack wb-gap-1">
                <label for="block_type_search">Search Blocks</label>
                <input id="block_type_search" name="block_type_search" class="wb-input" type="text" value="{{ request('block_type_search') }}" placeholder="Search by name or intent">
            </div>

            <div class="wb-stack wb-gap-1">
                <label>Recommended</label>
                <div class="wb-cluster wb-cluster-2">
                    @foreach ($availableBlockTypes->whereIn('slug', $recommendedSlugs)->sortBy('name') as $blockType)
                        <a href="{{ $action }}?{{ http_build_query(array_filter(['page_id' => $block->page_id, 'parent_id' => $block->parent_id, 'block_type_id' => $blockType->id])) }}" class="wb-btn {{ (string) ($selectedBlockType?->id) === (string) $blockType->id ? 'wb-btn-primary' : 'wb-btn-secondary' }}">
                            {{ $labelMap[$blockType->slug] ?? $blockType->name }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-1">
                    <label for="block_type_id_picker">Choose Block Type</label>
                    <select id="block_type_id_picker" name="block_type_id" class="wb-select" required>
                        <option value="">Choose block type</option>
                        @foreach ($groups as $category => $items)
                            <optgroup label="{{ $category }}">
                                @foreach ($items as $blockType)
                                    <option value="{{ $blockType->id }}" @selected((string) ($selectedBlockType?->id) === (string) $blockType->id)>
                                        {{ $labelMap[$blockType->slug] ?? $blockType->name }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>

                <div class="wb-stack wb-gap-1">
                    <label>Selection</label>
                    <div class="wb-card">
                        <div class="wb-card-body">
                            @if ($selectedBlockType)
                                <strong>{{ $labelMap[$selectedBlockType->slug] ?? $selectedBlockType->name }}</strong>
                                <div>{{ $selectedBlockType->description ?: ($selectedBlockType->is_system ? 'This system block renders application data instead of editorial content.' : 'This content block stores editorial fields directly in the block.') }}</div>
                            @else
                                <strong>No block type selected</strong>
                                <div>Choose a content block for authored fields or a system block for application-driven output.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                <div>
                    @if ($selectedBlockType)
                        <span class="wb-status-pill {{ $selectedBlockType->is_system ? 'wb-status-info' : 'wb-status-active' }}">
                            {{ $selectedBlockType->kindLabel() }}
                        </span>
                    @endif
                </div>

                <button type="submit" class="wb-btn wb-btn-primary">Open Block Form</button>
            </div>
        </form>
    </div>
</div>
