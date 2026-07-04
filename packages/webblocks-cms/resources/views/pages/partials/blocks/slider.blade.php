@php
    $slides = $block->children
        ->filter(fn ($child) => $child->typeSlug() === 'slide')
        ->values();
    $slideCount = $slides->count();
    $showControls = $slideCount > 1;
    $showArrows = $showControls && $block->sliderBooleanSetting('show_arrows', true);
    $showDots = $showControls && $block->sliderBooleanSetting('show_dots', true);
    $classes = collect([
        'wb-cms-slider',
        'wb-slider',
        $block->sliderHeightClass(),
        $block->sliderAspectRatioClass(),
        'wb-cms-slider-transition-'.$block->sliderTransition(),
        $block->sliderOverlayClass(),
        $block->sliderContentPositionClass(),
        $block->sliderContentWidthClass(),
        $block->sliderTextColorClass(),
        $block->sliderBackgroundFitClass(),
    ])->filter()->implode(' ');
    $style = $block->sliderMinHeightStyle();
@endphp

<section
    class="{{ $classes }}"
    data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}"
    data-wb-slider
    data-wb-slider-transition="{{ $block->sliderTransition() }}"
    data-wb-slider-autoplay="{{ $block->sliderBooleanSetting('autoplay', false) ? 'true' : 'false' }}"
    data-wb-slider-interval="{{ $block->sliderIntervalMs() }}"
    data-wb-slider-pause-on-hover="{{ $block->sliderBooleanSetting('pause_on_hover', true) ? 'true' : 'false' }}"
    data-wb-slider-loop="{{ $block->sliderBooleanSetting('loop', true) ? 'true' : 'false' }}"
    data-wb-slider-swipe="{{ $block->sliderBooleanSetting('swipe', true) ? 'true' : 'false' }}"
    data-wb-slider-keyboard="{{ $block->sliderBooleanSetting('keyboard', true) ? 'true' : 'false' }}"
    @if ($style !== null) style="{{ $style }}"@endif
>
    <div class="wb-cms-slider-track" data-wb-slider-track>
        @foreach ($slides as $slide)
            @include('webblocks-cms::pages.partials.block', ['block' => $slide])
        @endforeach
    </div>

    @if ($showArrows)
        <div class="wb-cms-slider-arrows" aria-hidden="false">
            <button type="button" class="wb-btn wb-btn-secondary wb-cms-slider-arrow wb-cms-slider-arrow-prev" data-wb-slider-prev aria-label="Previous slide">Previous</button>
            <button type="button" class="wb-btn wb-btn-secondary wb-cms-slider-arrow wb-cms-slider-arrow-next" data-wb-slider-next aria-label="Next slide">Next</button>
        </div>
    @endif

    @if ($showDots)
        <div class="wb-cms-slider-dots wb-slider-dots" role="tablist" aria-label="{{ $block->title ?: 'Slider' }} slides">
            @foreach ($slides as $slide)
                <button type="button" class="wb-slider-dot {{ $loop->first ? 'is-active' : '' }}" data-wb-slider-dot aria-label="Go to slide {{ $loop->iteration }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}"></button>
            @endforeach
        </div>
    @endif
</section>
