@php
    $deleteBlock = $slotDeleteModalBlock ?? null;
    $deleteMeta = $slotDeleteModalMeta ?? null;
    $sharedSlot = $sharedSlot ?? null;
    $activeLocale = $activeLocale ?? null;
    $closeUrl = $slotBlockRoute([
        'edit' => request('edit'),
        'picker' => request()->boolean('picker') ? 1 : null,
        'parent_id' => request('parent_id'),
        'block_type_id' => request('block_type_id'),
        'block_type_tab' => request('block_type_tab'),
        'block_type_search' => request('block_type_search'),
        'block_type_category' => request('block_type_category'),
        'block_type_sort' => request('block_type_sort'),
    ]);
    $summary = $deleteBlock?->editorSummary();
    $label = $deleteBlock?->editorLabel();
    $childCountLabel = $deleteMeta
        ? $deleteMeta['direct_child_count'].' '.\Illuminate\Support\Str::plural('direct child', $deleteMeta['direct_child_count'])
        : null;
    $descendantCountLabel = $deleteMeta
        ? $deleteMeta['descendant_count'].' '.\Illuminate\Support\Str::plural('nested descendant', $deleteMeta['descendant_count'])
        : null;
    $deleteAllMeta = $slotDeleteAllModalMeta ?? null;
    $showDeleteAllModal = request()->boolean('delete_all') && $deleteAllMeta && (($deleteAllMeta['total_count'] ?? 0) > 0);
    $deleteAllTitle = $sharedSlot ? 'Delete All Shared Slot Blocks' : 'Delete All Blocks';
    $deleteAllContext = $sharedSlot
        ? 'Shared Slot: '.$sharedSlot->name
        : 'Page: '.$page->title;
    $deleteAllAction = $sharedSlot
        ? route('admin.shared-slots.blocks.destroy-all', $sharedSlot)
        : route('admin.pages.slots.blocks.destroy-all', [$page, $slot]);
@endphp

@if ($deleteBlock && $deleteMeta)
    <div class="wb-overlay-layer wb-overlay-layer--dialog">
        <div class="wb-modal wb-modal-lg is-open" id="slot-block-delete-modal" role="dialog" aria-modal="true" aria-labelledby="slot-block-delete-title" aria-describedby="slot-block-delete-description" data-wb-slot-block-delete-modal>
            <div class="wb-modal-dialog">
                <div class="wb-modal-header">
                    <div>
                        <h2 class="wb-modal-title" id="slot-block-delete-title">Delete Block</h2>
                        <p class="wb-text-sm wb-text-muted" id="slot-block-delete-description">Choose whether to delete only this block or also remove its nested child blocks.</p>
                    </div>

                    <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="Close delete block dialog">
                        <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                    </a>
                </div>

                <form method="POST" action="{{ route('admin.blocks.destroy', $deleteBlock) }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="return_url" value="{{ request('return_url') }}">

                    @if ($sharedSlot)
                        <input type="hidden" name="shared_slot_id" value="{{ $sharedSlot->id }}">
                    @endif

                    @if ($activeLocale && ! $activeLocale->is_default)
                        <input type="hidden" name="locale" value="{{ $activeLocale->code }}">
                    @endif

                    <div class="wb-modal-body wb-stack wb-gap-4">
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-2">
                                <div class="wb-cluster wb-cluster-2">
                                    <span class="wb-status-pill wb-status-info">{{ $deleteBlock->typeName() }}</span>
                                    <span class="wb-text-sm wb-text-muted">Block #{{ $deleteBlock->id }}</span>
                                </div>
                                <strong>{{ $label }}</strong>
                                @if ($summary && $summary !== $label)
                                    <p class="wb-text-sm wb-text-muted">{{ $summary }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-2">
                                <strong>Nested Blocks</strong>
                                @if ($deleteMeta['has_children'])
                                    <p class="wb-text-sm wb-text-muted">This block currently contains {{ $childCountLabel }} and {{ $descendantCountLabel }}.</p>
                                @else
                                    <p class="wb-text-sm wb-text-muted">This block has no nested child blocks.</p>
                                @endif
                            </div>
                        </div>

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-3">
                                <label class="wb-checkbox" for="delete_descendants">
                                    <input id="delete_descendants" type="checkbox" name="delete_descendants" value="1" @checked(old('delete_descendants')) data-wb-delete-descendants-toggle>
                                    <span>Also delete all nested child blocks</span>
                                </label>

                                <p class="wb-text-sm wb-text-muted">Default behavior is safer and deletes only the selected block. Recursive deletion cannot be undone except by restoring a revision or backup.</p>
                            </div>
                        </div>
                    </div>

                    <div class="wb-modal-footer wb-flex wb-items-center wb-gap-3 wb-flex-wrap">
                        <button type="submit" class="wb-btn wb-btn-danger" data-wb-delete-submit data-default-label="Delete block" data-recursive-label="Delete block and children">
                            {{ old('delete_descendants') ? 'Delete block and children' : 'Delete block' }}
                        </button>
                        <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if ($showDeleteAllModal)
    <div class="wb-overlay-layer wb-overlay-layer--dialog">
        <div class="wb-modal wb-modal-lg is-open" id="slot-block-delete-all-modal" role="dialog" aria-modal="true" aria-labelledby="slot-block-delete-all-title" aria-describedby="slot-block-delete-all-description">
            <div class="wb-modal-dialog">
                <div class="wb-modal-header">
                    <div>
                        <h2 class="wb-modal-title" id="slot-block-delete-all-title">{{ $deleteAllTitle }}</h2>
                        <p class="wb-text-sm wb-text-muted" id="slot-block-delete-all-description">Delete every block from this slot only, including nested descendants.</p>
                    </div>

                    <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="Close delete all blocks dialog">
                        <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                    </a>
                </div>

                <form method="POST" action="{{ $deleteAllAction }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="return_url" value="{{ request('return_url') }}">

                    @if ($activeLocale && ! $activeLocale->is_default)
                        <input type="hidden" name="locale" value="{{ $activeLocale->code }}">
                    @endif

                    <div class="wb-modal-body wb-stack wb-gap-4">
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-2">
                                <div><strong>{{ $deleteAllContext }}</strong></div>
                                <div><strong>Slot:</strong> {{ $slot->slotType?->name ?? 'Slot' }}</div>
                                <div><strong>Top-level blocks:</strong> {{ $deleteAllMeta['top_level_count'] }}</div>
                                <div><strong>Nested descendants:</strong> {{ $deleteAllMeta['descendant_count'] }}</div>
                            </div>
                        </div>

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-2">
                                <strong>Warning</strong>
                                <p class="wb-text-sm wb-text-muted">All blocks in this slot will be deleted. Blocks in other slots will be preserved.</p>
                                <p class="wb-text-sm wb-text-muted">Recovery is only possible through revisions or backups.</p>
                            </div>
                        </div>

                        <label class="wb-checkbox" for="confirm_delete_all_blocks">
                            <input id="confirm_delete_all_blocks" type="checkbox" name="confirm_delete_all_blocks" value="1" @checked(old('confirm_delete_all_blocks')) required>
                            <span>I understand that this deletes every block in this slot.</span>
                        </label>
                    </div>

                    <div class="wb-modal-footer wb-flex wb-items-center wb-gap-3 wb-flex-wrap">
                        <button type="submit" class="wb-btn wb-btn-danger">Delete all blocks</button>
                        <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
