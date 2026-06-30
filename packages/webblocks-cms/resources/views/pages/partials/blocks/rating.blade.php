@php
    $tableReady = \Illuminate\Support\Facades\Schema::hasTable('content_ratings');
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
    $success = session('rating_success_block_id') === $block->id ? session('rating_success_message') : null;
@endphp

<section class="wb-card wb-public-rating" id="rating-{{ $block->id }}" data-wb-public-block-type="rating">
    <div class="wb-card-body wb-stack wb-gap-3">
        @if ($success)
            <div class="wb-alert wb-alert-success">
                <div>{{ $success }}</div>
            </div>
        @endif

        @if ($tableReady)
            <form method="POST" action="{{ route('content-ratings.store') }}" class="wb-cluster wb-cluster-2 wb-flex-wrap" aria-label="Rate this content">
                @csrf
                <input type="hidden" name="block_id" value="{{ $block->id }}">
                <input type="hidden" name="page_id" value="{{ $page->id ?? $block->renderPageId() ?? $block->page_id }}">
                <input type="hidden" name="source_url" value="{{ request()->getRequestUri() }}">

                @for ($rating = 1; $rating <= 5; $rating++)
                    <button type="submit" name="rating_value" value="{{ $rating }}" class="wb-btn wb-btn-secondary" aria-label="{{ $rating }} star rating">
                        {{ $rating }} star
                    </button>
                @endfor
            </form>
        @else
            <div class="wb-alert wb-alert-warning">
                <div>Ratings are temporarily unavailable.</div>
            </div>
        @endif

        @if ($tableReady && $showSummary)
            <div class="wb-text-sm wb-text-muted">
                @if ($ratingCount > 0)
                    Average {{ $ratingAverage }} / 5 from {{ $ratingCount }} {{ \Illuminate\Support\Str::plural('rating', $ratingCount) }}.
                @else
                    No ratings yet.
                @endif
            </div>
        @endif
    </div>
</section>
