@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $layoutSlotSummaryLocale = app(AdminLocaleResolver::class)->locale();
    $layoutSlotSummaryTranslator = app(CmsTranslator::class);
    $layoutSlotSummaryText = static fn (string $key, array $replace = []) => $layoutSlotSummaryTranslator->admin('page_layout_slot_summary.'.$key, $layoutSlotSummaryLocale, $replace);
    $layoutSlotComparison = $layoutSlotComparison ?? [];
    $layoutSlotRows = $layoutSlotComparison['layout_slots'] ?? collect();
    $extraSlotRows = $layoutSlotComparison['extra_slots'] ?? collect();
    $missingCount = (int) ($layoutSlotComparison['missing_count'] ?? 0);
    $hasLayoutSlots = (bool) ($layoutSlotComparison['has_layout_slots'] ?? false);
    $layoutLabel = $layoutSlotComparison['layout_label'] ?? $layoutSlotSummaryText('page_layout');
@endphp

<div class="wb-card wb-card-muted">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
        <div class="wb-stack wb-gap-1">
            <strong>{{ $layoutSlotSummaryText('page_layout_slots') }}</strong>
            <span class="wb-text-sm wb-text-muted">{{ $layoutSlotSummaryText('description') }}</span>
        </div>

        @if ($canEditContent && $missingCount > 0)
            <form method="POST" action="{{ route('admin.pages.layout-slots.sync', $page) }}">
                @csrf
                <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">
                <button type="submit" class="wb-btn wb-btn-primary wb-btn-sm">{{ $layoutSlotSummaryText('add_missing_layout_slots') }}</button>
            </form>
        @endif
    </div>

    <div class="wb-card-body wb-stack wb-gap-3">
        <div class="wb-grid wb-grid-3">
            <div class="wb-stack wb-gap-1">
                <span class="wb-text-sm wb-text-muted">{{ $layoutSlotSummaryText('page_layout') }}</span>
                <strong>{{ $layoutLabel }}</strong>
            </div>
            <div class="wb-stack wb-gap-1">
                <span class="wb-text-sm wb-text-muted">{{ $layoutSlotSummaryText('layout_slots') }}</span>
                <strong>{{ $layoutSlotComparison['layout_slot_count'] ?? 0 }}</strong>
            </div>
            <div class="wb-stack wb-gap-1">
                <span class="wb-text-sm wb-text-muted">{{ $layoutSlotSummaryText('page_slots') }}</span>
                <strong>{{ $layoutSlotComparison['page_slot_count'] ?? 0 }}</strong>
            </div>
        </div>

        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
            <span class="wb-status-pill wb-status-active">{{ $layoutSlotSummaryText('present_count', ['count' => $layoutSlotComparison['present_count'] ?? 0]) }}</span>
            <span class="wb-status-pill {{ $missingCount > 0 ? 'wb-status-pending' : 'wb-status-active' }}">{{ $layoutSlotSummaryText('missing_count', ['count' => $missingCount]) }}</span>
            <span class="wb-status-pill wb-status-info">{{ $layoutSlotSummaryText('extra_count', ['count' => $layoutSlotComparison['extra_count'] ?? 0]) }}</span>
            <span class="wb-status-pill wb-status-pending">{{ $layoutSlotSummaryText('disabled_count', ['count' => $layoutSlotComparison['disabled_count'] ?? 0]) }}</span>
            <span class="wb-status-pill wb-status-info">{{ $layoutSlotSummaryText('shared_slot_count', ['count' => $layoutSlotComparison['shared_slot_count'] ?? 0]) }}</span>
        </div>

        @if (! $hasLayoutSlots)
            <div class="wb-alert wb-alert-info wb-text-sm">
                {{ $layoutSlotSummaryText('no_layout_slots') }}
            </div>
        @elseif ($missingCount === 0)
            <div class="wb-alert wb-alert-success wb-text-sm">
                {{ $layoutSlotSummaryText('all_slots_present') }}
            </div>
        @else
            <div class="wb-alert wb-alert-info wb-text-sm">
                {{ $layoutSlotSummaryText('safe_to_add_missing') }}
            </div>
        @endif

        <div class="wb-text-sm wb-text-muted">
            {{ $layoutSlotSummaryText('extra_slots_help') }}
        </div>

        @if ($layoutSlotRows->isNotEmpty() || $extraSlotRows->isNotEmpty())
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>{{ $layoutSlotSummaryText('layout_slot') }}</th>
                            <th>{{ $layoutSlotSummaryText('page_slot') }}</th>
                            <th>{{ $layoutSlotSummaryText('status') }}</th>
                            <th>{{ $layoutSlotSummaryText('source') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($layoutSlotRows as $row)
                            @php
                                $pageSlot = $row['page_slot'];
                            @endphp
                            <tr>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <strong>{{ $row['layout_label'] }}</strong>
                                        <span class="wb-text-sm wb-text-muted"><code>{{ $row['layout_slot_name'] }}</code></span>
                                    </div>
                                </td>
                                <td>
                                    @if ($pageSlot)
                                        <div class="wb-stack wb-gap-1">
                                            <strong>{{ $row['page_slot_label'] ?: $layoutSlotSummaryText('page_slot') }}</strong>
                                            <span class="wb-text-sm wb-text-muted"><code>{{ $row['page_slot_name'] }}</code></span>
                                        </div>
                                    @else
                                        <span class="wb-text-sm wb-text-muted">{{ $layoutSlotSummaryText('missing_on_page') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                                        <span class="wb-status-pill {{ $row['status'] === 'missing' ? 'wb-status-pending' : 'wb-status-active' }}">{{ $row['status'] === 'missing' ? $layoutSlotSummaryText('missing') : $layoutSlotSummaryText('present') }}</span>
                                        @if ($row['is_disabled'])
                                            <span class="wb-status-pill wb-status-pending">{{ $layoutSlotSummaryText('disabled') }}</span>
                                        @endif
                                        @if ($row['is_shared_slot'])
                                            <span class="wb-status-pill wb-status-info">{{ $layoutSlotSummaryText('shared_slot') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if ($pageSlot)
                                        <div class="wb-stack wb-gap-1">
                                            <span>{{ $row['source_label'] }}</span>
                                            @if ($row['shared_slot_name'])
                                                <span class="wb-text-sm wb-text-muted">{{ $row['shared_slot_name'] }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="wb-text-sm wb-text-muted">{{ $layoutSlotSummaryText('will_be_added_as_page_content') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach

                        @foreach ($extraSlotRows as $row)
                            <tr>
                                <td>
                                    <span class="wb-text-sm wb-text-muted">{{ $layoutSlotSummaryText('not_defined_by_layout') }}</span>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <strong>{{ $row['page_slot_label'] ?: $layoutSlotSummaryText('page_slot') }}</strong>
                                        <span class="wb-text-sm wb-text-muted"><code>{{ $row['page_slot_name'] }}</code></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                                        <span class="wb-status-pill wb-status-info">{{ $layoutSlotSummaryText('extra') }}</span>
                                        @if ($row['is_disabled'])
                                            <span class="wb-status-pill wb-status-pending">{{ $layoutSlotSummaryText('disabled') }}</span>
                                        @endif
                                        @if ($row['is_shared_slot'])
                                            <span class="wb-status-pill wb-status-info">{{ $layoutSlotSummaryText('shared_slot') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <span>{{ $row['source_label'] }}</span>
                                        @if ($row['shared_slot_name'])
                                            <span class="wb-text-sm wb-text-muted">{{ $row['shared_slot_name'] }}</span>
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
