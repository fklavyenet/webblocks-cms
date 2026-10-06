@php
    $mobileImage = $block->mobileMedia();
    $mobileImageVariant = $mobileVariant ?? null;
    $mobileImageUrl = $block->publicMobileMediaUrl($mobileImageVariant);
    $mobileCandidates = $mobileImageVariant === 'content' ? ($mobileImage?->responsiveCandidates() ?? []) : [];
    $mobileSrcset = count($mobileCandidates) >= 2
        ? collect($mobileCandidates)->map(fn ($candidate) => $candidate->url.' '.$candidate->width.'w')->implode(', ')
        : $mobileImageUrl;
@endphp
@if ($mobileImageUrl !== null)
    <source
        media="{{ \WebBlocks\Cms\Support\Blocks\MobileBlockMedia::MEDIA_QUERY }}"
        srcset="{{ $mobileSrcset }}"
        @if (count($mobileCandidates) >= 2) sizes="100vw" @endif
        @if ($mobileImage?->width) width="{{ $mobileImage->width }}" @endif
        @if ($mobileImage?->height) height="{{ $mobileImage->height }}" @endif
    >
@endif
