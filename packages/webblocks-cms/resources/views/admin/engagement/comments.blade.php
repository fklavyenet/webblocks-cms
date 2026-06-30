@extends('webblocks-cms::layouts.admin', ['title' => 'Comments', 'heading' => 'Comments'])

@section('content')
    <div class="wb-stack wb-gap-4">
        @include('webblocks-cms::admin.partials.page-header', [
            'title' => 'Comments',
            'description' => 'Review and moderate public comments submitted through Comments system blocks.',
            'actions' => '<a href="'.route('admin.engagement.ratings.index').'" class="wb-btn wb-btn-secondary">Ratings</a>',
        ])

        @include('webblocks-cms::admin.partials.flash')

        @if (($tableReady ?? true) === false)
            <div class="wb-alert wb-alert-warning">
                <div>
                    <div class="wb-alert-title">Engagement tables are not ready</div>
                    <div>Run System Updates to create the Comments and Rating tables before reviewing public feedback.</div>
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
                        'label' => 'Search',
                        'value' => $filters['search'] ?? '',
                        'placeholder' => 'Search comments',
                    ],
                    'selects' => [
                        [
                            'id' => 'engagement_comments_status',
                            'name' => 'status',
                            'label' => 'Status',
                            'selected' => $filters['status'] ?? '',
                            'placeholder' => 'All statuses',
                            'options' => collect($statuses)
                                ->mapWithKeys(fn (string $status): array => [$status => ucfirst($status)])
                                ->all(),
                        ],
                    ],
                    'showReset' => ($filters['search'] ?? '') !== '' || ($filters['status'] ?? '') !== '',
                    'resetUrl' => route('admin.engagement.comments.index'),
                    'applyLabel' => 'Apply',
                ])
            </div>
        </div>

        <section class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>Comments</strong>
                    <span class="wb-status-pill wb-status-info">{{ $filteredCount }}</span>
                </div>
                <span class="wb-text-sm wb-text-muted">{{ $totalCount }} total</span>
            </div>
            <div class="wb-card-body">
                @if ($comments->isEmpty())
                    <div class="wb-empty">
                        <div class="wb-empty-title">No comments found</div>
                        <div class="wb-empty-text">Approved public comments will appear after review.</div>
                    </div>
                @else
                    <div class="wb-table-wrap">
                        <table class="wb-table">
                            <thead>
                                <tr>
                                    <th>Comment</th>
                                    <th>Source</th>
                                    <th>Status</th>
                                    <th>Spam</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($comments as $comment)
                                    <tr>
                                        <td>
                                            <div class="wb-stack wb-gap-1">
                                                <strong>{{ $comment->author_name ?: 'Anonymous' }}</strong>
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
                                                    <button class="wb-btn wb-btn-secondary" type="submit">Approve</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.engagement.comments.status', $comment) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="rejected">
                                                    <button class="wb-btn wb-btn-secondary" type="submit">Reject</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.engagement.comments.status', $comment) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="spam">
                                                    <button class="wb-btn wb-btn-secondary" type="submit">Spam</button>
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
                @include('webblocks-cms::admin.partials.pagination', ['paginator' => $comments, 'ariaLabel' => 'Comments pagination', 'compact' => true])
            </div>
        </section>
    </div>
@endsection
