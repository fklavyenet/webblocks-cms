@php
    $a11y = fn (string $key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)
        ->get('blocks.a11y.'.$key, strtolower((string) ($block->renderLocaleCode() ?? app()->getLocale())));
    $slides = $block->children
        ->filter(fn ($child) => $child->typeSlug() === 'slide')
        ->values();
    $slideCount = $slides->count();
    $showControls = $slideCount > 1;
    $showArrows = $showControls && $block->sliderBooleanSetting('show_arrows', true);
    $showDots = $showControls && $block->sliderBooleanSetting('show_dots', true);
    $classes = collect([
        'wb-slider',
        $block->sliderHeightClass(),
        $block->sliderAspectRatioClass(),
        $block->sliderOverlayClass(),
        $block->sliderContentPositionClass(),
        $block->sliderContentWidthClass(),
        $block->sliderTextColorClass(),
    ])->filter()->implode(' ');
    $style = $block->sliderMinHeightStyle();
@endphp

<section
    class="{{ $classes }}"
    data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}"
    data-wb-slider
    data-wb-slider-autoplay="{{ $block->sliderBooleanSetting('autoplay', false) ? 'true' : 'false' }}"
    data-wb-slider-interval="{{ $block->sliderIntervalMs() }}"
    data-wb-slider-pause-on-hover="{{ $block->sliderBooleanSetting('pause_on_hover', true) ? 'true' : 'false' }}"
    data-wb-slider-loop="{{ $block->sliderBooleanSetting('loop', true) ? 'true' : 'false' }}"
    data-wb-slider-swipe="{{ $block->sliderBooleanSetting('swipe', true) ? 'true' : 'false' }}"
    data-wb-slider-keyboard="{{ $block->sliderBooleanSetting('keyboard', true) ? 'true' : 'false' }}"
    @if ($style !== null) style="{{ $style }}"@endif
>
    <div class="wb-slider-viewport">
        <div class="wb-slider-track">
            @foreach ($slides as $slide)
                @include('webblocks-cms::pages.partials.block', ['block' => $slide])
            @endforeach
        </div>
    </div>

    @if ($showArrows || $showDots)
        <div class="wb-slider-controls">
            @if ($showArrows)
                <button type="button" class="wb-btn wb-btn-icon wb-slider-arrow wb-slider-prev" data-wb-slider-prev aria-label="{{ $a11y('previous_slide') }}">
                    <i class="wb-icon wb-icon-chevron-left" aria-hidden="true"></i>
                </button>
            @endif
            @if ($showDots)
                <div class="wb-slider-dots" data-wb-slider-dots aria-label="{{ trim(($block->title ?: '').' '.$a11y('slides')) }}"></div>
            @endif
            @if ($showArrows)
                <button type="button" class="wb-btn wb-btn-icon wb-slider-arrow wb-slider-next" data-wb-slider-next aria-label="{{ $a11y('next_slide') }}">
                    <i class="wb-icon wb-icon-chevron-right" aria-hidden="true"></i>
                </button>
            @endif
        </div>
    @endif
</section>
