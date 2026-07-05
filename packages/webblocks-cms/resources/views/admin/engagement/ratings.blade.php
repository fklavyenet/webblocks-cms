@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocaleCode = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocaleCode, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('engagement.ratings'), 'heading' => $adminText('engagement.ratings')])

@section('content')
    <div class="wb-stack wb-gap-4">
        @include('webblocks-cms::admin.partials.page-header', [
            'title' => $adminText('engagement.ratings'),
            'description' => $adminText('engagement.ratings_description'),
            'actions' => '<a href="'.route('admin.engagement.comments.index').'" class="wb-btn wb-btn-secondary">'.$adminText('engagement.comments').'</a>',
        ])

        @if (($tableReady ?? true) === false)
            <div class="wb-alert wb-alert-warning">
                <div>
                    <div class="wb-alert-title">{{ $adminText('engagement.tables_not_ready') }}</div>
                    <div>{{ $adminText('engagement.setup_guidance') }}</div>
                </div>
            </div>
        @endif

        <section class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <strong>{{ $adminText('engagement.ratings') }}</strong>
                <span class="wb-text-sm wb-text-muted">{{ $adminText('engagement.total', ['count' => $totalCount]) }}</span>
            </div>
            <div class="wb-card-body">
                @if ($ratings->isEmpty())
                    <div class="wb-empty">
                        <div class="wb-empty-title">{{ $adminText('engagement.no_ratings') }}</div>
                        <div class="wb-empty-text">{{ $adminText('engagement.no_ratings_help') }}</div>
                    </div>
                @else
                    <div class="wb-table-wrap">
                        <table class="wb-table">
                            <thead>
                                <tr>
                                    <th>{{ $adminText('engagement.rating') }}</th>
                                    <th>{{ $adminText('engagement.source') }}</th>
                                    <th>{{ $adminText('engagement.status') }}</th>
                                    <th>{{ $adminText('engagement.submitted') }}</th>
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
                @include('webblocks-cms::admin.partials.pagination', ['paginator' => $ratings, 'ariaLabel' => $adminText('engagement.ratings_pagination'), 'compact' => true])
            </div>
        </section>
    </div>
@endsection
