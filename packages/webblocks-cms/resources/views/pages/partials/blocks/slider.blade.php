@php
    $sliderAssets = $block->galleryAssets();
@endphp

<section class="wb-stack wb-gap-3">
    @if ($block->title)
        <h3>{{ $block->title }}</h3>
    @endif

    <div class="wb-slider wb-card wb-card-muted" data-wb-slider>
        <div class="wb-slider-track" data-wb-slider-track>
            @foreach ($sliderAssets as $sliderAsset)
                @if ($sliderAsset->url())
                    <article class="wb-slider-slide" data-wb-slider-slide>
                        <div class="wb-card-body wb-stack wb-gap-3">
                            <img src="{{ $sliderAsset->url() }}" alt="{{ $sliderAsset->alt_text ?: $sliderAsset->title ?: 'Slider image' }}">
                            <div class="wb-stack wb-gap-1">
                                <strong>{{ $sliderAsset->title ?: $sliderAsset->filename }}</strong>
                                @if ($sliderAsset->caption)
                                    <p>{{ $sliderAsset->caption }}</p>
                                @endif
                            </div>
                        </div>
                    </article>
                @endif
            @endforeach
        </div>

        @if ($sliderAssets->count() > 1)
            <div class="wb-card-body wb-slider-controls">
                <button type="button" class="wb-btn wb-btn-secondary" data-wb-slider-prev>Previous</button>
                <div class="wb-slider-dots" role="tablist" aria-label="{{ $block->title ?: 'Slider' }} slides">
                    @foreach ($sliderAssets as $sliderAsset)
                        @if ($sliderAsset->url())
                            <button type="button" class="wb-slider-dot {{ $loop->first ? 'is-active' : '' }}" data-wb-slider-dot aria-label="Go to slide {{ $loop->iteration }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}"></button>
                        @endif
                    @endforeach
                </div>
                <button type="button" class="wb-btn wb-btn-secondary" data-wb-slider-next>Next</button>
            </div>
        @endif
    </div>

    @if ($block->subtitle)
        <p class="wb-text-sm wb-text-muted">{{ $block->subtitle }}</p>
    @endif
</section>
