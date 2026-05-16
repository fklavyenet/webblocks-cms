@php
    $pickerSearchTerm = strtolower(trim((string) $pickerSearch));
    $pickerCategory = filled($pickerCategory ?? null) ? trim((string) $pickerCategory) : null;
    $pickerSort = trim((string) request('block_type_sort', 'default'));
    $pickerTab = trim((string) request('block_type_tab', 'common'));
    $pickerParentId = request()->integer('parent_id') ?: null;
    $allowedPickerSorts = ['default', 'name', 'category'];
    $allowedPickerTabs = ['common', 'layout', 'content', 'navigation', 'advanced', 'all'];
    if (! in_array($pickerSort, $allowedPickerSorts, true)) {
        $pickerSort = 'default';
    }
    if (! in_array($pickerTab, $allowedPickerTabs, true)) {
        $pickerTab = 'common';
    }
    $showPickerModal = $isPickerOpen && $slotModalMode !== 'create';

    $slotBlockRoute = $slotBlockRoute ?? function (array $parameters = []) use ($page, $slot, $activeLocale) {
        $resolved = $parameters;

        if (! array_key_exists('locale', $resolved) && ! $activeLocale->is_default) {
            $resolved['locale'] = $activeLocale->code;
        }

        return route('admin.pages.slots.blocks', [$page, $slot] + $resolved);
    };

    $slotBlockBaseRoute = $slotBlockBaseRoute ?? function (array $parameters = []) use ($page, $slot, $activeLocale) {
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
        'advanced' => 'Advanced',
        'legacy' => 'Legacy',
    ];

    $categoryOrder = [
        'content' => 0,
        'layout' => 1,
        'pattern' => 2,
        'navigation' => 3,
        'advanced' => 4,
        'legacy' => 5,
    ];

    $eligibleBlockTypes = ($pickerBlockTypes ?? $blockTypes)->values();

    $availableCategories = $eligibleBlockTypes
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

    $matchesSearch = function ($blockType) use ($pickerSearchTerm) {
        if ($pickerSearchTerm === '') {
            return true;
        }

        return str_contains(strtolower($blockType->name), $pickerSearchTerm)
            || str_contains(strtolower((string) $blockType->description), $pickerSearchTerm)
            || str_contains(strtolower((string) $blockType->category), $pickerSearchTerm)
            || str_contains(strtolower($blockType->slug), $pickerSearchTerm);
    };

    $sortBlockTypes = function ($blockTypes) use ($pickerSort) {
        return $blockTypes->sort(function ($left, $right) use ($pickerSort) {
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
    };

    $slugMatches = function ($blockType, array $slugs): bool {
        $normalizedSlug = strtolower(str_replace('_', '-', (string) $blockType->slug));

        return in_array($normalizedSlug, $slugs, true);
    };

    $commonSlugs = ['header', 'rich-text', 'text', 'button', 'button-link', 'image', 'card', 'code', 'table', 'quote', 'alert'];
    $layoutSlugs = ['section', 'container', 'grid', 'cluster', 'card'];
    $contentSlugs = ['header', 'text', 'plain-text', 'rich-text', 'button', 'button-link', 'code', 'table', 'quote', 'alert', 'stat-card', 'image', 'gallery', 'file', 'video', 'audio', 'map'];
    $navigationSlugs = ['link-list', 'link-list-item', 'toc', 'breadcrumb', 'header-actions', 'sticky-navbar', 'navbar-brand', 'navbar-navigation', 'sidebar-brand', 'sidebar-navigation', 'sidebar-nav-group', 'sidebar-nav-item', 'sidebar-footer', 'search-form', 'navigation-auto', 'menu'];
    $advancedSlugs = ['html'];

    $tabDefinitions = collect([
        'common' => [
            'label' => 'Common',
            'filter' => fn ($blockType) => $slugMatches($blockType, $commonSlugs),
            'emptyTitle' => 'No common block types',
            'emptyText' => 'No common shortcuts are available for this picker context.',
        ],
        'layout' => [
            'label' => 'Layout',
            'filter' => fn ($blockType) => strtolower((string) ($blockType->category ?? '')) === 'layout' || $slugMatches($blockType, $layoutSlugs),
            'emptyTitle' => 'No layout block types',
            'emptyText' => 'No layout or container block types are eligible here.',
        ],
        'content' => [
            'label' => 'Content',
            'filter' => fn ($blockType) => strtolower((string) ($blockType->category ?? '')) === 'content' || $slugMatches($blockType, $contentSlugs),
            'emptyTitle' => 'No content block types',
            'emptyText' => 'No editorial content block types are eligible here.',
        ],
        'navigation' => [
            'label' => 'Navigation',
            'filter' => fn ($blockType) => strtolower((string) ($blockType->category ?? '')) === 'navigation' || $slugMatches($blockType, $navigationSlugs),
            'emptyTitle' => 'No navigation block types',
            'emptyText' => 'No navigation or docs-shell utility blocks are eligible here.',
        ],
        'advanced' => [
            'label' => 'Advanced',
            'filter' => fn ($blockType) => strtolower((string) ($blockType->category ?? '')) === 'advanced' || $slugMatches($blockType, $advancedSlugs),
            'emptyTitle' => 'No advanced block types',
            'emptyText' => 'No advanced block types are eligible here.',
        ],
        'all' => [
            'label' => 'All',
            'filter' => fn () => true,
            'emptyTitle' => 'No block types',
            'emptyText' => 'No block types are eligible for this picker context.',
        ],
    ]);

    $tabBlockTypes = $tabDefinitions
        ->map(function (array $definition) use ($eligibleBlockTypes, $sortBlockTypes) {
            return $sortBlockTypes($eligibleBlockTypes->filter($definition['filter']));
        })
        ->filter(fn ($blockTypes, $tabKey) => $tabKey !== 'advanced' || $blockTypes->isNotEmpty());

    if (! $tabBlockTypes->has($pickerTab)) {
        $pickerTab = 'common';
    }

    $showSearchResults = $pickerSearchTerm !== '';
    $matchingBlockTypes = $sortBlockTypes(
        $eligibleBlockTypes
            ->filter($matchesSearch)
            ->when($showSearchResults && $pickerCategory !== null, fn ($blockTypes) => $blockTypes->filter(fn ($blockType) => (string) ($blockType->category ?? '') === $pickerCategory))
    );

    $visibleBlockTypes = $showSearchResults
        ? $matchingBlockTypes
        : ($tabBlockTypes->get($pickerTab) ?? collect());

    $activeTab = $showSearchResults ? 'all' : $pickerTab;

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
            'advanced' => 'wb-badge-primary',
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

    $tabUrl = function (string $tabKey) use ($slotBlockRoute, $pickerParentId, $pickerSearch, $pickerSort) {
        return $slotBlockRoute([
            'picker' => 1,
            'parent_id' => $pickerParentId ?: null,
            'block_type_tab' => $tabKey !== 'common' ? $tabKey : null,
            'block_type_search' => $pickerSearch ?: null,
            'block_type_sort' => $pickerSort !== 'default' ? $pickerSort : null,
        ]);
    };

    $pickerStateRouteParams = [
        'picker' => 1,
        'parent_id' => $pickerParentId ?: null,
        'block_type_tab' => $pickerTab !== 'common' ? $pickerTab : null,
        'block_type_search' => $pickerSearch ?: null,
        'block_type_sort' => $pickerSort !== 'default' ? $pickerSort : null,
        'block_type_category' => $showSearchResults && $pickerCategory !== null ? $pickerCategory : null,
    ];
@endphp

@if ($showPickerModal)
    <div class="wb-overlay-layer wb-overlay-layer--dialog">
        <div class="wb-overlay-backdrop"></div>

        <div class="wb-modal wb-modal-xl is-open" id="slot-block-picker-modal" role="dialog" aria-modal="true" aria-labelledby="slot-block-picker-title" data-wb-admin-close-url="{{ $closeUrl }}">
            <div class="wb-modal-dialog">
                <div class="wb-modal-header">
                    <div class="wb-stack wb-gap-1">
                        <h2 class="wb-modal-title" id="slot-block-picker-title">Block Types</h2>
                        <span class="wb-text-sm wb-text-muted">Choose a block type, then configure it without leaving the slot editor.@if ($pickerParentBlock) Showing block types allowed inside {{ $pickerParentBlock->typeName() }}.@endif</span>
                    </div>

                    <a href="{{ $closeUrl }}" class="wb-modal-close" data-wb-dismiss="modal" aria-label="Close block types modal">
                        <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                    </a>
                </div>

                <div class="wb-modal-body wb-stack wb-gap-4">
                    @include('admin.partials.listing-filters', [
                        'action' => $slotBlockRoute(),
                        'search' => [
                            'id' => 'slot_block_type_search',
                            'name' => 'block_type_search',
                            'label' => 'Search block types',
                            'value' => $pickerSearch,
                            'placeholder' => 'Search by name, intent, or slug',
                        ],
                        'selects' => [
                            ...($showSearchResults ? [[
                                'id' => 'slot_block_type_category',
                                'name' => 'block_type_category',
                                'label' => 'Category',
                                'selected' => $pickerCategory,
                                'placeholder' => 'All categories',
                                'options' => $availableCategories
                                    ->mapWithKeys(fn ($category) => [$category => $categoryDisplay($category)])
                                    ->all(),
                            ]] : []),
                            [
                                'id' => 'slot_block_type_sort',
                                'name' => 'block_type_sort',
                                'label' => 'Sort',
                                'selected' => $pickerSort,
                                'options' => [
                                    'default' => 'Default order',
                                    'name' => 'Name A-Z',
                                    'category' => 'Category',
                                ],
                            ],
                        ],
                        'hidden' => [
                            'picker' => 1,
                            'locale' => $activeLocale->is_default ? null : $activeLocale->code,
                            'parent_id' => $pickerParentId,
                            'block_type_tab' => $pickerTab !== 'common' ? $pickerTab : null,
                        ],
                        'showReset' => true,
                        'resetUrl' => $resetUrl,
                        'applyLabel' => 'Search',
                        'resetFirst' => true,
                    ])

                    @if ($showSearchResults)
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                                <div class="wb-stack wb-gap-1">
                                    <strong>Search results</strong>
                                    <span class="wb-text-sm wb-text-muted">Showing matches across the full eligible catalog.</span>
                                </div>
                                <span class="wb-text-sm wb-text-muted">{{ $matchingBlockTypes->count() }} result{{ $matchingBlockTypes->count() === 1 ? '' : 's' }}</span>
                            </div>
                        </div>
                    @else
                        <div class="wb-tabs" data-wb-tabs>
                            <div class="wb-tabs-nav" role="tablist" aria-label="Block type catalog groups">
                                @foreach ($tabBlockTypes as $tabKey => $blockTypes)
                                    <a
                                        href="{{ $tabUrl($tabKey) }}"
                                        id="slot-block-picker-tab-{{ $tabKey }}"
                                        role="tab"
                                        aria-controls="slot-block-picker-panel-{{ $tabKey }}"
                                        aria-selected="{{ $activeTab === $tabKey ? 'true' : 'false' }}"
                                        class="wb-tabs-btn {{ $activeTab === $tabKey ? 'is-active' : '' }}"
                                    >
                                        {{ $tabDefinitions[$tabKey]['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($visibleBlockTypes->isNotEmpty())
                        <div class="wb-table-wrap" @if (! $showSearchResults) role="tabpanel" id="slot-block-picker-panel-{{ $activeTab }}" aria-labelledby="slot-block-picker-tab-{{ $activeTab }}" @endif>
                            <table class="wb-table wb-table-striped wb-table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($visibleBlockTypes as $blockType)
                                        <tr data-block-type-slug="{{ $blockType->slug }}">
                                            <td>
                                                <a
                                                    href="{{ $slotBlockRoute($pickerStateRouteParams + ['block_type_id' => $blockType->id]) }}"
                                                    class="wb-link"
                                                    data-wb-slot-block-link
                                                    data-base-url="{{ $slotBlockBaseRoute($pickerStateRouteParams + ['block_type_id' => $blockType->id]) }}"
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
                            <div class="wb-empty-title">{{ $showSearchResults ? 'No matching block types' : $tabDefinitions[$activeTab]['emptyTitle'] }}</div>
                            <div class="wb-empty-text">{{ $showSearchResults ? 'Try a different search term or category.' : $tabDefinitions[$activeTab]['emptyText'] }}</div>
                        </div>
                    @endif
                </div>

                <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                    <div class="wb-flex wb-items-center wb-gap-3 wb-flex-wrap">
                        <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">Close</a>
                    </div>
                    <span class="wb-text-sm wb-text-muted">Select a block type to open its editor.</span>
                </div>
            </div>
        </div>
    </div>
@endif
