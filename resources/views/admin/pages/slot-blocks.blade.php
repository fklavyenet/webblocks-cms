@php
    $slotTitle = 'Edit Slot: '.($slot->slotType?->name ?? 'Slot').' ('.$page->title.')';
    $activePreviewUrl = $page->isPublished() ? $page->publicUrl($activeLocale->code) : null;
    $pagesIndexUrl = route('admin.pages.index', ['site' => $page->site_id]);
    $siteName = $page->site?->name ?? 'Site';
@endphp

@extends('layouts.admin', ['title' => $slotTitle, 'heading' => $slotTitle])

@section('content')
    @php
        $expandedBlockQuery = $expandedBlockIds->implode(',');

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
            if (! array_key_exists('locale', $parameters) && ! $activeLocale->is_default) {
                $parameters['locale'] = $activeLocale->code;
            }

            return route('admin.pages.slots.blocks', [$page, $slot] + $parameters);
        };
    @endphp

    @include('admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="Breadcrumb"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pagesIndexUrl.'">Pages</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pagesIndexUrl.'">'.$siteName.'</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.pages.edit', $page).'">'.$page->title.'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.($slot->slotType?->name ?? 'Slot').'</span></li></ol></nav>',
        'title' => $slotTitle,
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.pages.edit', $page).'" class="wb-btn wb-btn-secondary">Back to Page Slots</a>'.($activePreviewUrl ? '<a href="'.$activePreviewUrl.'" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer"><i class="wb-icon wb-icon-globe" aria-hidden="true"></i> <span>View Page</span></a>' : '').'</div>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <div class="wb-stack wb-gap-1">
                <strong>Blocks</strong>
                <span class="wb-text-sm wb-text-muted">Editing content for {{ strtoupper($activeLocale->code) }}. Structure, ordering, and shared block config remain canonical.</span>
            </div>
            <a href="{{ $slotBlockRoute(['picker' => 1]) }}" class="wb-btn wb-btn-secondary" data-wb-slot-block-link data-base-url="{{ $slotBlockBaseRoute(['picker' => 1]) }}">Add Block</a>
        </div>

        <div class="wb-card-body wb-border-b">
            <div class="wb-cluster wb-cluster-between wb-cluster-2">
                <div class="wb-cluster wb-cluster-2">
                    @foreach ($availableLocales as $translationStatus)
                        @php
                            $locale = $translationStatus['locale'];
                            $isActiveLocale = $locale->id === $activeLocale->id;
                        @endphp
                        <a href="{{ $slotBlockRoute(['locale' => $locale->code, 'edit' => request('edit'), 'picker' => request()->boolean('picker') ? 1 : null, 'block_type_id' => request('block_type_id'), 'block_type_search' => request('block_type_search')]) }}" class="wb-btn {{ $isActiveLocale ? 'wb-btn-primary' : 'wb-btn-secondary' }}">
                            {{ strtoupper($locale->code) }}
                        </a>
                    @endforeach
                </div>
                <span class="wb-text-sm wb-text-muted">Page route translation and block content translation are edited separately.</span>
            </div>
        </div>

        @if ($blocks->isEmpty())
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">No blocks in this slot yet</div>
                    <div class="wb-empty-text">Use Add Block to start populating this slot.</div>
                </div>
            </div>
        @else
            <div class="wb-card-body">
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover" data-wb-slot-block-table>
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Block Type</th>
                                <th>Summary</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        @foreach ($blocks as $block)
                            @php
                                $childCount = $block->children->count();
                                $isExpanded = $expandedBlockIds->contains($block->id);
                                $groupId = 'slot-block-children-'.$block->id;
                                $blockExpandedQuery = collect([$block->id])->merge($expandedBlockIds)->unique()->implode(',');
                            @endphp

                            <tbody>
                                <tr>
                                    <td>{{ $block->sort_order }}</td>
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <div class="wb-cluster wb-cluster-2">
                                                @if ($childCount > 0)
                                                    <button
                                                        type="button"
                                                        class="wb-action-btn wb-slot-block-toggle"
                                                        data-wb-slot-block-toggle
                                                        data-wb-target="{{ $groupId }}"
                                                        aria-controls="{{ $groupId }}"
                                                        aria-expanded="{{ $isExpanded ? 'true' : 'false' }}"
                                                        aria-label="{{ $isExpanded ? 'Collapse child blocks' : 'Expand child blocks' }}"
                                                        title="{{ $isExpanded ? 'Collapse child blocks' : 'Expand child blocks' }}"
                                                    >
                                                        <i class="wb-icon wb-icon-chevron-down wb-slot-block-toggle-icon" aria-hidden="true"></i>
                                                    </button>
                                                @endif

                                                <strong>{{ $block->typeName() }}</strong>
                                            </div>

                                            <span class="wb-text-sm wb-text-muted">
                                                {{ $block->is_system ? 'System block' : 'Visitor-facing block' }}
                                                @if ($childCount > 0)
                                                    | Children: {{ $childCount }} {{ \Illuminate\Support\Str::plural('item', $childCount) }}
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <a href="{{ $slotBlockRoute(['edit' => $block->id]) }}" data-wb-slot-block-link data-base-url="{{ $slotBlockBaseRoute(['edit' => $block->id]) }}"><strong>{{ $block->editorLabel() }}</strong></a>
                                            @if ($block->editorSummary())
                                                <span class="wb-text-sm wb-text-muted">{{ $block->editorSummary() }}</span>
                                            @endif
                                            @php($translationStatus = $block->translationStatus($activeLocale))
                                            <span class="wb-text-sm wb-text-muted">{{ $translationStatus['label'] }}{{ $translationStatus['state'] === 'fallback' ? ' from '.strtoupper($translationStatus['resolved_locale']->code) : '' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="wb-status-pill {{ $block->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">
                                            {{ $block->status }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="wb-action-group">
                                            <form method="POST" action="{{ route('admin.blocks.move-up', $block) }}">
                                                @csrf
                                                <input type="hidden" name="expanded" value="{{ $expandedBlockQuery }}" data-wb-slot-block-expanded-input>
                                                @unless ($activeLocale->is_default)
                                                    <input type="hidden" name="locale" value="{{ $activeLocale->code }}">
                                                @endunless
                                                <button type="submit" class="wb-action-btn" title="Move block up" aria-label="Move block up"><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.blocks.move-down', $block) }}">
                                                @csrf
                                                <input type="hidden" name="expanded" value="{{ $expandedBlockQuery }}" data-wb-slot-block-expanded-input>
                                                @unless ($activeLocale->is_default)
                                                    <input type="hidden" name="locale" value="{{ $activeLocale->code }}">
                                                @endunless
                                                <button type="submit" class="wb-action-btn" title="Move block down" aria-label="Move block down"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
                                            </form>
                                            <a href="{{ $slotBlockRoute(['edit' => $block->id]) }}" class="wb-action-btn wb-action-btn-edit" title="Edit block" aria-label="Edit block" data-wb-slot-block-link data-base-url="{{ $slotBlockBaseRoute(['edit' => $block->id]) }}"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                                            <form method="POST" action="{{ route('admin.blocks.destroy', $block) }}" onsubmit="return confirm('Delete this block?');">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="expanded" value="{{ $expandedBlockQuery }}" data-wb-slot-block-expanded-input>
                                                @unless ($activeLocale->is_default)
                                                    <input type="hidden" name="locale" value="{{ $activeLocale->code }}">
                                                @endunless
                                                <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete block" aria-label="Delete block"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>

                            @if ($childCount > 0)
                                <tbody id="{{ $groupId }}" data-wb-slot-block-children="{{ $groupId }}" @if (! $isExpanded) hidden @endif>
                                    @foreach ($block->children as $child)
                                        <tr>
                                            <td>{{ $block->sort_order }}.{{ $child->sort_order + 1 }}</td>
                                            <td>
                                                <div class="wb-stack wb-gap-1 wb-slot-block-child-meta">
                                                    <strong>{{ $child->typeName() }}</strong>
                                                    <span class="wb-text-sm wb-text-muted">Child of {{ $block->editorLabel() }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="wb-stack wb-gap-1 wb-slot-block-child-summary">
                                                    <a href="{{ $slotBlockRoute(['edit' => $child->id, 'expanded' => $blockExpandedQuery]) }}" data-wb-slot-block-link data-base-url="{{ $slotBlockBaseRoute(['edit' => $child->id]) }}"><strong>{{ $child->editorLabel() }}</strong></a>
                                                    @if ($child->editorSummary())
                                                        <span class="wb-text-sm wb-text-muted">{{ $child->editorSummary() }}</span>
                                                    @endif
                                                    @php($childTranslationStatus = $child->translationStatus($activeLocale))
                                                    <span class="wb-text-sm wb-text-muted">{{ $childTranslationStatus['label'] }}{{ $childTranslationStatus['state'] === 'fallback' ? ' from '.strtoupper($childTranslationStatus['resolved_locale']->code) : '' }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="wb-status-pill {{ $child->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">
                                                    {{ $child->status }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="wb-action-group">
                                                    <form method="POST" action="{{ route('admin.blocks.move-up', $child) }}">
                                                        @csrf
                                                        <input type="hidden" name="expanded" value="{{ $blockExpandedQuery }}" data-wb-slot-block-expanded-input>
                                                        @unless ($activeLocale->is_default)
                                                            <input type="hidden" name="locale" value="{{ $activeLocale->code }}">
                                                        @endunless
                                                        <button type="submit" class="wb-action-btn" title="Move child block up" aria-label="Move child block up"><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.blocks.move-down', $child) }}">
                                                        @csrf
                                                        <input type="hidden" name="expanded" value="{{ $blockExpandedQuery }}" data-wb-slot-block-expanded-input>
                                                        @unless ($activeLocale->is_default)
                                                            <input type="hidden" name="locale" value="{{ $activeLocale->code }}">
                                                        @endunless
                                                        <button type="submit" class="wb-action-btn" title="Move child block down" aria-label="Move child block down"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
                                                    </form>
                                                    <a href="{{ $slotBlockRoute(['edit' => $child->id, 'expanded' => $blockExpandedQuery]) }}" class="wb-action-btn wb-action-btn-edit" title="Edit child block" aria-label="Edit child block" data-wb-slot-block-link data-base-url="{{ $slotBlockBaseRoute(['edit' => $child->id]) }}"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                                                    <form method="POST" action="{{ route('admin.blocks.destroy', $child) }}" onsubmit="return confirm('Delete this child block?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="expanded" value="{{ $blockExpandedQuery }}" data-wb-slot-block-expanded-input>
                                                        @unless ($activeLocale->is_default)
                                                            <input type="hidden" name="locale" value="{{ $activeLocale->code }}">
                                                        @endunless
                                                        <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete child block" aria-label="Delete child block"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            @endif
                        @endforeach
                    </table>
                </div>
            </div>
        @endif

        <div class="wb-card-footer">
            <a href="{{ $slotBlockRoute(['picker' => 1]) }}" class="wb-btn wb-btn-primary" data-wb-slot-block-link data-base-url="{{ $slotBlockBaseRoute(['picker' => 1]) }}">Add Block</a>
        </div>
    </div>

@endsection

@push('overlays')
    @include('admin.pages.partials.slot-block-picker', [
        'page' => $page,
        'slot' => $slot,
        'blockTypes' => $blockTypes,
        'pickerSearch' => $pickerSearch,
        'isPickerOpen' => $isPickerOpen,
        'slotModalMode' => $slotModalMode,
    ])

    @include('admin.pages.partials.slot-block-modal', [
        'page' => $page,
        'slot' => $slot,
        'blockTypes' => $blockTypes,
        'slotModalMode' => $slotModalMode,
        'slotModalBlock' => $slotModalBlock,
        'slotModalSelectedBlockType' => $slotModalSelectedBlockType,
        'assetPickerAssets' => $assetPickerAssets,
        'assetPickerFolders' => $assetPickerFolders,
        'slotModalSelectedAsset' => $slotModalSelectedAsset,
        'slotModalSelectedGalleryAssets' => $slotModalSelectedGalleryAssets,
        'slotModalSelectedAttachmentAsset' => $slotModalSelectedAttachmentAsset,
        'slotParentBlocks' => $slotParentBlocks,
    ])
@endpush
