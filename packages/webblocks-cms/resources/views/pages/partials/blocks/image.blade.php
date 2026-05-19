@php
    $image = $block->media;
    $imageSource = $image?->url();
    $caption = trim((string) ($block->title ?? ''));
    $altText = trim((string) ($block->subtitle ?? ''));
    $fallbackAltText = trim((string) ($image?->alt_text ?: $image?->title ?: $caption ?: 'Image'));
    $resolvedAltText = $altText !== '' ? $altText : $fallbackAltText;
    $href = trim((string) ($block->url ?? ''));
    $linkAttributes = '';

    if ($href !== '' && preg_match('/^(https?:\/\/|\/|#|mailto:|tel:)/i', $href)) {
        $linkAttributes = ' href="'.e($href).'"';
    } else {
        $href = '';
    }
@endphp

@if ($imageSource)
    <figure class="wb-stack wb-gap-2" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
        @if ($href !== '')
            <a{!! $linkAttributes !!}>
        @endif
        <img
            src="{{ $imageSource }}"
            alt="{{ $resolvedAltText }}"
            @if ($image?->width) width="{{ $image->width }}" @endif
            @if ($image?->height) height="{{ $image->height }}" @endif
        >
        @if ($href !== '')
            </a>
        @endif

        @if ($caption !== '')
            <figcaption>{{ $caption }}</figcaption>
        @endif
    </figure>
@endif
