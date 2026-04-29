@php
    $pickerSearchTerm = strtolower(trim((string) $pickerSearch));
    $recommendedSlugs = collect(['header', 'plain_text', 'button_link', 'content_header', 'section', 'container', 'cluster']);
    $excludedSlugs = collect(['column_item', 'feature-item', 'link-list-item', 'menu']);
    $expandedBlockQuery = trim((string) request('expanded'));
    $showPickerModal = $isPickerOpen && $slotModalMode !== 'create';

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

    $slotBlockBaseRoute = function (array $parameters = []) use ($page, $slot, $activeLocale) {
        $resolved = $parameters;

        if (! array_key_exists('locale', $resolved) && ! $activeLocale->is_default) {
            $resolved['locale'] = $activeLocale->code;
        }

        return route('admin.pages.slots.blocks', [$page, $slot] + $resolved);
    };

    $closeUrl = $slotBlockRoute();
    $resetUrl = $slotBlockRoute(['picker' => 1]);

    $matchingBlockTypes = $blockTypes
        ->reject(fn ($blockType) => $excludedSlugs->contains($blockType->slug))
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

    $kindLabel = function ($blockType) {
        if (filled($blockType->category)) {
            return strtolower((string) $blockType->category);
        }

        return $blockType->is_system ? 'system' : 'content';
    };

    $kindBadgeClass = function ($blockType) use ($kindLabel) {
        return match ($kindLabel($blockType)) {
            'system' => 'wb-badge-primary',
            'layout' => 'wb-badge-warning',
            default => 'wb-badge-success',
        };
    };

    $descriptionFor = function ($blockType) {
        return $blockType->description
            ?: ($blockType->is_system
                ? 'Configure the system-driven output for this block.'
                : 'Open the editor for this content block.');
    };
@endphp

@if ($showPickerModal)
    <div class="wb-overlay-layer wb-overlay-layer--dialog">
        <div class="wb-overlay-backdrop"></div>

        <div class="wb-modal wb-modal-xl is-open" id="slot-block-picker-modal" role="dialog" aria-modal="true" aria-labelledby="slot-block-picker-title">
            <div class="wb-modal-dialog">
                <div class="wb-modal-header">
                    <div class="wb-stack wb-gap-1">
                        <h2 class="wb-modal-title" id="slot-block-picker-title">Block Types</h2>
                        <span class="wb-text-sm wb-text-muted">Choose a block type, then configure it without leaving the slot editor.</span>
                    </div>

                    <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="Close block types modal">
                        <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                    </a>
                </div>

                <div class="wb-modal-body wb-stack wb-gap-4">
                    <form method="GET" action="{{ route('admin.pages.slots.blocks', [$page, $slot]) }}" class="wb-cluster wb-cluster-between wb-cluster-2">
                        <input type="hidden" name="picker" value="1">
                        @unless ($activeLocale->is_default)
                            <input type="hidden" name="locale" value="{{ $activeLocale->code }}">
                        @endunless
                        @if ($expandedBlockQuery !== '')
                            <input type="hidden" name="expanded" value="{{ $expandedBlockQuery }}">
                        @endif

                        <div class="wb-stack wb-gap-1">
                            <label for="slot_block_type_search">Search block types</label>
                            <input id="slot_block_type_search" name="block_type_search" class="wb-input" type="text" value="{{ $pickerSearch }}" placeholder="Search by name, intent, or slug">
                        </div>

                        <div class="wb-cluster wb-cluster-end wb-cluster-2">
                            <a href="{{ $resetUrl }}" class="wb-btn wb-btn-secondary">Reset</a>
                            <button type="submit" class="wb-btn wb-btn-primary">Search</button>
                        </div>
                    </form>

                    @if ($recommendedBlockTypes->isNotEmpty())
                        <section class="wb-stack wb-gap-2" aria-labelledby="slot-block-picker-recommended-title">
                            <div class="wb-text-sm wb-text-muted" id="slot-block-picker-recommended-title">Recommended</div>
                            <div class="wb-list wb-list-sm">
                                @foreach ($recommendedBlockTypes as $blockType)
                                    <a
                                        href="{{ $slotBlockRoute(['picker' => 1, 'block_type_id' => $blockType->id, 'block_type_search' => $pickerSearch ?: null]) }}"
                                        class="wb-list-item wb-list-item-action"
                                        data-wb-slot-block-link
                                        data-base-url="{{ $slotBlockBaseRoute(['picker' => 1, 'block_type_id' => $blockType->id, 'block_type_search' => $pickerSearch ?: null]) }}"
                                    >
                                        <div class="wb-list-item-text">
                                            <span class="wb-list-item-title">{{ $blockType->name }}</span>
                                            <span class="wb-list-item-sub">{{ $descriptionFor($blockType) }}</span>
                                        </div>
                                        <span class="wb-badge {{ $kindBadgeClass($blockType) }}">{{ $kindLabel($blockType) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <section class="wb-stack wb-gap-2" aria-labelledby="slot-block-picker-all-title">
                        <div class="wb-text-sm wb-text-muted" id="slot-block-picker-all-title">All block types</div>

                        @if ($matchingBlockTypes->isNotEmpty())
                            <div class="wb-list wb-list-sm">
                                @foreach ($matchingBlockTypes as $blockType)
                                    <a
                                        href="{{ $slotBlockRoute(['picker' => 1, 'block_type_id' => $blockType->id, 'block_type_search' => $pickerSearch ?: null]) }}"
                                        class="wb-list-item wb-list-item-action"
                                        data-wb-slot-block-link
                                        data-base-url="{{ $slotBlockBaseRoute(['picker' => 1, 'block_type_id' => $blockType->id, 'block_type_search' => $pickerSearch ?: null]) }}"
                                    >
                                        <div class="wb-list-item-text">
                                            <span class="wb-list-item-title">{{ $blockType->name }}</span>
                                            <span class="wb-list-item-sub">{{ $descriptionFor($blockType) }}</span>
                                        </div>
                                        <span class="wb-badge {{ $kindBadgeClass($blockType) }}">{{ $kindLabel($blockType) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="wb-empty">
                                <div class="wb-empty-title">No matching block types</div>
                                <div class="wb-empty-text">Try a different search term.</div>
                            </div>
                        @endif
                    </section>
                </div>

                <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                    <span class="wb-text-sm wb-text-muted">Select a block type to open its editor.</span>
                    <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary">Close</a>
                </div>
            </div>
        </div>
    </div>
@endif
