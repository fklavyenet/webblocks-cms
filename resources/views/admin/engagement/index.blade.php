@php
    $adminLocaleCode = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('engagement.'.$key, $adminLocaleCode, $replace);
    $returnQuery = $returnQuery ?? http_build_query(request()->query());
@endphp
@extends('webblocks-cms::layouts.admin', ['title' => $adminText('overview'), 'heading' => $adminText('overview')])
@section('content')
<div class="wb-stack wb-gap-4">
    @include('webblocks-cms::admin.partials.page-header', ['title' => $adminText('overview'), 'description' => $adminText('overview_description')])
    @include('webblocks-cms::admin.partials.flash')
    @if (! $tableReady)<div class="wb-alert wb-alert-warning">{{ $adminText('setup_guidance') }}</div>@endif
    @include('webblocks-cms::admin.engagement.filters', ['kind' => 'overview', 'filterRoute' => route('admin.engagement.index')])
    <div class="wb-grid wb-grid-2">
        <section class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between"><strong>{{ $adminText('comments') }}</strong><span class="wb-badge">{{ $commentsCount }}</span></div>
            <div class="wb-card-body wb-stack wb-gap-3">
                <p>{{ $adminText('pending_comments', ['count' => $pendingCommentsCount]) }}</p>
                @if ($oldestPending)<p>{{ $adminText('oldest_pending', ['date' => $oldestPending.' ('.\Illuminate\Support\Carbon::parse($oldestPending)->locale($adminLocaleCode)->diffForHumans().')']) }}</p>@endif
                <p>{{ $adminText('recent_activity', ['seven' => $comments7, 'thirty' => $comments30]) }}</p>
                <p class="wb-text-sm wb-text-muted">{{ $adminText('queue_help') }}</p>
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">@foreach ($commentStatuses as $status => $count)<span>{{ $adminText('status_'.$status) }}: {{ $count }}</span>@endforeach</div>
            </div>
            <div class="wb-card-footer wb-cluster wb-cluster-2">
                <a class="wb-btn wb-btn-primary" href="{{ route('admin.engagement.comments.index', ['site' => $filters['site'], 'status' => 'pending']) }}">{{ $adminText('review_pending') }}</a>
                <a class="wb-btn wb-btn-secondary" href="{{ route('admin.engagement.comments.index', ['site' => $filters['site']]) }}">{{ $adminText('view_comments') }}</a>
            </div>
        </section>
        <section class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between"><strong>{{ $adminText('ratings') }}</strong><span class="wb-badge">{{ $ratingsCount }}</span></div>
            <div class="wb-card-body wb-stack wb-gap-3">
                <p>{{ is_null($averageRating) ? $adminText('no_ratings') : $adminText('rating_sample', ['value' => $averageRating, 'count' => $activeRatingsCount]) }}</p>
                <p>{{ $adminText('recent_activity', ['seven' => $ratings7, 'thirty' => $ratings30]) }}</p>
                <p class="wb-text-sm wb-text-muted">{{ $adminText('rating_summary_help') }}</p>
            </div>
            <div class="wb-card-footer"><a class="wb-btn wb-btn-primary" href="{{ route('admin.engagement.ratings.index', ['site' => $filters['site']]) }}">{{ $adminText('view_ratings') }}</a></div>
        </section>
    </div>
    <section class="wb-card">
        <div class="wb-card-header"><strong>{{ $adminText('page_summary') }}</strong></div>
        <div class="wb-card-body"><div class="wb-table-wrap"><table class="wb-table">
            <thead><tr><th>{{ $adminText('source') }}</th><th>{{ $adminText('site') }}</th><th>{{ $adminText('status_pending') }}</th><th>{{ $adminText('votes') }}</th><th>{{ $adminText('rating') }}</th><th>{{ $adminText('last_activity') }}</th><th>{{ $adminText('actions') }}</th></tr></thead>
            <tbody>@forelse ($pageSummary as $page)
                <tr><td>{{ $page->title ?: $page->slug }}</td><td>{{ $page->site?->name }}</td><td>{{ $page->pending_count }}</td><td>{{ $page->vote_count }}</td><td>{{ $page->average_rating !== null ? round($page->average_rating, 1).' / 5' : '—' }}</td><td>{{ $page->last_activity ?: '—' }}</td><td class="wb-table-actions"><div class="wb-action-group">
                    <a class="wb-action-btn wb-action-btn-view" href="{{ route('admin.engagement.comments.index', ['site' => $page->site_id, 'page_id' => $page->id]) }}" title="{{ $adminText('view_comments') }}" aria-label="{{ $adminText('view_comments') }}"><i class="wb-icon wb-icon-message-square" aria-hidden="true"></i></a>
                    <a class="wb-action-btn wb-action-btn-view" href="{{ route('admin.engagement.ratings.index', ['site' => $page->site_id, 'page_id' => $page->id]) }}" title="{{ $adminText('view_ratings') }}" aria-label="{{ $adminText('view_ratings') }}"><i class="wb-icon wb-icon-star" aria-hidden="true"></i></a>
                </div></td></tr>
            @empty<tr><td colspan="7">{{ $adminText('no_feedback') }}</td></tr>@endforelse</tbody>
        </table></div></div>
        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $pageSummary, 'compact' => true])
    </section>
</div>
@endsection
