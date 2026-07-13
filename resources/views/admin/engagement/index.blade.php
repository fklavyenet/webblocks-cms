@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocaleCode = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocaleCode, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('engagement.overview'), 'heading' => $adminText('engagement.overview')])

@section('content')
    <div class="wb-stack wb-gap-4">
        @include('webblocks-cms::admin.partials.page-header', [
            'title' => $adminText('engagement.overview'),
            'description' => $adminText('engagement.overview_description'),
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

        <div class="wb-grid wb-grid-2">
            <section class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>{{ $adminText('engagement.comments') }}</strong>
                    <span class="wb-status-pill wb-status-info">{{ $commentsCount }}</span>
                </div>
                <div class="wb-card-body">
                    <div class="wb-stack wb-gap-2">
                        <div class="wb-text-sm wb-text-muted">{{ $adminText('engagement.comments_description') }}</div>
                        @if (($pendingCommentsCount ?? 0) > 0)
                            <div class="wb-text-sm">{{ $adminText('engagement.pending_comments', ['count' => $pendingCommentsCount]) }}</div>
                        @endif
                    </div>
                </div>
                <div class="wb-card-footer">
                    <a href="{{ route('admin.engagement.comments.index') }}" class="wb-btn wb-btn-primary">{{ $adminText('engagement.view_comments') }}</a>
                </div>
            </section>

            <section class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>{{ $adminText('engagement.ratings') }}</strong>
                    <span class="wb-status-pill wb-status-info">{{ $ratingsCount }}</span>
                </div>
                <div class="wb-card-body">
                    <div class="wb-stack wb-gap-2">
                        <div class="wb-text-sm wb-text-muted">{{ $adminText('engagement.ratings_description') }}</div>
                        @if (! is_null($averageRating))
                            <div class="wb-text-sm">{{ $adminText('engagement.average_rating', ['value' => $averageRating]) }}</div>
                        @endif
                    </div>
                </div>
                <div class="wb-card-footer">
                    <a href="{{ route('admin.engagement.ratings.index') }}" class="wb-btn wb-btn-primary">{{ $adminText('engagement.view_ratings') }}</a>
                </div>
            </section>
        </div>
    </div>
@endsection
