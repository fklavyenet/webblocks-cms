@php
    $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $localeCode = $block->renderLocaleCode();
    $tableReady = \Illuminate\Support\Facades\Schema::hasTable('wbcms_content_ratings');
    $ratingCount = 0;
    $ratingAverage = null;
    if ($tableReady) {
        $ratings = \WebBlocks\Cms\Models\ContentRating::query()
            ->where('block_id', $block->id)
            ->where('status', 'active');
        $ratingCount = (clone $ratings)->count();
        $ratingAverage = $ratingCount > 0 ? round((float) (clone $ratings)->avg('rating_value'), 1) : null;
    }
    $showSummary = (bool) $block->setting('show_summary', true);
    $title = trim((string) $block->setting('title', ''));
    $averagePercent = $ratingAverage !== null ? round(($ratingAverage / 5) * 100, 2) : 0;
    $success = session('rating_success_block_id') === $block->id ? session('rating_success_message') : null;
@endphp

<section class="wb-card wb-public-rating wb-rating" id="rating-{{ $block->id }}" data-wb-public-block-type="rating">
    <div class="wb-card-body wb-stack wb-gap-3">
        @if ($title !== '')
            <h3 class="wb-public-rating-title">{{ $title }}</h3>
        @endif

        @if ($success)
            <div class="wb-alert wb-alert-success">
                <div>{{ $success }}</div>
            </div>
        @endif

        @if ($tableReady && $showSummary && $ratingCount > 0)
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap" style="align-items: center; gap: var(--wb-s3);">
                <span class="wb-rating-stars" style="--wb-rating-value: {{ $averagePercent }}%; --wb-rating-size: 1.35rem;" role="img" aria-label="{{ $translator->get('blocks.rating.average_aria', $localeCode, ['average' => $ratingAverage]) }}"></span>
                <span class="wb-text-sm wb-text-muted">
                    {{ $translator->get($ratingCount === 1 ? 'blocks.rating.summary' : 'blocks.rating.summary_plural', $localeCode, ['average' => $ratingAverage, 'count' => $ratingCount]) }}
                </span>
            </div>
        @endif

        @if ($tableReady)
            <form method="POST" action="{{ route('content-ratings.store') }}" class="wb-rating-input" aria-label="{{ $translator->get('blocks.rating.form_label', $localeCode) }}">
                @csrf
                <input type="hidden" name="block_id" value="{{ $block->id }}">
                <input type="hidden" name="page_id" value="{{ $page->id ?? $block->renderPageId() ?? $block->page_id }}">
                <input type="hidden" name="source_url" value="{{ request()->getRequestUri() }}">

                @for ($rating = 1; $rating <= 5; $rating++)
                    <button type="submit" name="rating_value" value="{{ $rating }}" aria-label="{{ $translator->get('blocks.rating.option_label', $localeCode, ['rating' => $rating]) }}">★</button>
                @endfor
            </form>
        @else
            <div class="wb-alert wb-alert-warning">
                <div>{{ $translator->get('blocks.rating.unavailable', $localeCode) }}</div>
            </div>
        @endif

        @if ($tableReady && $showSummary && $ratingCount === 0)
            <div class="wb-text-sm wb-text-muted">{{ $translator->get('blocks.rating.none', $localeCode) }}</div>
        @endif
    </div>
</section>
