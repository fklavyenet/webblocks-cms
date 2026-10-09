@php
    $adminLocaleCode = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('engagement.'.$key, $adminLocaleCode, $replace);
    $hasActiveFilters = count(array_filter(array_diff_key($filters, ['sort' => true, 'page' => true]), fn ($value) => $value !== '' && $value !== null)) > 0;
    $returnQuery = $returnQuery ?? http_build_query(request()->query());
@endphp
@extends('webblocks-cms::layouts.admin', ['title' => $adminText('comments'), 'heading' => $adminText('comments')])
@section('content')
<div class="wb-stack wb-gap-4">
    @include('webblocks-cms::admin.partials.page-header', ['title' => $adminText('comments'), 'description' => $adminText('comments_description')])
    @include('webblocks-cms::admin.partials.flash')
    @if (! $tableReady)<div class="wb-alert wb-alert-warning">{{ $adminText('setup_guidance') }}</div>@endif
    @include('webblocks-cms::admin.engagement.filters', ['kind' => 'comments', 'filterRoute' => route('admin.engagement.comments.index')])
    <div class="wb-cluster wb-cluster-2 wb-flex-wrap">@foreach ($statuses as $status)<a class="wb-btn wb-btn-ghost" href="{{ route('admin.engagement.comments.index', array_replace(array_diff_key($filters, ['page' => true]), ['status' => $status])) }}">{{ $adminText('status_'.$status) }} <span class="wb-badge">{{ $commentStatuses[$status] ?? 0 }}</span></a>@endforeach</div>
    <section class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between"><strong>{{ $adminText('comments') }} <span class="wb-badge">{{ $filteredCount }}</span></strong><span>{{ $adminText('total', ['count' => $totalCount]) }}</span></div>
        <div class="wb-card-body wb-stack wb-gap-3">
            <form id="engagement-bulk-moderation" method="POST" action="{{ route('admin.engagement.comments.bulk-status') }}" class="wb-cluster wb-cluster-2">
                @csrf
                <input type="hidden" name="return_query" value="{{ $returnQuery }}">
                <div class="wb-field"><label class="wb-label" for="bulk_comment_status">{{ $adminText('selected_action') }}</label><select id="bulk_comment_status" name="status" class="wb-select">@foreach ($statuses as $status)<option value="{{ $status }}">{{ $adminText('status_'.$status) }}</option>@endforeach</select></div>
                <button class="wb-btn wb-btn-secondary" type="submit" @disabled($comments->isEmpty())>{{ $adminText('apply_selected') }}</button>
            </form>
            <p class="wb-text-sm wb-text-muted">{{ $adminText('spam_help') }}</p>
            <div class="wb-table-wrap"><table class="wb-table">
                <thead><tr><th>{{ $adminText('select') }}</th><th>{{ $adminText('comment') }}</th><th>{{ $adminText('source') }}</th><th>{{ $adminText('status') }}</th><th>{{ $adminText('spam') }}</th><th>{{ $adminText('submitted') }}</th><th>{{ $adminText('actions') }}</th></tr></thead>
                <tbody>@forelse ($comments as $comment)
                    <tr><td><label class="wb-check" for="comment-select-{{ $comment->id }}"><input id="comment-select-{{ $comment->id }}" type="checkbox" form="engagement-bulk-moderation" name="comment_ids[]" value="{{ $comment->id }}" aria-label="{{ $adminText('select_comment', ['id' => $comment->id]) }}"><span class="wb-sr-only">{{ $adminText('select_comment', ['id' => $comment->id]) }}</span></label></td>
                        <td><div class="wb-stack wb-gap-1"><strong>{{ $comment->author_name ?: $adminText('anonymous') }}</strong><span>{{ \Illuminate\Support\Str::limit($comment->body, 140) }}</span></div></td>
                        <td><div class="wb-stack wb-gap-1"><span>{{ $comment->sourceLabel() }}</span><span class="wb-text-sm wb-text-muted">{{ $comment->site?->name }}</span>@if (! $comment->block_id)<span class="wb-text-sm wb-text-muted">{{ $adminText('previous_block') }}</span>@endif</div></td>
                        <td><span class="wb-status-pill {{ $comment->statusClass() }}">{{ $adminText('status_'.$comment->status) }}</span></td>
                        <td>{{ $comment->spam_score }}</td><td>{{ $comment->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="wb-table-actions">@include('webblocks-cms::admin.engagement.comment-actions')</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="wb-empty">
                        <div class="wb-empty-title">{{ $adminText('no_comments') }}</div>
                        <div class="wb-empty-text">{{ $hasActiveFilters ? $adminText('no_comments_filtered_help') : $adminText('no_comments_help') }}</div>
                        @if ($hasActiveFilters)<div class="wb-empty-action"><a class="wb-btn wb-btn-secondary" href="{{ route('admin.engagement.comments.index') }}">{{ $adminText('clear_filters') }}</a></div>@endif
                    </div></td></tr>
                @endforelse</tbody>
            </table></div>
        </div>
        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $comments, 'compact' => true])
    </section>
</div>
@endsection
