@php
    $adminLocaleCode = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('engagement.'.$key, $adminLocaleCode, $replace);
    $hasActiveFilters = count(array_filter(array_diff_key($filters, ['sort' => true, 'page' => true]), fn ($value) => $value !== '' && $value !== null)) > 0;
    $returnQuery = $returnQuery ?? http_build_query(request()->query());
@endphp
@extends('webblocks-cms::layouts.admin', ['title' => $adminText('ratings'), 'heading' => $adminText('ratings')])
@section('content')
<div class="wb-stack wb-gap-4">
    @include('webblocks-cms::admin.partials.page-header', ['title' => $adminText('ratings'), 'description' => $adminText('ratings_description')])
    @if (! $tableReady)<div class="wb-alert wb-alert-warning">{{ $adminText('setup_guidance') }}</div>@endif
    @include('webblocks-cms::admin.engagement.filters', ['kind' => 'ratings', 'filterRoute' => route('admin.engagement.ratings.index')])
    @include('webblocks-cms::admin.engagement.rating-summary')
    <section class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between"><strong>{{ $adminText('ratings') }} <span class="wb-badge">{{ $filteredCount }}</span></strong><span>{{ $adminText('total', ['count' => $totalCount]) }}</span></div>
        <div class="wb-card-body wb-stack wb-gap-3">
            <p class="wb-text-sm wb-text-muted">{{ $adminText('rating_history_help') }}</p>
            <div class="wb-table-wrap"><table class="wb-table">
                <thead><tr><th>{{ $adminText('rating') }}</th><th>{{ $adminText('source') }}</th><th>{{ $adminText('status') }}</th><th>{{ $adminText('submitted') }}</th><th>{{ $adminText('updated') }}</th><th>{{ $adminText('actions') }}</th></tr></thead>
                <tbody>@forelse ($ratings as $rating)
                    <tr><td><strong>{{ $rating->rating_value }} / {{ $rating->rating_max }}</strong></td>
                        <td><div class="wb-stack wb-gap-1"><span>{{ $rating->page?->title ?: '—' }}</span><span class="wb-text-sm wb-text-muted">{{ $rating->site?->name }}</span>@if (! $rating->block_id)<span class="wb-text-sm wb-text-muted">{{ $adminText('previous_block') }}</span>@endif</div></td>
                        <td><span class="wb-status-pill {{ $rating->status === 'active' ? 'wb-status-active' : 'wb-status-pending' }}">{{ $rating->status === 'active' ? $adminText('status_active') : $adminText('status_inactive') }}</span></td>
                        <td>{{ $rating->created_at?->format('Y-m-d H:i') }}</td><td>{{ $rating->updated_at?->format('Y-m-d H:i') }}</td>
                        <td class="wb-table-actions">@if ($rating->page?->status === 'published')<div class="wb-action-group"><a class="wb-action-btn wb-action-btn-view" href="{{ $rating->page->publicUrl() }}" target="_blank" rel="noopener" title="{{ $adminText('open_page') }}" aria-label="{{ $adminText('open_page') }}"><i class="wb-icon wb-icon-external-link" aria-hidden="true"></i></a></div>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="wb-empty">
                        <div class="wb-empty-title">{{ $adminText('no_ratings') }}</div>
                        <div class="wb-empty-text">{{ $hasActiveFilters ? $adminText('no_ratings_filtered_help') : $adminText('no_ratings_help') }}</div>
                        @if ($hasActiveFilters)<div class="wb-empty-action"><a class="wb-btn wb-btn-secondary" href="{{ route('admin.engagement.ratings.index') }}">{{ $adminText('clear_filters') }}</a></div>@endif
                    </div></td></tr>
                @endforelse</tbody>
            </table></div>
        </div>
        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $ratings, 'compact' => true])
    </section>
</div>
@endsection
