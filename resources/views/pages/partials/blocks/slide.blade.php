@php
    $media = $block->media;
    $sources = app(\WebBlocks\Cms\Support\Media\PublicMediaSources::class)->forImage($media);
    $mediaUrl = $sources['url'];
    $mobileMedia = $block->mobileMedia();
    $mobileSources = app(\WebBlocks\Cms\Support\Media\PublicMediaSources::class)->forImage($mobileMedia);
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
        $block->slideBackgroundOverlayClass(),
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
        @if ($mobileSources['url'] !== null || $sources['webp'] !== null)
            <picture>
                @if ($mobileSources['url'] !== null)
                    @if ($mobileSources['webp'] !== null)
                        <source media="{{ \WebBlocks\Cms\Support\Blocks\MobileBlockMedia::MEDIA_QUERY }}" type="image/webp" srcset="{{ $mobileSources['webp'] }}" sizes="100vw" @if ($mobileMedia?->width) width="{{ $mobileMedia->width }}" @endif @if ($mobileMedia?->height) height="{{ $mobileMedia->height }}" @endif>
                    @endif
                    <source media="{{ \WebBlocks\Cms\Support\Blocks\MobileBlockMedia::MEDIA_QUERY }}" srcset="{{ $mobileSources['srcset'] ?? $mobileSources['url'] }}" sizes="100vw" @if ($mobileMedia?->width) width="{{ $mobileMedia->width }}" @endif @if ($mobileMedia?->height) height="{{ $mobileMedia->height }}" @endif>
                @endif
                @if ($sources['webp'] !== null)
                    <source type="image/webp" srcset="{{ $sources['webp'] }}" sizes="100vw">
                @endif
        @endif
        <img
            class="{{ $mediaClasses }}"
            src="{{ $mediaUrl }}"
            @if ($sources['srcset']) srcset="{{ $sources['srcset'] }}" sizes="100vw" @endif
            alt="{{ $mediaAlt }}"
            loading="eager"
            fetchpriority="{{ $slideImagePriority ?? 'auto' }}"
            decoding="async"
            @if ($media?->width) width="{{ $media->width }}"@endif
            @if ($media?->height) height="{{ $media->height }}"@endif
            style="{{ $block->publicBackgroundMediaPositionStyle() }}"
        >
        @if ($mobileSources['url'] !== null || $sources['webp'] !== null)
            </picture>
        @endif
    @endif

    <div class="wb-slide-content">
        @foreach (app(\WebBlocks\Cms\Support\ContentSources\ContentCollectionRenderer::class)->children($block) as $child)
            @include('webblocks-cms::pages.partials.block', ['block' => $child])
        @endforeach
    </div>
</article>
@include('webblocks-cms::pages.partials.blocks.content-collection-pagination', ['block' => $block])
