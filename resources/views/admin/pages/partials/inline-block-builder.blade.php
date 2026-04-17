@php
    $inlineBlocks = old('blocks')
        ? collect(old('blocks'))->map(function ($submittedBlock) use ($blockTypes, $page) {
            $block = new \App\Models\Block($submittedBlock);
            $block->page_id = $page->id;
            $block->block_type_id = $submittedBlock['block_type_id'] ?? null;
            $block->type = $blockTypes->firstWhere('id', $block->block_type_id)?->slug ?? ($submittedBlock['type'] ?? null);

            return $block;
        })
        : $page->blocks()->with(['blockType', 'slotType', 'asset', 'blockAssets.asset'])->whereNull('parent_id')->orderBy('sort_order')->get();

    $pickerGroups = $blockTypes
        ->where('status', 'published')
        ->sortBy([
            fn ($blockType) => $blockType->category ?: 'content',
            fn ($blockType) => $blockType->sort_order,
            fn ($blockType) => $blockType->name,
        ])
        ->groupBy(fn ($blockType) => $blockType->category ?: 'content');
@endphp

<div class="wb-card wb-card-accent" data-wb-inline-builder>
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
        <strong>Blocks</strong>

        <div class="wb-dropdown wb-dropdown-end">
            <button class="wb-btn wb-btn-primary" type="button" data-wb-toggle="dropdown" data-wb-target="#inline-block-menu" aria-expanded="false">Add Block</button>
            <div class="wb-dropdown-menu" id="inline-block-menu">
                @foreach ($pickerGroups as $category => $items)
                    <div class="wb-dropdown-label">{{ ucfirst($category) }}</div>
                    @foreach ($items as $blockType)
                        @php
                            $blockTypePayload = json_encode([
                                'id' => $blockType->id,
                                'slug' => $blockType->slug,
                                'name' => $blockType->name,
                                'description' => $blockType->description,
                            ], JSON_HEX_APOS | JSON_HEX_QUOT);
                        @endphp
                        <button type="button" class="wb-dropdown-item" data-wb-inline-add data-block-type='{{ $blockTypePayload }}'>{{ $blockType->name }}</button>
                    @endforeach
                    @unless ($loop->last)
                        <hr class="wb-dropdown-divider">
                    @endunless
                @endforeach
            </div>
        </div>
    </div>

    <div class="wb-card-body">
        <div class="wb-stack wb-gap-3" data-wb-inline-list>
            @forelse ($inlineBlocks as $index => $block)
                @include('admin.pages.partials.inline-block-item', [
                    'block' => $block,
                    'index' => $index,
                    'blockTypes' => $blockTypes,
                    'slotTypes' => $slotTypes,
                ])
            @empty
                <div class="wb-empty" data-wb-inline-empty>
                    <div class="wb-empty-title">No blocks yet</div>
                    <div class="wb-empty-text">Add the first block to start composing this page inline.</div>
                </div>
            @endforelse
        </div>
    </div>

    <template data-wb-inline-template>
        <div class="wb-card wb-card-muted" data-wb-inline-block>
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                <div class="wb-stack wb-gap-1">
                    <strong data-wb-inline-label>New Block</strong>
                    <span class="wb-text-sm wb-text-muted">New inline block</span>
                </div>

                <div class="wb-action-group">
                    <button type="button" class="wb-action-btn" data-wb-inline-move="up" title="Move block up" aria-label="Move block up"><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>
                    <button type="button" class="wb-action-btn" data-wb-inline-move="down" title="Move block down" aria-label="Move block down"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
                    <button type="button" class="wb-action-btn" data-wb-inline-toggle title="Collapse block" aria-label="Collapse block"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
                    <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-inline-remove title="Remove block" aria-label="Remove block"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                </div>
            </div>
            <div class="wb-card-body" data-wb-inline-body></div>
        </div>
    </template>

    <input type="hidden" data-wb-default-slot-type value="{{ $slotTypes->first()?->id }}">
    <input type="hidden" data-wb-inline-next-index value="{{ $inlineBlocks->count() }}">
</div>
