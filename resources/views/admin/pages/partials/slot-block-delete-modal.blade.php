@php
    use Illuminate\Support\Str;
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $slotBlockDeleteLocale = app(AdminLocaleResolver::class)->locale();
    $slotBlockDeleteTranslator = app(CmsTranslator::class);
    $slotBlockDeleteText = static fn (string $key, array $replace = []) => $slotBlockDeleteTranslator->admin('slot_block_delete_modal.'.$key, $slotBlockDeleteLocale, $replace);
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
        ? $deleteMeta['direct_child_count'].' '.Str::plural($slotBlockDeleteText('direct_child'), $deleteMeta['direct_child_count'])
        : null;
    $descendantCountLabel = $deleteMeta
        ? $deleteMeta['descendant_count'].' '.Str::plural($slotBlockDeleteText('nested_descendant'), $deleteMeta['descendant_count'])
        : null;
    $deleteAllMeta = $slotDeleteAllModalMeta ?? null;
    $showDeleteAllModal = request()->boolean('delete_all') && $deleteAllMeta && (($deleteAllMeta['total_count'] ?? 0) > 0);
    $deleteAllTitle = $sharedSlot ? $slotBlockDeleteText('delete_all_shared_slot_blocks') : $slotBlockDeleteText('delete_all_blocks');
    $deleteAllContext = $sharedSlot
        ? $slotBlockDeleteText('shared_slot_context', ['name' => $sharedSlot->name])
        : $slotBlockDeleteText('page_context', ['title' => $page->title]);
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
                        <h2 class="wb-modal-title" id="slot-block-delete-title">{{ $slotBlockDeleteText('delete_block_title') }}</h2>
                        <p class="wb-text-sm wb-text-muted" id="slot-block-delete-description">{{ $slotBlockDeleteText('delete_block_description') }}</p>
                    </div>

                    <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="{{ $slotBlockDeleteText('close_delete_block_dialog') }}">
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
                                    <span class="wb-text-sm wb-text-muted">{{ $slotBlockDeleteText('block_number', ['id' => $deleteBlock->id]) }}</span>
                                </div>
                                <strong>{{ $label }}</strong>
                                @if ($summary && $summary !== $label)
                                    <p class="wb-text-sm wb-text-muted">{{ $summary }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-2">
                                <strong>{{ $slotBlockDeleteText('nested_blocks') }}</strong>
                                @if ($deleteMeta['has_children'])
                                    <p class="wb-text-sm wb-text-muted">{{ $slotBlockDeleteText('contains_children', ['children' => $childCountLabel, 'descendants' => $descendantCountLabel]) }}</p>
                                @else
                                    <p class="wb-text-sm wb-text-muted">{{ $slotBlockDeleteText('no_nested_children') }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-3">
                                <label class="wb-checkbox" for="delete_descendants">
                                    <input id="delete_descendants" type="checkbox" name="delete_descendants" value="1" @checked(old('delete_descendants')) data-wb-delete-descendants-toggle>
                                    <span>{{ $slotBlockDeleteText('also_delete_nested') }}</span>
                                </label>

                                <p class="wb-text-sm wb-text-muted">{{ $slotBlockDeleteText('recursive_warning') }}</p>
                            </div>
                        </div>
                    </div>

                    <x-webblocks-cms::admin.form-actions
                        :cancel-url="$closeUrl"
                        :show-submit="false"
                        :delete-submit="true"
                        :delete-label="old('delete_descendants') ? $slotBlockDeleteText('delete_block_and_children') : $slotBlockDeleteText('delete_block')"
                        :delete-attributes="[
                            'data-wb-delete-submit' => true,
                            'data-default-label' => $slotBlockDeleteText('delete_block'),
                            'data-recursive-label' => $slotBlockDeleteText('delete_block_and_children'),
                        ]"
                        container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                    />
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
                        <p class="wb-text-sm wb-text-muted" id="slot-block-delete-all-description">{{ $slotBlockDeleteText('delete_all_description') }}</p>
                    </div>

                    <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="{{ $slotBlockDeleteText('close_delete_all_dialog') }}">
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
                                <div><strong>{{ $slotBlockDeleteText('slot') }}</strong> {{ $slot->slotType?->name ?? $slotBlockDeleteText('slot_fallback') }}</div>
                                <div><strong>{{ $slotBlockDeleteText('top_level_blocks') }}</strong> {{ $deleteAllMeta['top_level_count'] }}</div>
                                <div><strong>{{ $slotBlockDeleteText('nested_descendants') }}</strong> {{ $deleteAllMeta['descendant_count'] }}</div>
                            </div>
                        </div>

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-2">
                                <strong>{{ $slotBlockDeleteText('warning') }}</strong>
                                <p class="wb-text-sm wb-text-muted">{{ $slotBlockDeleteText('delete_all_warning') }}</p>
                                <p class="wb-text-sm wb-text-muted">{{ $slotBlockDeleteText('recovery_warning') }}</p>
                            </div>
                        </div>

                        <label class="wb-checkbox" for="confirm_delete_all_blocks">
                            <input id="confirm_delete_all_blocks" type="checkbox" name="confirm_delete_all_blocks" value="1" @checked(old('confirm_delete_all_blocks')) required>
                            <span>{{ $slotBlockDeleteText('confirm_delete_all') }}</span>
                        </label>
                    </div>

                    <x-webblocks-cms::admin.form-actions
                        :cancel-url="$closeUrl"
                        :show-submit="false"
                        :delete-submit="true"
                        :delete-label="$slotBlockDeleteText('delete_all_blocks_button')"
                        container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                    />
                </form>
            </div>
        </div>
    </div>
@endif
