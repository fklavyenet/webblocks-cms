@php
    $classes = collect([
        'wb-cms-slide',
        'wb-slider-slide',
        $block->publicBackgroundMediaClass(),
        $block->sliderContentPositionClass(),
        $block->sliderContentWidthClass(),
        $block->sliderTextColorClass(),
        $block->sliderBackgroundFitClass(),
    ])->filter()->implode(' ');
    $backgroundStyle = $block->publicBackgroundMediaStyle();
    $label = trim((string) ($block->setting('aria_label') ?? $block->title ?? ''));
@endphp

<article
    class="{{ $classes }}"
    data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}"
    data-wb-slider-slide
    @if ($label !== '') aria-label="{{ $label }}"@endif
    @if ($backgroundStyle !== null) style="{{ $backgroundStyle }}"@endif
>
    <div class="wb-cms-slide-content">
        @foreach ($block->children as $child)
            @include('webblocks-cms::pages.partials.block', ['block' => $child])
        @endforeach
    </div>
</article>
