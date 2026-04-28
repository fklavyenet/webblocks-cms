@php
    $pickerSearchTerm = strtolower(trim((string) $pickerSearch));
    $recommendedSlugs = collect(['section', 'heading', 'rich-text', 'callout', 'button']);
    $expandedBlockQuery = trim((string) request('expanded'));

    $slotBlockRoute = function (array $parameters = []) use ($page, $slot, $expandedBlockQuery, $activeLocale) {
        $resolved = $parameters;

        if (! array_key_exists('locale', $resolved) && ! $activeLocale->is_default) {
            $resolved['locale'] = $activeLocale->code;
        }

        if ($expandedBlockQuery !== '' && ! array_key_exists('expanded', $resolved)) {
            $resolved['expanded'] = $expandedBlockQuery;
        }

        return route('admin.pages.slots.blocks', [$page, $slot] + $resolved);
    };

    $matchingBlockTypes = $blockTypes
        ->reject(fn ($blockType) => in_array($blockType->slug, ['column_item', 'feature-item', 'link-list-item', 'menu'], true))
        ->filter(function ($blockType) use ($pickerSearchTerm) {
            if ($pickerSearchTerm === '') {
                return true;
            }

            return str_contains(strtolower($blockType->name), $pickerSearchTerm)
                || str_contains(strtolower((string) $blockType->description), $pickerSearchTerm)
                || str_contains(strtolower($blockType->slug), $pickerSearchTerm);
        })
        ->sortBy([
            fn ($blockType) => $blockType->is_system ? 0 : 1,
            fn ($blockType) => $recommendedSlugs->search($blockType->slug) === false ? 1 : 0,
            fn ($blockType) => $blockType->sort_order,
            fn ($blockType) => $blockType->name,
        ])
        ->values();

    $recommendedBlockTypes = $matchingBlockTypes
        ->filter(fn ($blockType) => $recommendedSlugs->contains($blockType->slug))
        ->values();

    $groupedBlockTypes = $matchingBlockTypes
        ->groupBy(function ($blockType) {
            if ($blockType->is_system) {
                return 'System Blocks';
            }

            return 'Content Blocks';
        });
@endphp

@if ($isPickerOpen)
    <div class="wb-stack wb-gap-4">
        <form method="GET" action="{{ route('admin.pages.slots.blocks', [$page, $slot]) }}" class="wb-grid wb-grid-4">
            <input type="hidden" name="picker" value="1">
            @unless ($activeLocale->is_default)
                <input type="hidden" name="locale" value="{{ $activeLocale->code }}">
            @endunless
            @if ($expandedBlockQuery !== '')
                <input type="hidden" name="expanded" value="{{ $expandedBlockQuery }}">
            @endif

            <div class="wb-stack wb-gap-1 wb-slot-block-picker-span-all">
                <label for="slot_block_type_search">Search block types</label>
                <input id="slot_block_type_search" name="block_type_search" class="wb-input" type="text" value="{{ $pickerSearch }}" placeholder="Search by name, intent, or slug">
            </div>

            <div class="wb-cluster wb-cluster-end wb-cluster-2 wb-slot-block-picker-span-all">
                <a href="{{ $slotBlockRoute(['picker' => 1]) }}" class="wb-btn wb-btn-secondary">Reset</a>
                <a href="{{ $slotBlockRoute() }}" class="wb-btn wb-btn-secondary">Close</a>
                <button type="submit" class="wb-btn wb-btn-primary">Search</button>
            </div>
        </form>

        @if ($recommendedBlockTypes->isNotEmpty())
            <div class="wb-stack wb-gap-2">
                <div class="wb-text-sm wb-text-muted">Recommended</div>
                <div class="wb-grid wb-grid-3">
                    @foreach ($recommendedBlockTypes as $blockType)
                        <a href="{{ $slotBlockRoute(['picker' => 1, 'block_type_id' => $blockType->id, 'block_type_search' => $pickerSearch ?: null]) }}" class="wb-card wb-card-accent">
                            <div class="wb-card-body wb-stack wb-gap-1">
                                <strong>{{ $blockType->name }}</strong>
                                <span class="wb-text-sm wb-text-muted">{{ $blockType->description ?: ($blockType->is_system ? 'Configure the system-driven output for this block.' : 'Open the type-specific editor for this content block.') }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @forelse ($groupedBlockTypes as $group => $items)
            <div class="wb-stack wb-gap-2">
                <div class="wb-text-sm wb-text-muted">{{ $group }}</div>
                <div class="wb-grid wb-grid-3">
                    @foreach ($items as $blockType)
                        <a href="{{ $slotBlockRoute(['picker' => 1, 'block_type_id' => $blockType->id, 'block_type_search' => $pickerSearch ?: null]) }}" class="wb-card">
                            <div class="wb-card-body wb-stack wb-gap-1">
                                <div class="wb-cluster wb-cluster-between wb-cluster-2">
                                    <strong>{{ $blockType->name }}</strong>
                                    <span class="wb-status-pill {{ $blockType->is_system ? 'wb-status-info' : 'wb-status-active' }}">{{ $blockType->is_system ? 'system' : 'content' }}</span>
                                </div>
                                <span class="wb-text-sm wb-text-muted">{{ $blockType->description ?: ($blockType->is_system ? 'Configure the system-driven output for this block.' : 'Open the editor for this content block.') }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="wb-empty">
                <div class="wb-empty-title">No matching block types</div>
                <div class="wb-empty-text">Try a different search term.</div>
            </div>
        @endforelse
    </div>
@endif
