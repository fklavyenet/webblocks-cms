@php
    // Only the public render is localized. The stored URL remains canonical
    // for admin forms, API responses and managed CTA synchronization.
    $buttonUrl = $block->localizedPublicUrl($block->buttonLinkUrl());

    $blankTarget = $block->buttonLinkTarget() === '_blank';
@endphp

@if ($buttonUrl)
    <a href="{{ $buttonUrl }}" class="{{ $block->buttonLinkVariantClass() }}"@if ($blankTarget) target="_blank" rel="noopener noreferrer"@endif>{{ $block->title }}</a>
@endif
