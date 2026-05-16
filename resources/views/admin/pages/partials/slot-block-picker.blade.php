@php
    $pickerSearchTerm = strtolower(trim((string) $pickerSearch));
    $pickerTab = trim((string) request('block_type_tab', 'common'));
    $pickerParentId = request()->integer('parent_id') ?: null;
    $allowedPickerTabs = ['common', 'layout', 'content', 'navigation', 'advanced', 'all'];
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

    $eligibleBlockTypes = ($pickerBlockTypes ?? $blockTypes)->values();

    $matchesSearch = function ($blockType) use ($pickerSearchTerm) {
        if ($pickerSearchTerm === '') {
            return true;
        }

        return str_contains(strtolower($blockType->name), $pickerSearchTerm)
            || str_contains(strtolower((string) $blockType->description), $pickerSearchTerm)
            || str_contains(strtolower((string) $blockType->category), $pickerSearchTerm)
            || str_contains(strtolower($blockType->slug), $pickerSearchTerm);
    };

    $sortBlockTypes = function ($blockTypes) {
        return $blockTypes->sort(function ($left, $right) {
            $compare = static fn ($a, $b) => $a <=> $b;

            return $compare(strtolower($left->name), strtolower($right->name))
                ?: $compare($left->sort_order, $right->sort_order);
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
    $matchingBlockTypes = $sortBlockTypes($eligibleBlockTypes->filter($matchesSearch));

    $visibleBlockTypes = $showSearchResults
        ? $matchingBlockTypes
        : ($tabBlockTypes->get($pickerTab) ?? collect());
    $pickerBlockTypeCount = $showSearchResults ? $matchingBlockTypes->count() : $eligibleBlockTypes->count();

    $activeTab = $showSearchResults ? 'all' : $pickerTab;
    $pickerClientTab = $activeTab;

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

    $tabUrl = function (string $tabKey) use ($slotBlockRoute, $pickerParentId, $pickerSearch) {
        return $slotBlockRoute([
            'picker' => 1,
            'parent_id' => $pickerParentId ?: null,
            'block_type_tab' => $tabKey !== 'common' ? $tabKey : null,
            'block_type_search' => $pickerSearch ?: null,
        ]);
    };

    $pickerStateRouteParams = [
        'picker' => 1,
        'parent_id' => $pickerParentId ?: null,
        'block_type_tab' => $pickerTab !== 'common' ? $pickerTab : null,
        'block_type_search' => $pickerSearch ?: null,
    ];

    $showPickerReset = $pickerSearch !== ''
        || $pickerTab !== 'common';
@endphp

@if ($showPickerModal)
    <div class="wb-modal wb-modal-xl wb-slot-block-picker-modal" id="slot-block-picker-modal" role="dialog" aria-modal="true" aria-labelledby="slot-block-picker-title" data-wb-admin-close-url="{{ $closeUrl }}" data-wb-admin-autoload-overlay hidden>
            <div class="wb-modal-dialog wb-slot-block-picker-dialog">
                <div class="wb-modal-header">
                    <div class="wb-stack wb-gap-1">
                        <div class="wb-cluster wb-cluster-2 wb-flex-wrap wb-items-center">
                            <h2 class="wb-modal-title" id="slot-block-picker-title">Block Types</h2>
                            <span class="wb-status-pill wb-status-info" data-slot-block-picker-count>{{ $pickerBlockTypeCount }}</span>
                        </div>
                        <span class="wb-text-sm wb-text-muted">Choose a block type, then configure it without leaving the slot editor.@if ($pickerParentBlock) Showing block types allowed inside {{ $pickerParentBlock->typeName() }}.@endif</span>
                    </div>

                    <a href="{{ $closeUrl }}" class="wb-modal-close" data-wb-dismiss="modal" aria-label="Close block types modal">
                        <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                    </a>
                </div>

                <div class="wb-modal-body wb-stack wb-gap-4 wb-slot-block-picker-body">
                    @include('admin.partials.listing-filters', [
                        'action' => $slotBlockRoute(),
                        'search' => [
                            'id' => 'slot_block_type_search',
                            'name' => 'block_type_search',
                            'label' => 'Search block types',
                            'value' => $pickerSearch,
                            'placeholder' => 'Search by name, intent, or slug',
                        ],
                        'selects' => [],
                        'hidden' => [
                            'picker' => 1,
                            'locale' => $activeLocale->is_default ? null : $activeLocale->code,
                            'parent_id' => $pickerParentId,
                        ],
                        'showReset' => $showPickerReset,
                        'resetUrl' => $resetUrl,
                        'applyLabel' => 'Apply',
                    ])

                    <input type="hidden" name="block_type_tab" value="{{ $pickerClientTab !== 'common' ? $pickerClientTab : 'common' }}" data-wb-slot-block-picker-tab-input>

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

                        @if ($visibleBlockTypes->isNotEmpty())
                            <div class="wb-table-wrap wb-slot-block-picker-table-wrap">
                                <table class="wb-table wb-table-striped wb-table-hover">
                                    <colgroup>
                                        <col style="width: 18rem;">
                                        <col style="width: 9rem;">
                                        <col>
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th class="wb-nowrap">Name</th>
                                            <th>Category</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($visibleBlockTypes as $blockType)
                                            <tr data-block-type-slug="{{ $blockType->slug }}">
                                                <td class="wb-nowrap">
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
                                <div class="wb-empty-title">{{ $tabDefinitions[$activeTab]['emptyTitle'] }}</div>
                                <div class="wb-empty-text">{{ $tabDefinitions[$activeTab]['emptyText'] }}</div>
                            </div>
                        @endif
                    @else
                        <div class="wb-tabs" data-wb-tabs data-wb-slot-block-picker-tabs>
                            <div class="wb-tabs-nav" role="tablist" aria-label="Block type catalog groups">
                                @foreach ($tabBlockTypes as $tabKey => $blockTypes)
                                    <button
                                        type="button"
                                        id="slot-block-picker-tab-{{ $tabKey }}"
                                        aria-selected="{{ $pickerClientTab === $tabKey ? 'true' : 'false' }}"
                                        class="wb-tabs-btn {{ $activeTab === $tabKey ? 'is-active' : '' }}"
                                        data-wb-tab="slot-block-picker-panel-{{ $tabKey }}"
                                        data-wb-slot-block-picker-tab="{{ $tabKey }}"
                                        @if ($pickerClientTab !== $tabKey) tabindex="-1" @endif
                                    >
                                        {{ $tabDefinitions[$tabKey]['label'] }}
                                    </button>
                                @endforeach
                            </div>

                            <div class="wb-tabs-panels">
                                @foreach ($tabBlockTypes as $tabKey => $blockTypes)
                                    <div class="wb-tabs-panel {{ $pickerClientTab === $tabKey ? 'is-active' : '' }} wb-stack wb-gap-0" id="slot-block-picker-panel-{{ $tabKey }}" @if ($pickerClientTab !== $tabKey) hidden aria-hidden="true" @else aria-hidden="false" @endif>
                                        @if ($blockTypes->isNotEmpty())
                                            <div class="wb-table-wrap wb-slot-block-picker-table-wrap">
                                                <table class="wb-table wb-table-striped wb-table-hover">
                                                    <colgroup>
                                                        <col style="width: 18rem;">
                                                        <col style="width: 9rem;">
                                                        <col>
                                                    </colgroup>
                                                    <thead>
                                                        <tr>
                                                            <th class="wb-nowrap">Name</th>
                                                            <th>Category</th>
                                                            <th>Description</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($blockTypes as $blockType)
                                                            <tr data-block-type-slug="{{ $blockType->slug }}">
                                                                <td class="wb-nowrap">
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
                                                <div class="wb-empty-title">{{ $tabDefinitions[$tabKey]['emptyTitle'] }}</div>
                                                <div class="wb-empty-text">{{ $tabDefinitions[$tabKey]['emptyText'] }}</div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
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
@endif
