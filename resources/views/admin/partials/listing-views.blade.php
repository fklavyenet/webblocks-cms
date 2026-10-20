@php
    $viewText = static fn (string $key) => __('webblocks-cms::listing_views.'.$key);
    $listing = $listingView['listing'];
    $selectedView = $listingView['selected'];
    $viewRoute = ['listing' => $listing];
    $standardUrl = route('admin.'.$listing.'.index', $listing === 'pages' ? ['reset' => 1] : []);
@endphp
<div class="wb-card wb-card-muted" data-wb-listing-views>
    <div class="wb-card-body wb-stack wb-gap-3">
        <div class="wb-cluster wb-cluster-between wb-flex-wrap wb-gap-2">
            <strong>{{ $viewText('title') }}</strong>
            <a href="{{ $standardUrl }}" class="wb-btn wb-btn-secondary">{{ $viewText('standard') }}</a>
        </div>
        <div class="wb-cluster wb-flex-wrap wb-gap-2">
            @forelse ($listingView['views'] as $personalView)
                <a href="{{ route('admin.listing-views.apply', [...$viewRoute, 'listingView' => $personalView->id]) }}" class="wb-btn {{ $selectedView?->id === $personalView->id ? 'wb-btn-primary' : 'wb-btn-secondary' }}" @if ($selectedView?->id === $personalView->id) aria-current="page" @endif>{{ $personalView->name }}</a>
            @empty
                <span class="wb-text-sm wb-text-muted">{{ $viewText('empty') }}</span>
            @endforelse
        </div>
        @if ($selectedView)
            <p class="wb-text-sm" role="status" data-wb-listing-view-status>{{ $viewText($listingView['blocked'] ? 'stale' : ($listingView['changed'] ? 'changed' : 'matching')) }}</p>
        @endif
        <p class="wb-text-sm wb-text-muted" data-wb-listing-view-pending hidden>{{ $viewText('pending') }}</p>
        <details>
            <summary>{{ $viewText('manage') }}</summary>
            <div class="wb-stack wb-gap-3">
                <p class="wb-text-sm wb-text-muted">{{ $viewText('help') }}</p>
                <form method="POST" action="{{ route('admin.listing-views.store', $viewRoute) }}" class="wb-stack wb-gap-2">
                    @csrf
                    <input type="hidden" name="operation" value="save">
                    @foreach ($listingView['filters'] as $filterName => $filterValue)
                        <input type="hidden" name="filters[{{ $filterName }}]" value="{{ $filterValue }}">
                    @endforeach
                    <input type="hidden" name="columns" value="{{ implode(',', $listingColumns) }}">
                    <label class="wb-label" for="listing_view_new_name">{{ $viewText('name') }}</label>
                    <input id="listing_view_new_name" class="wb-input" name="name" maxlength="80" required>
                    <button class="wb-btn wb-btn-primary" type="submit" data-wb-listing-view-save>{{ $viewText('create') }}</button>
                </form>
                @if ($selectedView)
                    <form method="POST" action="{{ route('admin.listing-views.update', [...$viewRoute, 'listingView' => $selectedView->id]) }}" class="wb-stack wb-gap-2">
                        @csrf
                        @method('PUT')
                        @foreach ($listingView['filters'] as $filterName => $filterValue)
                            <input type="hidden" name="filters[{{ $filterName }}]" value="{{ $filterValue }}">
                        @endforeach
                        <input type="hidden" name="columns" value="{{ implode(',', $listingColumns) }}">
                        <label class="wb-label" for="listing_view_name">{{ $viewText('name') }}</label>
                        <input id="listing_view_name" class="wb-input" name="name" value="{{ $selectedView->name }}" maxlength="80" required>
                        <div class="wb-cluster wb-flex-wrap wb-gap-2">
                            <button class="wb-btn wb-btn-secondary" type="submit" name="operation" value="rename">{{ $viewText('rename') }}</button>
                            <button class="wb-btn wb-btn-primary" type="submit" name="operation" value="save" data-wb-listing-view-save @disabled($listingView['blocked'] && ! request()->boolean('correct_view'))>{{ $viewText('update') }}</button>
                            <button class="wb-btn wb-btn-danger" type="button" data-wb-toggle="modal" data-wb-target="#delete-listing-view-modal">{{ $viewText('delete') }}</button>
                        </div>
                    </form>
                @endif
                <form method="GET" action="{{ route('admin.'.$listing.'.index') }}" class="wb-stack wb-gap-2" data-wb-listing-view-columns>
                    @foreach ($listingView['filters'] as $filterName => $filterValue)
                        <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
                    @endforeach
                    @if ($selectedView)
                        <input type="hidden" name="saved_view" value="{{ $selectedView->id }}">
                        @if ($listingView['blocked'])
                            <input type="hidden" name="correct_view" value="1">
                        @endif
                    @endif
                    <fieldset class="wb-stack wb-gap-2">
                        <legend class="wb-label">{{ $viewText('columns') }}</legend>
                        <p class="wb-text-sm wb-text-muted">{{ $viewText('columns_help') }}</p>
                        @foreach ($listingView['columnOptions'] as $columnIndex => $columnOption)
                            <div class="wb-field">
                                <label for="listing_column_{{ $columnIndex }}" class="wb-label">{{ $viewText('position') }} {{ $columnIndex + 1 }}</label>
                                <select id="listing_column_{{ $columnIndex }}" name="columns[]" class="wb-select">
                                    <option value="">{{ $viewText('hidden') }}</option>
                                    @foreach ($listingView['columnOptions'] as $option)
                                        <option value="{{ $option }}" @selected(($listingColumns[$columnIndex] ?? '') === $option)>{{ $viewText($listing.'.'.$option) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </fieldset>
                    <button class="wb-btn wb-btn-secondary" type="submit">{{ $viewText('apply_columns') }}</button>
                </form>
            </div>
        </details>
    </div>
</div>
@if ($selectedView)
    @push('overlays')
        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
            'id' => 'delete-listing-view-modal',
            'title' => $viewText('delete'),
            'description' => $viewText('delete_help'),
            'action' => route('admin.listing-views.destroy', [...$viewRoute, 'listingView' => $selectedView->id]),
            'method' => 'DELETE',
            'submitLabel' => $viewText('delete'),
        ])
            <p>{{ $selectedView->name }}</p>
        @endcomponent
    @endpush
@endif
@push('scripts')
    @php($listingViewsJsPath = public_path('cms/js/admin/listing-views.js'))
    @if (is_file($listingViewsJsPath))
        <script src="{{ asset('cms/js/admin/listing-views.js') }}?v={{ filemtime($listingViewsJsPath) }}" defer></script>
    @endif
@endpush
