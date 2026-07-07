@php
    $layoutSlots = $pageLayout->layoutSlots->sortBy('sort_order')->values();
    $pageLayoutsText = fn (string $key, array $replace = []) => __('webblocks-cms::admin.page_layouts.'.$key, $replace);
@endphp

<div class="wb-card">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
        <div class="wb-stack wb-gap-1">
            <strong>{{ $pageLayoutsText('slots_title') }}</strong>
            <span class="wb-text-sm wb-text-muted">{{ $pageLayoutsText('slots_description') }}</span>
        </div>

        <a href="{{ route('admin.page-layouts.slots.create', $pageLayout) }}" class="wb-btn wb-btn-primary wb-btn-sm">{{ $pageLayoutsText('add_slot') }}</a>
    </div>

    <div class="wb-card-body">
        @error('page_layout_slot')
            <div class="wb-alert wb-alert-danger">{{ $message }}</div>
        @enderror

        <div class="wb-card wb-card-muted wb-mb-3">
            <div class="wb-card-body wb-stack wb-gap-1 wb-text-sm wb-text-muted">
                <div>{{ $pageLayoutsText('slots_help_1') }}</div>
                <div>{{ $pageLayoutsText('slots_help_2') }}</div>
                <div>{{ $pageLayoutsText('slots_help_3') }}</div>
            </div>
        </div>

        @if ($layoutSlots->isEmpty())
            <div class="wb-empty">
                <div class="wb-empty-title">{{ $pageLayoutsText('no_slots_title') }}</div>
                <div class="wb-empty-text">{{ $pageLayoutsText('no_slots_text') }}</div>
            </div>
        @else
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>{{ $pageLayoutsText('order') }}</th>
                            <th>{{ $pageLayoutsText('slot') }}</th>
                            <th>{{ $pageLayoutsText('wrapper') }}</th>
                            <th>{{ $pageLayoutsText('status') }}</th>
                            <th>{{ $pageLayoutsText('actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($layoutSlots as $layoutSlot)
                            <tr>
                                <td class="wb-nowrap">{{ $layoutSlot->sort_order }}</td>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                                            <strong>{{ $layoutSlot->label ?: ($layoutSlot->slotType?->name ?? $layoutSlot->slot_name) }}</strong>
                                            <span class="wb-status-pill wb-status-info">{{ $layoutSlot->slotType?->name ?? $pageLayoutsText('no_slot_type') }}</span>
                                        </div>
                                        <div class="wb-text-sm wb-text-muted"><code>{{ $layoutSlot->slot_name }}</code></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-1 wb-text-sm">
                                        <div><strong>{{ $pageLayoutsText('element_label') }}</strong> <code>{{ $layoutSlot->html_element }}</code></div>
                                        <div><strong>{{ $pageLayoutsText('id_label') }}</strong> <span title="{{ $layoutSlot->html_id ?: '-' }}">{{ $layoutSlot->html_id ?: '-' }}</span></div>
                                        <div title="{{ $layoutSlot->html_classes ?: '-' }}"><strong>{{ $pageLayoutsText('classes_label') }}</strong> {{ \Illuminate\Support\Str::limit($layoutSlot->html_classes ?: '-', 48) }}</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                                            <span class="wb-status-pill {{ $layoutSlot->is_required ? 'wb-status-info' : 'wb-status-pending' }}">{{ $layoutSlot->is_required ? $pageLayoutsText('required') : $pageLayoutsText('optional') }}</span>
                                            <span class="wb-status-pill {{ $layoutSlot->statusBadgeClass() }}">{{ $layoutSlot->statusLabel() }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="wb-nowrap">
                                    <div class="wb-action-group">
                                        <a href="{{ route('admin.page-layouts.slots.edit', [$pageLayout, $layoutSlot]) }}" class="wb-action-btn wb-action-btn-edit" title="{{ $pageLayoutsText('edit_slot') }}" aria-label="{{ $pageLayoutsText('edit_slot') }}">
                                            <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                        </a>
                                        @if (! $layoutSlot->is_system && ! $layoutSlot->is_required)
                                            <form method="POST" action="{{ route('admin.page-layouts.slots.destroy', [$pageLayout, $layoutSlot]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="wb-action-btn wb-action-btn-delete" title="{{ $pageLayoutsText('delete_slot') }}" aria-label="{{ $pageLayoutsText('delete_slot') }}">
                                                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
