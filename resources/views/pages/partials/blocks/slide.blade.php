@php
    $mediaUrl = $block->publicBackgroundMediaUrl();
    $media = $block->media;
    $mediaAlt = trim((string) ($block->setting('media_alt') ?? $media?->alt_text ?? $media?->title ?? ''));
    $mediaFitClass = $block->sliderBackgroundFitClass() ?? $block->parent?->sliderBackgroundFitClass();
    $contentPositionClass = $block->setting('content_position') !== null
        ? $block->sliderContentPositionClass()
        : ($block->parent?->sliderContentPositionClass() ?? $block->sliderContentPositionClass());
    $contentWidthClass = $block->setting('content_width') !== null
        ? $block->sliderContentWidthClass()
        : ($block->parent?->sliderContentWidthClass() ?? $block->sliderContentWidthClass());
    $textColorClass = $block->setting('text_color') !== null
        ? $block->sliderTextColorClass()
        : ($block->parent?->sliderTextColorClass() ?? $block->sliderTextColorClass());
    $mediaClasses = collect([
        'wb-slide-media',
        $mediaFitClass,
    ])->filter()->implode(' ');
    $classes = collect([
        'wb-slide',
        $contentPositionClass,
        $contentWidthClass,
        $textColorClass,
    ])->filter()->implode(' ');
    $label = trim((string) ($block->setting('aria_label') ?? $block->title ?? ''));
@endphp

<article
    class="{{ $classes }}"
    data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}"
    @if ($label !== '') aria-label="{{ $label }}"@endif
>
    @if ($mediaUrl !== null)
        <img
            class="{{ $mediaClasses }}"
            src="{{ $mediaUrl }}"
            alt="{{ $mediaAlt }}"
            @if ($media?->width) width="{{ $media->width }}"@endif
            @if ($media?->height) height="{{ $media->height }}"@endif
            style="{{ $block->publicBackgroundMediaPositionStyle() }}"
        >
    @endif

    <div class="wb-slide-content">
        @foreach ($block->children as $child)
            @include('webblocks-cms::pages.partials.block', ['block' => $child])
        @endforeach
    </div>
</article>
