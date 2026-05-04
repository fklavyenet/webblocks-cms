@php
    $pickerSearchTerm = strtolower(trim((string) $pickerSearch));
    $pickerCategory = filled($pickerCategory ?? null) ? trim((string) $pickerCategory) : null;
    $pickerSort = trim((string) request('block_type_sort', 'default'));
    $pickerParentId = request()->integer('parent_id') ?: null;
    $allowedPickerSorts = ['default', 'name', 'category'];
    if (! in_array($pickerSort, $allowedPickerSorts, true)) {
        $pickerSort = 'default';
    }
    $showPickerModal = $isPickerOpen && $slotModalMode !== 'create';

    $slotBlockRoute = function (array $parameters = []) use ($page, $slot, $activeLocale) {
        $resolved = $parameters;

        if (! array_key_exists('locale', $resolved) && ! $activeLocale->is_default) {
            $resolved['locale'] = $activeLocale->code;
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
    $resetUrl = $slotBlockRoute(['picker' => 1, 'parent_id' => $pickerParentId ?: null]);

    $categoryLabels = [
        'content' => 'Content',
        'layout' => 'Layout',
        'pattern' => 'Pattern',
        'navigation' => 'Navigation',
        'legacy' => 'Legacy',
    ];

    $categoryOrder = [
        'content' => 0,
        'layout' => 1,
        'pattern' => 2,
        'navigation' => 3,
        'legacy' => 4,
    ];

    $availableCategories = ($pickerBlockTypes ?? $blockTypes)
        ->map(fn ($blockType) => trim((string) ($blockType->category ?? '')))
        ->filter()
        ->unique()
        ->sort(function ($left, $right) use ($categoryOrder) {
            $leftKey = strtolower($left);
            $rightKey = strtolower($right);
            $leftRank = $categoryOrder[$leftKey] ?? 100;
            $rightRank = $categoryOrder[$rightKey] ?? 100;

            return $leftRank <=> $rightRank ?: $leftKey <=> $rightKey;
        })
        ->values();

    $matchingBlockTypes = ($pickerBlockTypes ?? $blockTypes)
        ->filter(function ($blockType) use ($pickerSearchTerm, $pickerCategory) {
            if ($pickerCategory !== null && (string) ($blockType->category ?? '') !== $pickerCategory) {
                return false;
            }

            if ($pickerSearchTerm === '') {
                return true;
            }

            return str_contains(strtolower($blockType->name), $pickerSearchTerm)
                || str_contains(strtolower((string) $blockType->description), $pickerSearchTerm)
                || str_contains(strtolower((string) $blockType->category), $pickerSearchTerm)
                || str_contains(strtolower($blockType->slug), $pickerSearchTerm);
        })
        ->sort(function ($left, $right) use ($pickerSort) {
            $compare = static fn ($a, $b) => $a <=> $b;

            return match ($pickerSort) {
                'name' => $compare(strtolower($left->name), strtolower($right->name))
                    ?: $compare($left->sort_order, $right->sort_order),
                'category' => $compare(strtolower((string) ($left->category ?? '')), strtolower((string) ($right->category ?? '')))
                    ?: $compare($left->sort_order, $right->sort_order)
                    ?: $compare(strtolower($left->name), strtolower($right->name)),
                default => $compare($left->sort_order, $right->sort_order)
                    ?: $compare(strtolower($left->name), strtolower($right->name)),
            };
        })
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

    $categoryDisplay = function (?string $category) use ($categoryLabels) {
        $resolved = strtolower(trim((string) $category));

        if ($resolved === '') {
            return 'Other';
        }

        return $categoryLabels[$resolved] ?? str($resolved)->replace('-', ' ')->title()->toString();
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
                        <span class="wb-text-sm wb-text-muted">Choose a block type, then configure it without leaving the slot editor.@if ($pickerParentBlock) Showing block types allowed inside {{ $pickerParentBlock->typeName() }}.@endif</span>
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
                        @if ($pickerParentId)
                            <input type="hidden" name="parent_id" value="{{ $pickerParentId }}">
                        @endif

                        <div class="wb-stack wb-gap-1">
                            <label for="slot_block_type_search">Search block types</label>
                            <input id="slot_block_type_search" name="block_type_search" class="wb-input" type="text" value="{{ $pickerSearch }}" placeholder="Search by name, intent, or slug">
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="slot_block_type_category">Category</label>
                            <select id="slot_block_type_category" name="block_type_category" class="wb-select">
                                <option value="">All categories</option>
                                @foreach ($availableCategories as $category)
                                    <option value="{{ $category }}" @selected($pickerCategory === $category)>{{ $categoryDisplay($category) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="slot_block_type_sort">Sort</label>
                            <select id="slot_block_type_sort" name="block_type_sort" class="wb-select">
                                <option value="default" @selected($pickerSort === 'default')>Default order</option>
                                <option value="name" @selected($pickerSort === 'name')>Name A-Z</option>
                                <option value="category" @selected($pickerSort === 'category')>Category</option>
                            </select>
                        </div>

                        <div class="wb-cluster wb-cluster-end wb-cluster-2">
                            <a href="{{ $resetUrl }}" class="wb-btn wb-btn-secondary">Reset</a>
                            <button type="submit" class="wb-btn wb-btn-primary">Search</button>
                        </div>
                    </form>

                    @if ($matchingBlockTypes->isNotEmpty())
                        <div class="wb-table-wrap">
                            <table class="wb-table wb-table-striped wb-table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($matchingBlockTypes as $blockType)
                                        <tr>
                                            <td>
                                                <a
                                                    href="{{ $slotBlockRoute(['picker' => 1, 'parent_id' => $pickerParentId ?: null, 'block_type_id' => $blockType->id, 'block_type_search' => $pickerSearch ?: null, 'block_type_category' => $pickerCategory, 'block_type_sort' => $pickerSort !== 'default' ? $pickerSort : null]) }}"
                                                    class="wb-link"
                                                    data-wb-slot-block-link
                                                    data-base-url="{{ $slotBlockBaseRoute(['picker' => 1, 'parent_id' => $pickerParentId ?: null, 'block_type_id' => $blockType->id, 'block_type_search' => $pickerSearch ?: null, 'block_type_category' => $pickerCategory, 'block_type_sort' => $pickerSort !== 'default' ? $pickerSort : null]) }}"
                                                >
                                                    <strong>{{ $blockType->name }}</strong>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="wb-badge {{ $kindBadgeClass($blockType) }}">{{ $kindLabel($blockType) }}</span>
                                            </td>
                                            <td>{{ $descriptionFor($blockType) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="wb-empty">
                            <div class="wb-empty-title">No matching block types</div>
                            <div class="wb-empty-text">Try a different search term or category.</div>
                        </div>
                    @endif
                </div>

                <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                    <span class="wb-text-sm wb-text-muted">Select a block type to open its editor.</span>
                    <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary">Close</a>
                </div>
            </div>
        </div>
    </div>
@endif
