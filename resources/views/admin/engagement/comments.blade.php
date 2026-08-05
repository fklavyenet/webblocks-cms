@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocaleCode = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocaleCode, $replace);
    $hasActiveFilters = ($filters['search'] ?? '') !== '' || ($filters['status'] ?? '') !== '';
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('engagement.comments'), 'heading' => $adminText('engagement.comments')])

@section('content')
    <div class="wb-stack wb-gap-4">
        @include('webblocks-cms::admin.partials.page-header', [
            'title' => $adminText('engagement.comments'),
            'description' => $adminText('engagement.comments_description'),
            'actions' => '<a href="'.route('admin.engagement.ratings.index').'" class="wb-btn wb-btn-secondary">'.$adminText('engagement.ratings').'</a>',
        ])

        @include('webblocks-cms::admin.partials.flash')

        @if (($tableReady ?? true) === false)
            <div class="wb-alert wb-alert-warning">
                <div>
                    <div class="wb-alert-title">{{ $adminText('engagement.tables_not_ready') }}</div>
                    <div>{{ $adminText('engagement.setup_guidance') }}</div>
                </div>
            </div>
        @endif

        <div class="wb-card wb-card-muted">
            <div class="wb-card-body">
                @include('webblocks-cms::admin.partials.listing-filters', [
                    'action' => route('admin.engagement.comments.index'),
                    'search' => [
                        'id' => 'engagement_comments_search',
                        'name' => 'search',
                        'label' => $adminText('engagement.search'),
                        'value' => $filters['search'] ?? '',
                        'placeholder' => $adminText('engagement.search_comments'),
                    ],
                    'selects' => [
                        [
                            'id' => 'engagement_comments_status',
                            'name' => 'status',
                            'label' => $adminText('engagement.status'),
                            'selected' => $filters['status'] ?? '',
                            'placeholder' => $adminText('engagement.all_statuses'),
                            'options' => collect($statuses)
                                ->mapWithKeys(fn (string $status): array => [$status => ucfirst($status)])
                                ->all(),
                        ],
                    ],
                    'showReset' => ($filters['search'] ?? '') !== '' || ($filters['status'] ?? '') !== '',
                    'resetUrl' => route('admin.engagement.comments.index'),
                    'applyLabel' => $adminText('engagement.apply'),
                ])
            </div>
        </div>

        <section class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>{{ $adminText('engagement.comments') }}</strong>
                    <span class="wb-status-pill wb-status-info">{{ $filteredCount }}</span>
                </div>
                <span class="wb-text-sm wb-text-muted">{{ $adminText('engagement.total', ['count' => $totalCount]) }}</span>
            </div>
            <div class="wb-card-body">
                @if ($comments->isEmpty())
                    <div class="wb-empty">
                        <div class="wb-empty-title">{{ $adminText('engagement.no_comments') }}</div>
                        <div class="wb-empty-text">
                            {{ $hasActiveFilters ? $adminText('engagement.no_comments_filtered_help') : $adminText('engagement.no_comments_help') }}
                        </div>
                        @if ($hasActiveFilters)
                            <div class="wb-empty-action">
                                <a href="{{ route('admin.engagement.comments.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('engagement.clear_filters') }}</a>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="wb-table-wrap">
                        <table class="wb-table">
                            <thead>
                                <tr>
                                    <th>{{ $adminText('engagement.comment') }}</th>
                                    <th>{{ $adminText('engagement.source') }}</th>
                                    <th>{{ $adminText('engagement.status') }}</th>
                                    <th>{{ $adminText('engagement.spam') }}</th>
                                    <th>{{ $adminText('engagement.submitted') }}</th>
                                    <th>{{ $adminText('engagement.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($comments as $comment)
                                    <tr>
                                        <td>
                                            <div class="wb-stack wb-gap-1">
                                                <strong>{{ $comment->author_name ?: $adminText('engagement.anonymous') }}</strong>
                                                <span>{{ \Illuminate\Support\Str::limit($comment->body, 140) }}</span>
                                            </div>
                                        </td>
                                        <td>{{ $comment->sourceLabel() }}</td>
                                        <td><span class="wb-status-pill {{ $comment->statusClass() }}">{{ $comment->status }}</span></td>
                                        <td>
                                            <div class="wb-stack wb-gap-1">
                                                <span>{{ $comment->spam_score }}</span>
                                                @foreach ($comment->spamReasonLabels() as $reason)
                                                    <span class="wb-text-sm wb-text-muted">{{ $reason }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td>{{ $comment->created_at?->format('Y-m-d H:i') }}</td>
                                        <td class="wb-table-actions">
                                            <div class="wb-action-group">
                                                <form method="POST" action="{{ route('admin.engagement.comments.status', $comment) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="approved">
                                                    <button class="wb-btn wb-btn-secondary" type="submit">{{ $adminText('engagement.approve') }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.engagement.comments.status', $comment) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="rejected">
                                                    <button class="wb-btn wb-btn-secondary" type="submit">{{ $adminText('engagement.reject') }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.engagement.comments.status', $comment) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="spam">
                                                    <button class="wb-btn wb-btn-secondary" type="submit">{{ $adminText('engagement.mark_spam') }}</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            <div class="wb-card-footer">
                @include('webblocks-cms::admin.partials.pagination', ['paginator' => $comments, 'ariaLabel' => $adminText('engagement.comments_pagination'), 'compact' => true])
            </div>
        </section>
    </div>
@endsection
