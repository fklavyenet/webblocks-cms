@extends('webblocks-cms::layouts.admin', ['title' => 'Ratings', 'heading' => 'Ratings'])

@section('content')
    <div class="wb-stack wb-gap-4">
        @include('webblocks-cms::admin.partials.page-header', [
            'title' => 'Ratings',
            'description' => 'Review lightweight public ratings submitted through Rating system blocks.',
            'actions' => '<a href="'.route('admin.engagement.comments.index').'" class="wb-btn wb-btn-secondary">Comments</a>',
        ])

        @if (($tableReady ?? true) === false)
            <div class="wb-alert wb-alert-warning">
                <div>
                    <div class="wb-alert-title">Engagement tables are not ready</div>
                    <div>Run System Updates to create the Comments and Rating tables before reviewing public feedback.</div>
                </div>
            </div>
        @endif

        <section class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <strong>Ratings</strong>
                <span class="wb-text-sm wb-text-muted">{{ $totalCount }} total</span>
            </div>
            <div class="wb-card-body">
                @if ($ratings->isEmpty())
                    <div class="wb-empty">
                        <div class="wb-empty-title">No ratings found</div>
                        <div class="wb-empty-text">Ratings appear after visitors use a Rating block.</div>
                    </div>
                @else
                    <div class="wb-table-wrap">
                        <table class="wb-table">
                            <thead>
                                <tr>
                                    <th>Rating</th>
                                    <th>Source</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ratings as $rating)
                                    <tr>
                                        <td><strong>{{ $rating->rating_value }} / {{ $rating->rating_max }}</strong></td>
                                        <td>{{ $rating->page?->title ?: '-' }}</td>
                                        <td><span class="wb-status-pill wb-status-active">{{ $rating->status }}</span></td>
                                        <td>{{ $rating->created_at?->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            <div class="wb-card-footer">
                @include('webblocks-cms::admin.partials.pagination', ['paginator' => $ratings, 'ariaLabel' => 'Ratings pagination', 'compact' => true])
            </div>
        </section>
    </div>
@endsection
