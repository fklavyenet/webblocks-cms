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

                    <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                        <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary">Cancel</a>
                        <button type="submit" class="wb-btn wb-btn-danger" data-wb-delete-submit data-default-label="Delete block" data-recursive-label="Delete block and children">
                            {{ old('delete_descendants') ? 'Delete block and children' : 'Delete block' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
